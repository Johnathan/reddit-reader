<?php

namespace App\Services;

use App\Exceptions\RedditException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Hacker News via the Algolia HN Search API — free, no auth, and (unlike the
 * official Firebase API) items/{id} returns the WHOLE nested comment tree in
 * one call.
 *
 * Everything returned from here is normalized into the same associative-array
 * shape the Reddit views render (id/title/author/created_utc/score/...), plus
 * a `_source` marker, so post cards and the comment partial need no HN-specific
 * rendering path.
 */
class HackerNews
{
    protected const BASE_URL = 'https://hn.algolia.com/api/v1';

    public const SOURCE = 'hn';

    /** Submissions below this score are hidden from the "new" merge — HN's
     *  firehose is mostly noise and would drown the timeline. */
    protected const MIN_NEW_POINTS = 5;

    /**
     * A page of stories for the unified feed.
     *
     * @return array{items: array<int, array<string, mixed>>, cursor: ?string}
     */
    public function listing(string $sort, ?string $cursor = null): array
    {
        [$path, $query] = $this->listingQuery($sort, $cursor);

        $cacheKey = 'hn:listing:'.md5($path.'|'.json_encode($query));

        $data = Cache::remember($cacheKey, 90, fn () => $this->request($path, $query));

        $hits = $data['hits'] ?? [];
        $items = array_map([$this, 'mapStory'], $hits);
        $short = count($hits) < ($query['hitsPerPage'] ?? 20);

        // "new" pages by timestamp: the next cursor is the oldest item shown.
        // Everything else pages Algolia's result pages by number.
        if ($sort === 'new') {
            $next = $items === [] || $short ? null : (string) end($items)['created_utc'];
        } else {
            $page = (int) ($query['page'] ?? 0);
            $next = $short || $page + 1 >= (int) ($data['nbPages'] ?? 1) ? null : (string) ($page + 1);
        }

        return ['items' => $items, 'cursor' => $next];
    }

    /**
     * One item (story or comment) plus its full nested tree, normalized:
     * the root in card shape, children in the comment partial's t1 shape.
     *
     * @return array{item: array<string, mixed>, comments: array<int, array<string, mixed>>}|null
     */
    public function thread(string $id): ?array
    {
        $cacheKey = 'hn:item:'.$id;

        $data = Cache::remember($cacheKey, 300, fn () => $this->request('/items/'.$id, []));

        if (! is_array($data) || ! isset($data['id'])) {
            return null;
        }

        return [
            'item' => $this->mapItem($data),
            'comments' => array_map([$this, 'mapComment'], $data['children'] ?? []),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function listingQuery(string $sort, ?string $cursor): array
    {
        if ($sort === 'new') {
            return ['/search_by_date', [
                'tags' => 'story',
                'hitsPerPage' => 20,
                'numericFilters' => 'points>='.self::MIN_NEW_POINTS
                    .($cursor ? ',created_at_i<'.(int) $cursor : ''),
            ]];
        }

        $page = max(0, (int) ($cursor ?? 0));

        return match ($sort) {
            // Most-loved of the last day, closest analogue to reddit top/day.
            // The window's edge is rounded to the hour: a live timestamp here
            // would land in the cache key, so no two requests could ever share
            // a cache entry — and page 2 would use a different window.
            'top' => ['/search', ['tags' => 'story', 'numericFilters' => 'created_at_i>'.(intdiv(now()->getTimestamp(), 3600) * 3600 - 86400), 'hitsPerPage' => 20, 'page' => $page]],
            // The actual HN front page, in rank order.
            default => ['/search', ['tags' => 'front_page', 'hitsPerPage' => 20, 'page' => $page]],
        };
    }

    /** @param array<string, mixed> $hit */
    protected function mapStory(array $hit): array
    {
        $url = $hit['url'] ?? null;

        return [
            '_source' => self::SOURCE,
            'id' => (string) ($hit['objectID'] ?? $hit['id'] ?? ''),
            'title' => $hit['title'] ?? '',
            'author' => $hit['author'] ?? null,
            'created_utc' => (int) ($hit['created_at_i'] ?? 0),
            'score' => (int) ($hit['points'] ?? 0),
            'num_comments' => (int) ($hit['num_comments'] ?? 0),
            'url' => $url,
            'domain' => $url ? preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) : null,
            'is_self' => $url === null,
            'selftext_html' => Reddit::sanitizeHtml($hit['story_text'] ?? $hit['text'] ?? null),
            'permalink' => 'https://news.ycombinator.com/item?id='.($hit['objectID'] ?? $hit['id'] ?? ''),
            'subreddit' => null,
            'stickied' => false,
            'over_18' => false,
        ];
    }

    /** @param array<string, mixed> $item  An /items/{id} payload (story or comment). */
    public function mapItem(array $item): array
    {
        $mapped = $this->mapStory($item + ['num_comments' => $this->countComments($item)]);
        $mapped['type'] = $item['type'] ?? 'story';
        $mapped['story_id'] = isset($item['story_id']) ? (string) $item['story_id'] : null;

        return $mapped;
    }

    /**
     * Into the exact shape partials/comment.blade.php renders, children and
     * all — so HN threads get collapse, swipe and archiving for free.
     *
     * @param  array<string, mixed>  $node
     */
    protected function mapComment(array $node): array
    {
        $children = array_map([$this, 'mapComment'], $node['children'] ?? []);

        return ['kind' => 't1', 'data' => [
            'id' => (string) ($node['id'] ?? ''),
            'author' => $node['author'] ?? '[deleted]',
            'body_html' => Reddit::sanitizeHtml($node['text'] ?? null),
            'score' => (int) ($node['points'] ?? 0),
            'created_utc' => (int) ($node['created_at_i'] ?? 0),
            'subreddit' => null,
            'replies' => $children === [] ? '' : ['data' => ['children' => $children]],
        ]];
    }

    /** @param array<string, mixed> $item */
    protected function countComments(array $item): int
    {
        $n = 0;

        foreach ($item['children'] ?? [] as $child) {
            $n += 1 + $this->countComments($child);
        }

        return $n;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected function request(string $path, array $query): ?array
    {
        try {
            $response = Http::timeout(12)->connectTimeout(5)
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }

        if ($response->failed()) {
            throw RedditException::unexpected($response->status());
        }

        return $response->json();
    }
}
