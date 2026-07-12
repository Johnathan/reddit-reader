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

        try {
            $data = $this->reddit->comments($subreddit, $id);

            $post = $data[0]['data']['children'][0]['data'] ?? null;
            $comments = $data[1]['data']['children'] ?? [];

            if ($post === null) {
                $error = 'That post could not be found.';
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('posts.show', [
            'subreddit' => $subreddit,
            'post' => $post,
            'comments' => $comments,
            'error' => $error,
        ]);
    }
}
