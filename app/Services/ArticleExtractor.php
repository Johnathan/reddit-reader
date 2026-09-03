<?php

namespace App\Services;

use App\Exceptions\DisallowedRedirect;
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

    /** Cached marker for "we tried, it can't be extracted". */
    protected const FAILED = 'failed';

    /**
     * @return array{title: ?string, html: string, excerpt: ?string, byline: ?string}|null
     */
    public function extract(string $url): ?array
    {
        if (! self::allowedUrl($url)) {
            return null;
        }

        // Not Cache::remember — it reads a stored null back as a miss, so a
        // page that can't be extracted would be re-fetched and re-parsed on
        // every single card open. Failures are cached too, just more briefly
        // so a transient upstream error isn't remembered for a day.
        $key = 'article:'.sha1($url);
        $cached = Cache::get($key);

        if ($cached === self::FAILED) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $article = $this->parse($url);

        Cache::put($key, $article ?? self::FAILED, $article !== null ? 86400 : 600);

        return $article;
    }

    /**
     * @return array{title: ?string, html: string, excerpt: ?string, byline: ?string}|null
     */
    protected function parse(string $url): ?array
    {
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
            // News sites do redirect (http→https, vanity URLs), so redirects
            // are followed — but every hop is re-checked against the host
            // allowlist, or a 302 from an allowed host could point the fetch
            // at anything, including this network's own services.
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->withOptions(['allow_redirects' => [
                'max' => 3,
                'on_redirect' => function ($req, $res, $uri) {
                    if (! self::allowedUrl((string) $uri)) {
                        throw new DisallowedRedirect((string) $uri);
                    }
                },
            ]])->timeout(15)->connectTimeout(5)->get($url);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        } catch (DisallowedRedirect $e) {
            return null;   // tried to leave the allowlist — not extractable
        }

        if ($response->failed() || strlen($response->body()) > self::MAX_BYTES) {
            return null;
        }

        return $response->body();
    }
}
