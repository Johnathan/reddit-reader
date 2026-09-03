<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\ReadingListItem;
use App\Services\Reddit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Save-for-later: tap once while scrolling the feed, work through the pile
 * afterwards. Everything that mutates answers JSON so nothing ever navigates
 * away mid-scroll.
 */
class ReadingListController extends Controller
{
    public function __construct(
        protected Reddit $reddit,
        protected \App\Services\HackerNews $hackerNews,
        protected \App\Services\ArticleExtractor $extractor,
    ) {}

    public function index(Request $request): View
    {
        // Two tabs: the queue (unread) and the archive of things already read.
        $reading = $request->string('view')->toString() === 'read' ? 'read' : 'unread';

        $items = $request->user()->readingList()
            ->when($reading === 'read', fn ($q) => $q->whereNotNull('read_at')->orderByDesc('read_at'))
            ->when($reading === 'unread', fn ($q) => $q->whereNull('read_at')->orderByDesc('created_at'))
            ->get();

        return view('reading-list.index', [
            'items' => $items,
            'view' => $reading,
            'unreadCount' => $request->user()->readingList()->whereNull('read_at')->count(),
            'readCount' => $request->user()->readingList()->whereNotNull('read_at')->count(),
        ]);
    }

    /**
     * Reader mode: a swipeable deck of the unread queue, each card the full
     * post and its comments. Content is lazy-loaded per card; advancing past a
     * card marks it read.
     */
    public function reader(Request $request): View
    {
        $items = $request->user()->readingList()
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->get();

        return view('reading-list.reader', ['items' => $items]);
    }

    /**
     * The rendered post + comments for one reading-list card. A bare HTML
     * fragment the reader injects when a card scrolls into view — never all of
     * them up front, which would be one Reddit call per saved item on load.
     */
    public function content(Request $request, string $postId): View
    {
        $item = $this->itemQuery($request, $postId)->firstOrFail();

        $error = null;
        $post = null;
        $comments = [];
        $article = null;

        try {
            if ($item->source === 'hn') {
                $thread = $this->hackerNews->thread($item->post_id);
                $post = $thread['item'] ?? null;
                $comments = $thread['comments'] ?? [];
            } elseif ($item->source !== 'reddit') {
                // An RSS item: the card is the extracted article.
                $article = $item->url ? $this->extractor->extract($item->url) : null;

                if ($article === null) {
                    $error = 'This article could not be extracted.';
                }
            } else {
                $data = $this->reddit->comments($item->subreddit, $item->post_id);
                $post = $data[0]['data']['children'][0]['data'] ?? null;
                $comments = $data[1]['data']['children'] ?? [];
            }

            if ($post === null && $article === null && $error === null) {
                $error = 'This post could not be loaded — it may have been removed.';
            }
        } catch (RedditException $e) {
            $error = $e->getMessage();
        }

        return view('reading-list.card-content', [
            'item' => $item,
            'post' => $post,
            'comments' => $comments,
            'article' => $article,
            'error' => $error,
        ]);
    }

    /** Mark read as the reader advances. Idempotent — never toggles back. */
    public function markRead(Request $request, string $postId): JsonResponse
    {
        $item = $this->itemQuery($request, $postId)->firstOrFail();

        if (! $item->isRead()) {
            $item->update(['read_at' => now()]);
        }

        return $this->state($request, ['read' => true]);
    }

    /**
     * Add a post. The card already has the title/thumbnail on screen, so it
     * posts them along rather than making us re-fetch the post from Reddit
     * just to name it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            // Only sources the app can resolve back into a page — an unknown
            // key would make a row that 404s forever when opened.
            'source' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['reddit', 'hn', ...array_keys(\App\Services\RssSource::all())])],
            'url' => ['nullable', 'url', 'max:2000'],
            'subreddit' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'],
            'title' => ['required', 'string', 'max:300'],
            'slug' => ['nullable', 'string', 'max:120'],
            'thumbnail' => ['nullable', 'url', 'max:2000'],
            'score' => ['nullable', 'integer', 'min:0'],
            'num_comments' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = $request->user()->readingList()->updateOrCreate(
            ['source' => $data['source'] ?? 'reddit', 'post_id' => $data['post_id']],
            [
                'url' => $data['url'] ?? null,
                'subreddit' => $data['subreddit'],
                'title' => $data['title'],
                'slug' => $data['slug'] ?? null,
                'thumbnail' => $data['thumbnail'] ?? null,
                'score' => $data['score'] ?? 0,
                'num_comments' => $data['num_comments'] ?? 0,
            ],
        );

        return $this->state($request, [
            'saved' => true,
            'message' => 'Added to reading list',
            'item' => $this->present($item),
        ]);
    }

    /** Remove a post from the list. */
    public function destroy(Request $request, string $postId): JsonResponse
    {
        $this->itemQuery($request, $postId)->delete();

        return $this->state($request, ['saved' => false, 'message' => 'Removed from reading list']);
    }

    /** Mark read / unread — the "done with this one" toggle. */
    public function toggleRead(Request $request, string $postId): JsonResponse
    {
        $item = $this->itemQuery($request, $postId)->firstOrFail();

        $item->update(['read_at' => $item->isRead() ? null : now()]);

        return $this->state($request, [
            'read' => $item->isRead(),
            'message' => $item->isRead() ? 'Marked as read' : 'Marked as unread',
        ]);
    }

    /** Clear the whole list, or just the ones already read. */
    public function clear(Request $request): JsonResponse
    {
        $query = $request->user()->readingList();

        if ($request->boolean('read_only')) {
            $query->whereNotNull('read_at');
        }

        $removed = $query->delete();

        return $this->state($request, [
            'message' => $removed === 1 ? '1 item cleared' : "{$removed} items cleared",
        ]);
    }

    /**
     * Every response carries the current counts so the dock badge can update
     * without a second request.
     *
     * @param  array<string, mixed>  $payload
     */
    private function state(Request $request, array $payload): JsonResponse
    {
        return response()->json($payload + [
            'count' => $request->user()->readingList()->count(),
            'unread' => $request->user()->readingList()->whereNull('read_at')->count(),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ReadingListItem $item): array
    {
        return [
            'post_id' => $item->post_id,
            'source' => $item->source,
            'title' => $item->title,
            'subreddit' => $item->subreddit,
            'label' => $item->sourceLabel(),
            'url' => $item->url(),
            'thumbnail' => $item->thumbnailUrl(),
        ];
    }

    /**
     * One item by id, scoped by source when the client says which. IDs are
     * only unique per source (the unique key is user+source+post_id), so an
     * unscoped match could act on a same-id row from a different source.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ReadingListItem, \App\Models\User>
     */
    private function itemQuery(Request $request, string $postId)
    {
        return $request->user()->readingList()
            ->where('post_id', $postId)
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')));
    }
}
