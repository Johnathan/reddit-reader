<?php

namespace App\Services;

use App\Exceptions\RedditException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A config-driven RSS source (see config/sources.php). Entries are normalized
 * into the same card shape as Reddit/HN items; the item id is a hash of the
 * article URL since feeds have no usable public id.
 *
 * RSS gives headline + link only — the full article comes from
 * ArticleExtractor when the item is opened.
 */
class RssSource
{
    /** @return array<string, array{name: string, feed: string, hosts: array<int, string>}> */
    public static function all(): array
    {
        return config('sources.rss', []);
    }

    /** @return array{name: string, feed: string, hosts: array<int, string>}|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * The feed's items, newest first, in card shape. Cursor is a timestamp:
     * pages after it are items older than it (feeds are small, so "paging" is
     * slicing one cached fetch).
     *
     * @return array{items: array<int, array<string, mixed>>, cursor: ?string}
     */
    public function listing(string $key, ?string $cursor = null): array
    {
        $source = self::find($key);

        if ($source === null) {
            return ['items' => [], 'cursor' => null];
        }

        $all = Cache::remember('rss:'.$key, 300, fn () => $this->fetch($key, $source));

        // The cursor is "timestamp:id" of the last item shown. A bare
        // timestamp cursor once dropped every OTHER item sharing that second
        // at a page boundary (wire feeds do publish batches in one second);
        // the id tie-break makes the position exact. The listing is sorted
        // (created_utc desc, id asc), so "after the cursor" is: strictly
        // older, or same second with a later id.
        if ($cursor !== null) {
            [$ts, $lastId] = array_pad(explode(':', $cursor, 2), 2, '');
            $ts = (int) $ts;

            $all = array_values(array_filter($all, fn ($i) => $i['created_utc'] < $ts
                || ($lastId !== '' && $i['created_utc'] === $ts && strcmp($i['id'], $lastId) > 0)));
        }

        $page = array_slice($all, 0, 20);
        $more = count($all) > count($page);
        $last = $page !== [] ? end($page) : null;

        return [
            'items' => $page,
            'cursor' => $more && $last ? $last['created_utc'].':'.$last['id'] : null,
        ];
    }

    /** Resolve an item id back to its entry (for the article page). */
    public function resolve(string $key, string $id): ?array
    {
        $source = self::find($key);

        if ($source === null) {
            return null;
        }

        $all = Cache::remember('rss:'.$key, 300, fn () => $this->fetch($key, $source));

        foreach ($all as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, feed: string, hosts: array<int, string>}  $source
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(string $key, array $source): array
    {
        try {
            $response = Http::timeout(12)->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'reader/1.0 (+rss)'])
                ->get($source['feed']);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }

        if ($response->failed()) {
            throw RedditException::unexpected($response->status());
        }

        $items = [];

        try {
            $xml = new \SimpleXMLElement($response->body(), LIBXML_NOCDATA | LIBXML_NONET);
        } catch (\Throwable $e) {
            return [];
        }

        // RSS 2.0 (channel/item) with an Atom (feed/entry) fallback.
        $entries = $xml->channel->item ?? $xml->entry ?? [];

        foreach ($entries as $entry) {
            $link = $this->entryLink($entry);
            $title = trim((string) $entry->title);

            if ($link === '' || $title === '') {
                continue;
            }

            $when = (string) ($entry->pubDate ?? $entry->updated ?? $entry->published ?? '');
            // strtotime() returns false on a malformed date; (int) false is 0,
            // which sank the item to 1970 ("55 years ago", bottom of every
            // "new" merge). An unparseable date reads as "just published".
            $ts = $when !== '' ? strtotime($when) : false;
            $thumb = null;
            foreach ($entry->children('http://search.yahoo.com/mrss/') as $mediaTag) {
                $thumb = (string) ($mediaTag['url'] ?? '') ?: $thumb;
            }

            $items[] = [
                '_source' => 'rss',
                'source_key' => $key,
                'source_name' => $source['name'],
                'id' => substr(sha1($link), 0, 16),
                'title' => $title,
                'author' => null,
                'created_utc' => $ts !== false ? $ts : time(),
                'score' => null,
                'num_comments' => null,
                'url' => $link,
                'domain' => preg_replace('/^www\./', '', (string) parse_url($link, PHP_URL_HOST)),
                'is_self' => false,
                'selftext_html' => '',
                'summary' => trim(strip_tags((string) ($entry->description ?? $entry->summary ?? ''))),
                'permalink' => $link,
                'subreddit' => null,
                'thumbnail' => $thumb,
                'stickied' => false,
                'over_18' => false,
            ];
        }

        // Deterministic order: newest first, id as tie-break — the paging
        // cursor depends on ties always resolving the same way.
        usort($items, fn ($a, $b) => [$b['created_utc'], $a['id']] <=> [$a['created_utc'], $b['id']]);

        return $items;
    }

    /**
     * The entry's article URL. Atom entries carry several <link> siblings
     * (self, alternate, related…) in arbitrary order — taking the first one
     * blindly can key every item to the FEED's own URL. Prefer the
     * rel="alternate" (or rel-less) link; RSS 2.0's plain <link>text</link>
     * falls through to the string cast.
     */
    protected function entryLink(\SimpleXMLElement $entry): string
    {
        foreach ($entry->link as $link) {
            $rel = (string) ($link['rel'] ?? '');
            $href = trim((string) ($link['href'] ?? ''));

            if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                return $href;
            }
        }

        return trim((string) ($entry->link['href'] ?? $entry->link));
    }
}
