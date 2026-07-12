<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Subscription;
use App\Services\Reddit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubredditController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    /**
     * Show a single subreddit's listing with sort/pagination and a
     * subscribe/unsubscribe toggle.
     */
    public function show(Request $request, string $subreddit): View
    {
        $subreddit = strtolower($subreddit);
        $sort = $request->string('sort', 'hot')->toString();

        $error = null;
        $posts = [];
        $after = null;
        $subredditInfo = null;

        try {
            $listing = $this->reddit->listing($subreddit, $sort, [
                'after' => $request->query('after'),
                't' => $request->query('t'),
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

        return view('subreddit.show', [
            'subreddit' => $subreddit,
            'posts' => $posts,
            'sort' => $sort,
            'after' => $after,
            'error' => $error,
            'isSubscribed' => $isSubscribed,
        ]);
    }
}
