<?php

namespace App\Http\Controllers;

use App\Exceptions\RedditException;
use App\Models\Folder;
use App\Models\SavedItem;
use App\Models\Tag;
use App\Services\Reddit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The archive. Distinct from the reading list: this keeps a COPY of the
 * content so it survives deletion, and is organised (folders, tags) and
 * searchable rather than being a queue you burn down.
 */
class SavedController extends Controller
{
    public function __construct(
        protected Reddit $reddit,
        protected \App\Services\HackerNews $hackerNews,
        protected \App\Services\ArticleExtractor $extractor,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $q = trim($request->string('q')->toString());

        $items = $user->savedItems()
            ->with(['folder', 'tags'])
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when($request->filled('folder'), function ($query) use ($request, $user) {
                $folder = $user->folders()->where('slug', $request->string('folder'))->first();
                $query->where('folder_id', $folder?->id ?? 0);
            })
            ->when($request->filled('tag'), function ($query) use ($request, $user) {
                $tag = $user->tags()->where('slug', $request->string('tag'))->first();
                $query->whereHas('tags', fn ($t) => $t->where('tags.id', $tag?->id ?? 0));
            })
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('saved.index', [
            'items' => $items,
            'q' => $q,
            'folders' => $user->folders()->withCount('items')->orderBy('name')->get(),
            'tags' => $user->tags()->withCount('items')->orderBy('name')->get(),
            'activeFolder' => $request->string('folder')->toString(),
            'activeTag' => $request->string('tag')->toString(),
            'activeKind' => $request->string('kind')->toString(),
            'total' => $user->savedItems()->count(),
        ]);
    }

    /**
     * Archive a post or comment.
     *
     * The body is pulled from Reddit server-side rather than posted up from
     * the page: the archive's whole value is fidelity, and the client's DOM is
     * both lossy and untrusted. The listing/comments calls are cached, so
     * saving something you are looking at usually costs nothing.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:post,comment'],
            'source' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/'],
            'reddit_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'subreddit' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'],
            'post_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'url' => ['nullable', 'url', 'max:2000'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        $source = $data['source'] ?? 'reddit';
        $isComment = $data['kind'] === SavedItem::KIND_COMMENT;
        $postId = $isComment ? ($data['post_id'] ?? null) : $data['reddit_id'];

        if ($isComment && ! $postId && $source === 'reddit') {
            return response()->json(['message' => 'A comment needs its post.'], 422);
        }

        try {
            $snapshot = match (true) {
                $source === 'hn' => $this->snapshotHackerNews($data['reddit_id']),
                $source !== 'reddit' => $this->snapshotArticle($source, $data['url'] ?? null),
                default => $this->snapshot($data['subreddit'], $postId, $isComment ? $data['reddit_id'] : null),
            };
        } catch (RedditException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($snapshot === null) {
            return response()->json(['message' => 'Could not read that to archive it.'], 404);
        }

        // A folder from someone else's account is simply ignored.
        $folderId = $data['folder_id'] ?? null;
        if ($folderId && ! $request->user()->folders()->whereKey($folderId)->exists()) {
            $folderId = null;
        }

        $item = $request->user()->savedItems()->updateOrCreate(
            ['source' => $source, 'reddit_id' => $data['reddit_id']],
            $snapshot + ['kind' => $data['kind'], 'folder_id' => $folderId],
        );

        return response()->json([
            'saved' => true,
            'id' => $item->id,
            'message' => $isComment ? 'Comment archived' : 'Post archived',
            'count' => $request->user()->savedItems()->count(),
        ]);
    }

    public function destroy(Request $request, string $redditId): JsonResponse
    {
        $request->user()->savedItems()->where('reddit_id', $redditId)->get()->each->delete();

        return response()->json([
            'saved' => false,
            'message' => 'Removed from your archive',
            'count' => $request->user()->savedItems()->count(),
        ]);
    }

    /** Move between folders, retag, or annotate. */
    public function update(Request $request, SavedItem $savedItem): JsonResponse
    {
        abort_unless($savedItem->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:300'],
        ]);

        if ($request->has('folder_id')) {
            $folderId = $data['folder_id'] ?: null;
            $savedItem->folder_id = $folderId && $request->user()->folders()->whereKey($folderId)->exists()
                ? $folderId
                : null;
        }

        if ($request->has('note')) {
            $savedItem->note = $data['note'];
        }

        $savedItem->save();

        if ($request->has('tags')) {
            $ids = collect(explode(',', (string) $data['tags']))
                ->map(fn ($name) => Tag::findOrCreateFor($request->user()->id, $name))
                ->filter()
                ->pluck('id');

            $savedItem->tags()->sync($ids);
        }

