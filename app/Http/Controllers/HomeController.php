<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Subscription;
use App\Services\Reddit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    /**
     * Combined home feed of every subreddit the current user subscribes to.
     */
    public function index(Request $request): View
    {
        $sort = $request->string('sort', 'hot')->toString();

        $subreddits = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('subreddit')
            ->pluck('subreddit')
            ->all();

        if (empty($subreddits)) {
            return view('home', [
                'subreddits' => [],
                'posts' => [],
                'sort' => $sort,
                'after' => null,
                'error' => null,
            ]);
        }

        $error = null;
        $posts = [];
        $after = null;

        try {
            $listing = $this->reddit->homeListing($subreddits, $sort, [
                'after' => $request->query('after'),
                't' => $request->query('t'),
            ]);

            $posts = $listing['data']['children'] ?? [];
            $after = $listing['data']['after'] ?? null;
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('home', [
            'subreddits' => $subreddits,
            'posts' => $posts,
            'sort' => $sort,
            'after' => $after,
            'error' => $error,
        ]);
    }
}
