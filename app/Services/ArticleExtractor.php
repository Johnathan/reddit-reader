<?php

namespace App\Services;

use App\Exceptions\RedditException;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Full-article extraction: fetch a page server-side, boil it down to readable
 * content with Readability, sanitize, cache for a day.
 *
 * SSRF guard: this will only fetch hosts that belong to a configured source
 * (config/sources.php). It is NOT a general purpose "fetch any URL" service,
 * for the same reason the image proxy isn't.
 */
class ArticleExtractor
{
    protected const MAX_BYTES = 3 * 1024 * 1024;

    /**
     * @return array{title: ?string, html: string, excerpt: ?string, byline: ?string}|null
     */
    public function extract(string $url): ?array
    {
        if (! self::allowedUrl($url)) {
            return null;
        }

        return Cache::remember('article:'.sha1($url), 86400, function () use ($url) {
            $html = $this->fetch($url);

            if ($html === null) {
                return null;
            }

            try {
                $readability = new Readability(new Configuration([
                    'fixRelativeURLs' => true,
                    'originalURL' => $url,
                    'summonCthulhu' => true,   // strip <script> wholesale before parsing
                ]));
                $readability->parse($html);
            } catch (\Throwable $e) {
                return null;
            }

            $content = $readability->getContent();

            if (! $content) {
                return null;
            }

            return [
                'title' => $readability->getTitle() ?: null,
                'html' => Reddit::sanitizeHtml($content),
                'excerpt' => $readability->getExcerpt() ?: null,
                'byline' => $readability->getAuthor() ?: null,
            ];
        });
    }

    /** Is this URL on a host some configured source owns? */
    public static function allowedUrl(?string $url): bool
    {
        if (! $url || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (RssSource::all() as $source) {
            foreach ($source['hosts'] as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->timeout(15)->connectTimeout(5)->get($url);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }

        if ($response->failed() || strlen($response->body()) > self::MAX_BYTES) {
            return null;
        }

        return $response->body();
    }
}
