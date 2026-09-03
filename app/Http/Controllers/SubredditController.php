<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Subscription;
use App\Services\Reddit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubredditController extends Controller
{
    /** How many posts the Explore unfold shows. */
    private const PREVIEW_POSTS = 3;

    public function __construct(protected Reddit $reddit) {}

    /**
     * A peek at a subreddit's first few posts, rendered as a bare HTML
     * fragment for the unfold on Explore. Fetched on demand — unfolding 25
     * rows up front would be 25 Reddit calls nobody asked for.
     */
    public function preview(string $subreddit): View
    {
        $subreddit = strtolower($subreddit);
        $error = null;
        $posts = [];

        try {
            $listing = $this->reddit->listing($subreddit, 'hot', ['limit' => self::PREVIEW_POSTS]);
            $posts = array_slice($listing['data']['children'] ?? [], 0, self::PREVIEW_POSTS);
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('partials.sub-preview', [
            'subreddit' => $subreddit,
            'posts' => $posts,
            'error' => $error,
        ]);
    }

    /**
     * Show a single subreddit's listing with sort/pagination and a
     * subscribe/unsubscribe toggle.
     */
    public function show(Request $request, string $subreddit): View
    {
        $subreddit = strtolower($subreddit);

        // Each sub remembers how you last sorted it. An explicit ?sort= (a tap
        // on the switcher) updates the memory; arriving without one (from the
        // feed, sidebar, a link) restores it.
        $sessionKey = 'sub-sort.'.$subreddit;

        if ($request->query('sort') !== null) {
            $sort = Reddit::normalizeSort($request->string('sort')->toString());
            $t = $request->query('t');
            $request->session()->put($sessionKey, ['sort' => $sort, 't' => $t]);
        } else {
            $remembered = $request->session()->get($sessionKey, []);
            // Normalized on read too, so junk remembered before clamping
            // existed doesn't stick to the subreddit forever.
            $sort = Reddit::normalizeSort($remembered['sort'] ?? 'hot');
            $t = $remembered['t'] ?? null;
        }

        $error = null;
        $posts = [];
        $after = null;
        $subredditInfo = null;

        try {
            $listing = $this->reddit->listing($subreddit, $sort, [
                'after' => $request->query('after'),
                't' => $t,
            ]);

            $posts = $listing['data']['children'] ?? [];
            $after = $listing['data']['after'] ?? null;
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        $isSubscribed = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('subreddit', $subreddit)
            ->exists();

        // Related communities from the sidebar, minus anything already followed.
        $related = [];
        try {
            $subscribed = array_flip($request->user()->subscriptions()->pluck('subreddit')->all());
            $related = array_values(array_filter(
                array_slice($this->reddit->relatedSubreddits($subreddit), 0, 12),
                fn ($n) => ! isset($subscribed[$n])
            ));
        } catch (RedditException $e) {
            // Related subs are a nicety; never let them break the page.
        }

        return view('subreddit.show', [
            'subreddit' => $subreddit,
            'posts' => $posts,
            'sort' => $sort,
            't' => $t,
            'after' => $after,
            'error' => $error,
            'isSubscribed' => $isSubscribed,
            'related' => $related,
        ]);
    }
}
