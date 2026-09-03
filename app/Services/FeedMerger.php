<?php

namespace App\Services;

use App\Exceptions\RedditException;

/**
 * The unified timeline: k-way merge of every configured source into one feed.
 *
 * Each source contributes one ordered page per request plus an opaque cursor;
 * cursors travel in the load-more URL as c[reddit], c[hn], c[bbcni]... so the
 * merge is stateless. A source with no cursor left is finished; a source that
 * errors is skipped for the page rather than sinking the whole feed (its error
 * is surfaced once).
 *
 * Ordering: "new" merges strictly by timestamp — every post has one. Ranked
 * sorts (hot/top/rising) have no comparable timestamp order, so pages
 * interleave by each item's position in its own source's ranking.
 */
class FeedMerger
{
    public function __construct(
        protected Reddit $reddit,
        protected HackerNews $hackerNews,
        protected RssSource $rss,
    ) {}

    /**
     * @param  array<int, string>  $subreddits
     * @param  array<string, string>  $cursors  Source key => cursor. An ABSENT
     *                                          key on page one means "start";
     *                                          the literal 'end' means done.
     * @param  array<int, string>  $disabledSources  Source groups the user has
     *                                               switched off: 'reddit',
     *                                               'hn', 'news' (all RSS).
     * @return array{posts: array<int, array<string, mixed>>, cursors: array<string, string>, errors: array<int, string>}
     */
    public function page(array $subreddits, string $sort, ?string $timeRange, array $cursors, bool $firstPage, array $disabledSources = []): array
    {
        $streams = [];
        $next = [];
        $errors = [];

        $pull = function (string $key, callable $fetch) use (&$streams, &$next, &$errors, $cursors, $firstPage) {
            $cursor = $cursors[$key] ?? null;

            if ($cursor === 'end' || (! $firstPage && $cursor === null)) {
                return;   // exhausted on an earlier page
            }

            // 'start' is a failed page one carried forward (see the catch):
            // a real position that fetches from the top, unlike null, which
            // on later pages means "this source is done".
            if ($cursor === 'start') {
                $cursor = null;
            }

            try {
                [$items, $nextCursor] = $fetch($cursor);
                $streams[$key] = $items;
                $next[$key] = $nextCursor ?? 'end';
            } catch (RedditException $e) {
                $errors[] = $e->getMessage();
                // Hold position so the next "load more" retries this page.
                // A failed page one has no cursor to hold — carry 'start',
                // never 'end', or one hiccup retires the source for good.
                $next[$key] = $cursor ?? 'start';
            }
        };

        $enabled = fn (string $group) => ! in_array($group, $disabledSources, true);

        if ($subreddits !== [] && $enabled('reddit')) {
            $pull('reddit', function ($cursor) use ($subreddits, $sort, $timeRange) {
                $listing = $this->reddit->homeListing($subreddits, $sort, [
                    'after' => $cursor, 't' => $timeRange,
                ]);

                $items = array_map(
                    fn ($child) => ($child['data'] ?? []) + ['_source' => 'reddit'],
                    $listing['data']['children'] ?? [],
                );

                return [$items, $listing['data']['after'] ?? null];
            });
        }

        if ($enabled('hn')) {
            $pull('hn', function ($cursor) use ($sort) {
                $page = $this->hackerNews->listing($sort, $cursor);

                return [$page['items'], $page['cursor']];
            });
        }

        if ($enabled('news')) {
            foreach (array_keys(RssSource::all()) as $key) {
                $pull($key, function ($cursor) use ($key) {
                    $page = $this->rss->listing($key, $cursor);

                    return [$page['items'], $page['cursor']];
                });
            }
        }

        // Carry finished sources forward as 'end' so later pages don't restart
        // them; there is another page while any source still has a cursor.
        return [
            'posts' => $this->merge($streams, $sort),
            'cursors' => $next,
            'errors' => $errors,
        ];
    }

    /** @param array<string, string> $cursors */
    public static function hasMore(array $cursors): bool
    {
        foreach ($cursors as $cursor) {
            if ($cursor !== 'end') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $streams
     * @return array<int, array<string, mixed>>
     */
    protected function merge(array $streams, string $sort): array
    {
        $tagged = [];

        foreach ($streams as $key => $items) {
            $count = max(1, count($items));

            foreach ($items as $i => $item) {
                // Normalized rank: item 3-of-20 sits beside item 3-of-20 from
                // any other source, regardless of page sizes.
                $item['_seq'] = $i / $count;
                $tagged[] = $item;
            }
        }

        if ($sort === 'new') {
            usort($tagged, fn ($a, $b) => ($b['created_utc'] ?? 0) <=> ($a['created_utc'] ?? 0));
        } else {
            usort($tagged, fn ($a, $b) => $a['_seq'] <=> $b['_seq']);
        }

        return $tagged;
    }
}
