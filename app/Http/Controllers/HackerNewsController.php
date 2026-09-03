<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Services\HackerNews;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HackerNewsController extends Controller
{
    public function __construct(protected HackerNews $hackerNews) {}

    /**
     * An HN story (or a single comment subtree) with its full comment tree.
     */
    public function item(Request $request, string $id): View
    {
        abort_unless(preg_match('/^\d+$/', $id), 404);

        $error = null;
        $thread = null;

        try {
            $thread = $this->hackerNews->thread($id);

            if ($thread === null) {
                $error = 'That item could not be found on Hacker News.';
            } elseif (($thread['item']['type'] ?? 'story') !== 'comment') {
                // Stories only: a drilled-into comment permalink has its own
                // id, which is not what the feed card is keyed by. The seen
                // count must be on the FEED's scale (Algolia/descendants),
                // not this page's tree walk — see feedCommentCount.
                $count = $this->hackerNews->feedCommentCount((string) $thread['item']['id'])
                    ?? $thread['item']['num_comments'] ?? null;
                $request->user()->markSeen('hn:'.$thread['item']['id'], $count);
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('hn.item', [
            'id' => $id,
            'item' => $thread['item'] ?? null,
            'comments' => $thread['comments'] ?? [],
            'error' => $error,
        ]);
    }
}