        return response()->json(['message' => 'Updated', 'id' => $savedItem->id]);
    }

    public function storeFolder(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);

        $folder = $request->user()->folders()->create([
            'name' => $data['name'],
            'slug' => Folder::uniqueSlugFor($request->user()->id, $data['name']),
        ]);

        // The Organise popover creates folders inline over fetch; the header
        // form on /saved is a plain form post and wants the redirect.
        if ($request->wantsJson()) {
            return response()->json(['id' => $folder->id, 'name' => $folder->name, 'slug' => $folder->slug]);
        }

        return back();
    }

    public function destroyFolder(Request $request, Folder $folder): RedirectResponse
    {
        abort_unless($folder->user_id === $request->user()->id, 404);

        // Items outlive their folder — deleting a folder must never bin the
        // archive inside it.
        $folder->items()->update(['folder_id' => null]);
        $folder->delete();

        return back();
    }

    /**
     * Archive an HN story or comment. One Algolia call returns everything.
     *
     * @return array<string, mixed>|null
     */
    private function snapshotHackerNews(string $id): ?array
    {
        $thread = $this->hackerNews->thread($id);

        if ($thread === null) {
            return null;
        }

        $item = $thread['item'];
        $isComment = ($item['type'] ?? 'story') === 'comment';

        return [
            'subreddit' => 'hackernews',
            'author' => $item['author'],
            'title' => $isComment ? null : \Illuminate\Support\Str::limit($item['title'], 290),
            'post_id' => $isComment ? $item['story_id'] : null,
            'post_title' => null,
            'slug' => null,
            'body_html' => $item['selftext_html'] ?? '',
            'permalink' => $item['permalink'],
            'thumbnail' => null,
            'score' => (int) ($item['score'] ?? 0),
            'num_comments' => (int) ($item['num_comments'] ?? 0),
            'posted_at' => $item['created_utc'] ? now()->setTimestamp($item['created_utc']) : null,
        ];
    }

    /**
     * Archive an RSS article: extract the full text now, so what is stored
     * reads forever regardless of the site.
     *
     * @return array<string, mixed>|null
     */
    private function snapshotArticle(string $sourceKey, ?string $url): ?array
    {
        $sourceName = \App\Services\RssSource::find($sourceKey)['name'] ?? $sourceKey;

        if (! $url || ! \App\Services\ArticleExtractor::allowedUrl($url)) {
            return null;
        }

        $article = $this->extractor->extract($url);

        if ($article === null) {
            return null;
        }

        return [
            'subreddit' => $sourceKey,
            'author' => $article['byline'] ?? $sourceName,
            'title' => \Illuminate\Support\Str::limit($article['title'] ?? $url, 290),
            'post_id' => null,
            'post_title' => null,
            'slug' => null,
            'body_html' => $article['html'],
            'permalink' => $url,
            'thumbnail' => null,
            'score' => 0,
            'num_comments' => 0,
            'posted_at' => now(),
        ];
    }

    /**
     * Read a post (and optionally one comment inside it) straight from Reddit
     * and reduce it to the columns we archive.
     *
     * @return array<string, mixed>|null
     */
    private function snapshot(string $subreddit, string $postId, ?string $commentId): ?array
    {
        // Asking for a single comment returns just that subtree — no walking.
        $params = $commentId ? ['comment' => $commentId, 'context' => 0] : [];
        $data = $this->reddit->comments($subreddit, $postId, $params);

        $post = $data[0]['data']['children'][0]['data'] ?? null;

        if ($post === null) {
            return null;
        }

        $slug = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug($post['title'] ?? ''), 110, '');

        if ($commentId === null) {
            $thumb = (! empty($post['thumbnail']) && str_starts_with($post['thumbnail'], 'http'))
                ? $post['thumbnail'] : null;

            return [
                'subreddit' => $post['subreddit'] ?? $subreddit,
                'author' => $post['author'] ?? null,
                'title' => \Illuminate\Support\Str::limit($post['title'] ?? '', 290),
                'post_id' => null,
                'post_title' => null,
                'slug' => $slug,
                'body_html' => Reddit::sanitizeHtml($post['selftext_html'] ?? null),
                'permalink' => $post['permalink'] ?? null,
                'thumbnail' => $thumb,
                'score' => (int) ($post['score'] ?? 0),
                'num_comments' => max(0, (int) ($post['num_comments'] ?? 0)),
                'posted_at' => isset($post['created_utc']) ? now()->setTimestamp((int) $post['created_utc']) : null,
            ];
        }

        $comment = $data[1]['data']['children'][0]['data'] ?? null;

        if ($comment === null || ($comment['id'] ?? null) !== $commentId) {
            return null;
        }

        return [
            'subreddit' => $comment['subreddit'] ?? $subreddit,
            'author' => $comment['author'] ?? null,
            'title' => null,
            'post_id' => $postId,
            'post_title' => \Illuminate\Support\Str::limit($post['title'] ?? '', 290),
            'slug' => $slug,
            'body_html' => Reddit::sanitizeHtml($comment['body_html'] ?? null),
            'permalink' => $comment['permalink'] ?? null,
            'thumbnail' => null,
            'score' => (int) ($comment['score'] ?? 0),
            'num_comments' => 0,
            'posted_at' => isset($comment['created_utc']) ? now()->setTimestamp((int) $comment['created_utc']) : null,
        ];
    }
}
