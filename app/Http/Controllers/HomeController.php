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
        $sort = $request->string('sort', 'hot')->toString();

        $subreddits = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('subreddit')
            ->pluck('subreddit')
            ->all();

        $cursors = collect((array) $request->query('c', []))
            ->map(fn ($v) => (string) $v)
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
