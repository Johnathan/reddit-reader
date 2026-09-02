<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Services\Reddit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(protected Reddit $reddit) {}

    /**
     * Post detail: full body/media, then the threaded comment tree.
     */
    public function show(Request $request, string $subreddit, string $id, ?string $slug = null): View
    {
        $subreddit = strtolower($subreddit);
        $error = null;
        $post = null;
        $comments = [];

        // Optional focus on a single comment subtree, used to drill into deep
        // threads in-app instead of bouncing the user out to Reddit.
        $focus = $request->string('comment')->toString();
        $focus = preg_match('/^[A-Za-z0-9]+$/', $focus) ? $focus : null;

        try {
            $params = $focus ? ['comment' => $focus, 'context' => 0] : [];
            $data = $this->reddit->comments($subreddit, $id, $params);

            $post = $data[0]['data']['children'][0]['data'] ?? null;
            $comments = $data[1]['data']['children'] ?? [];

            if ($post === null) {
                $error = 'That post could not be found.';
            } elseif ($request->user()) {
                // Opening the post IS reading it — record it server-side so
                // every device's feed dims it and baselines its "+N new".
                $request->user()->markSeen('reddit:'.$post['id'], $post['num_comments'] ?? null);
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        // Guests (shared links) get a read-only page with none of the app
        // shell — no sidebar, dock, or actions, so nothing personal renders.
        return view($request->user() ? 'posts.show' : 'posts.public', [
            'subreddit' => $subreddit,
            'post' => $post,
            'comments' => $comments,
            'error' => $error,
            'focusComment' => $focus,
        ]);
    }
}
