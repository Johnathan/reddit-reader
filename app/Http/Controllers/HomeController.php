<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\FeedMerger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(protected FeedMerger $merger) {}

    /**
     * The unified home timeline: subscribed subreddits + Hacker News + every
     * configured RSS source, merged into one feed. Per-source cursors travel
     * in the load-more URL as c[source].
     */
    public function index(Request $request): View
    {
        $sort = \App\Services\Reddit::normalizeSort($request->string('sort', 'hot')->toString());

        $subreddits = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('subreddit')
            ->pluck('subreddit')
            ->all();

        // Only string-keyed string cursors survive: ?c=foo gives an integer
        // key no source matches, and ?c[reddit][a]=1 gives an array value —
        // either once made every source read as exhausted (an empty feed) or
        // warned on the string cast. Anything malformed reads as page one.
        $cursors = collect((array) $request->query('c', []))
            ->filter(fn ($v, $k) => is_string($k) && is_string($v) && $v !== '')
            ->all();

        $disabledSources = $request->user()->disabledSources();

        $page = $this->merger->page(
            $subreddits,
            $sort,
            $request->query('t'),
            $cursors,
            firstPage: $cursors === [],
            disabledSources: $disabledSources,
        );

        $loadMoreUrl = FeedMerger::hasMore($page['cursors'])
            ? route('home', array_filter([
                'sort' => $sort,
                't' => $request->query('t'),
                'c' => $page['cursors'],
            ]))
            : null;

        return view('home', [
            'subreddits' => $subreddits,
            'posts' => $page['posts'],
            'sort' => $sort,
            'error' => $page['errors'][0] ?? null,
            'loadMoreUrl' => $loadMoreUrl,
            'disabledSources' => $disabledSources,
        ]);
    }
}
