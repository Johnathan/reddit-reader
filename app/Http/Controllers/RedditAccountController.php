<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Links Johnathan's Reddit account to the app via OAuth.
 *
 * This exists for one reason: age-gated (18+) subreddits. Reddit serves
 * anonymous clients an empty listing for them and hides them from search
 * entirely — no cookie or header gets around it. A user-context token does.
 *
 * The authorization-code flow means the Reddit password is typed on Reddit's
 * own site, never here. We keep only the refresh token (encrypted).
 */
class RedditAccountController extends Controller
{
    private const SCOPES = ['identity', 'read', 'mysubreddits'];

    public function connect(Request $request): RedirectResponse
    {
        if (! config('services.reddit.client_id')) {
            return redirect()->route('subs.index')
                ->with('error', 'Set REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET in .env first — see the README.');
        }

        $state = Str::random(40);
        Cache::put('reddit:oauth:state:'.$state, $request->user()->id, now()->addMinutes(10));

        return redirect()->away('https://www.reddit.com/api/v1/authorize?'.http_build_query([
            'client_id' => config('services.reddit.client_id'),
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => route('reddit.callback'),
            'duration' => 'permanent', // We want a refresh token, not a one-hour token.
            'scope' => implode(' ', self::SCOPES),
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state');
        $cacheKey = 'reddit:oauth:state:'.$state;

        // The state must match one we issued, for this user, within 10 minutes.
        if (! $state || Cache::pull($cacheKey) !== $request->user()->id) {
            return redirect()->route('subs.index')
                ->with('error', 'That Reddit sign-in did not match the one we started. Please try again.');
        }

        if ($error = $request->query('error')) {
            return redirect()->route('subs.index')
                ->with('error', 'Reddit declined the connection: '.$error);
        }

        $response = Http::withBasicAuth(
            config('services.reddit.client_id'),
            config('services.reddit.client_secret'),
        )
            ->withHeaders(['User-Agent' => config('services.reddit.user_agent')])
            ->asForm()
            ->timeout(15)
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type' => 'authorization_code',
                'code' => (string) $request->query('code'),
                'redirect_uri' => route('reddit.callback'),
            ]);

        $refreshToken = $response->json('refresh_token');
        $accessToken = $response->json('access_token');

        if ($response->failed() || ! $refreshToken) {
            return redirect()->route('subs.index')
                ->with('error', 'Could not complete the Reddit connection (HTTP '.$response->status().'). Check the app’s redirect URI matches exactly.');
        }

        // Grab the username purely so the UI can show which account is linked.
        $me = Http::withToken($accessToken)
            ->withHeaders(['User-Agent' => config('services.reddit.user_agent')])
            ->timeout(15)
            ->get('https://oauth.reddit.com/api/v1/me');

        $request->user()->forceFill([
            'reddit_refresh_token' => $refreshToken,
            'reddit_username' => $me->json('name'),
        ])->save();

        // Linking changes what Reddit will show us, and listings cached while
        // anonymous are exactly the empty 18+ ones. Start clean.
        Cache::flush();

        return redirect()->route('subs.index')
            ->with('status', 'Reddit account connected as u/'.$me->json('name').'. 18+ subreddits are now visible (if enabled in your Reddit preferences).');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'reddit_refresh_token' => null,
            'reddit_username' => null,
        ])->save();

        Cache::flush();

        return redirect()->route('subs.index')->with('status', 'Reddit account disconnected.');
    }
}
