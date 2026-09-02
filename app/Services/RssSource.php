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

        if ($cursor !== null) {
            $all = array_values(array_filter($all, fn ($i) => $i['created_utc'] < (int) $cursor));
        }

        $page = array_slice($all, 0, 20);
        $more = count($all) > count($page);

        return [
            'items' => $page,
            'cursor' => $more && $page !== [] ? (string) end($page)['created_utc'] : null,
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
            $link = trim((string) ($entry->link['href'] ?? $entry->link));
            $title = trim((string) $entry->title);

            if ($link === '' || $title === '') {
                continue;
            }

            $when = (string) ($entry->pubDate ?? $entry->updated ?? $entry->published ?? '');
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
                'created_utc' => $when !== '' ? (int) strtotime($when) : 0,
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

        usort($items, fn ($a, $b) => $b['created_utc'] <=> $a['created_utc']);

        return $items;
    }
}
