<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Subscription;
use App\Services\Reddit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    /**
     * Live typeahead for the search boxes. Returns [] rather than an error on a
     * Reddit hiccup — a dead suggestion drop-down shouldn't surface a scary message.
     */
    public function suggest(Request $request): JsonResponse
    {
        try {
            $suggestions = $this->reddit->suggestSubreddits((string) $request->query('q', ''));
        } catch (RedditException $e) {
            $suggestions = [];
        }

        return response()->json($suggestions);
    }

    /**
     * Manage-subscriptions page: current subscriptions plus a subreddit
     * search box (results come from Reddit's subreddit search endpoint).
     */
    public function index(Request $request): View
    {
        $subscriptions = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('subreddit')
            ->get();

        $q = trim((string) $request->query('q', ''));
        $results = [];
        $error = null;

        if ($q !== '') {
            try {
                $search = $this->reddit->searchSubreddits($q);
                $results = $search['data']['children'] ?? [];
            } catch (RedditException $e) {
                $error = $e->getMessage();
            }
        }

        return view('subs.index', [
            'subscriptions' => $subscriptions,
            'q' => $q,
            'results' => $results,
            'error' => $error,
        ]);
    }

    /**
     * Subscribe to a subreddit. Returns JSON to fetch() callers (so the button
     * toggles in place without a page reload) and a redirect otherwise.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'subreddit' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{2,21}$/'],
        ]);

        $name = strtolower($validated['subreddit']);

        Subscription::firstOrCreate([
            'user_id' => $request->user()->id,
            'subreddit' => $name,
        ]);

        $message = "Subscribed to r/{$validated['subreddit']}.";

        if ($request->expectsJson()) {
            return response()->json(['subscribed' => true, 'subreddit' => $name, 'message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * Unsubscribe from a subreddit.
     */
    public function destroy(Request $request, string $subreddit): RedirectResponse|JsonResponse
    {
        $name = strtolower($subreddit);

        Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('subreddit', $name)
            ->delete();

        $message = "Unsubscribed from r/{$subreddit}.";

        if ($request->expectsJson()) {
            return response()->json(['subscribed' => false, 'subreddit' => $name, 'message' => $message]);
        }

        return back()->with('status', $message);
    }
}
