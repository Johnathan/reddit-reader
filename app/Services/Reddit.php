<?php

namespace App\Services;

use App\Exceptions\RedditException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Reddit's JSON endpoints (the "add .json to any URL"
 * trick), with raw_json=1 so entities come back unescaped and short-lived
 * caching so a personal single-user reader doesn't hammer Reddit.
 *
 * Reddit blocks the public .json endpoints from many IPs, so when
 * REDDIT_CLIENT_ID / REDDIT_CLIENT_SECRET are set in .env (create an app of
 * type "script" at https://www.reddit.com/prefs/apps) the same requests are
 * sent to oauth.reddit.com with an app-only client_credentials token instead.
 * Without credentials it falls back to the public endpoints.
 */
class Reddit
{
    protected const BASE_URL = 'https://www.reddit.com';

    protected const OAUTH_BASE_URL = 'https://oauth.reddit.com';

    protected const TOKEN_CACHE_KEY = 'reddit:oauth:token';

    protected const USER_AGENT = 'macos:personal-reddit-reader:v1.0 (personal single-user reader)';

    protected const SORTS = ['hot', 'new', 'top', 'rising', 'best'];

    protected const TIME_RANGES = ['hour', 'day', 'week', 'month', 'year', 'all'];

    /**
     * Fetch a subreddit (or multi-subreddit, e.g. "programming+php") listing.
     *
     * @param  array<string, mixed>  $params  Extra query params: after, before, t (for top), limit, count.
     */
    public function listing(string $subredditOrMulti, string $sort = 'hot', array $params = []): ?array
    {
        $sort = $this->normalizeSort($sort);
        $query = $this->buildQuery($sort, $params);

        $subredditOrMulti = strtolower($subredditOrMulti);
        $cacheKey = 'reddit:listing:'.md5($subredditOrMulti.'|'.$sort.'|'.json_encode($query));

        return Cache::remember($cacheKey, 90, function () use ($subredditOrMulti, $sort, $query) {
            return $this->request("/r/{$subredditOrMulti}/{$sort}.json", $query);
        });
    }

    /**
     * Fetch a combined home-feed listing for a set of subscribed subreddits.
     *
     * @param  array<int, string>  $subreddits
     * @param  array<string, mixed>  $params
     */
    public function homeListing(array $subreddits, string $sort = 'hot', array $params = []): ?array
    {
        $multi = implode('+', array_map('strtolower', $subreddits));

        return $this->listing($multi, $sort, $params);
    }

    /**
     * Fetch a single post plus its comment tree.
     *
     * @param  array<string, mixed>  $params  Extra query params: sort (comment sort), limit, depth.
     * @return array{0: ?array, 1: ?array}|null  [post listing, comments listing] as decoded from Reddit's
     *                                            two-element top-level JSON array, or null on failure.
     */
    public function comments(string $subreddit, string $id, array $params = []): ?array
    {
        $query = array_filter(array_merge(['raw_json' => 1], $params), fn ($v) => $v !== null && $v !== '');
        $subreddit = strtolower($subreddit);
        $cacheKey = 'reddit:comments:'.md5($subreddit.'|'.$id.'|'.json_encode($query));

        return Cache::remember($cacheKey, 300, function () use ($subreddit, $id, $query) {
            return $this->request("/r/{$subreddit}/comments/{$id}.json", $query);
        });
    }

    /**
     * Search for subreddits by name/description.
     */
    public function searchSubreddits(string $query): ?array
    {
        $q = trim($query);

        if ($q === '') {
            return null;
        }

        $params = ['q' => $q, 'raw_json' => 1, 'include_over_18' => 'on'];
        $cacheKey = 'reddit:subsearch:'.md5($q);

        return Cache::remember($cacheKey, 3600, function () use ($params) {
            return $this->request('/subreddits/search.json', $params);
        });
    }

    /**
     * Build the query string for a listing request, applying raw_json and
     * only including the `t` (time range) param when relevant to `top`.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildQuery(string $sort, array $params): array
    {
        $query = ['raw_json' => 1];

        if (! empty($params['after'])) {
            $query['after'] = $params['after'];
        }

        if (! empty($params['before'])) {
            $query['before'] = $params['before'];
        }

        if ($sort === 'top' || $sort === 'controversial') {
            $t = $params['t'] ?? 'day';
            $query['t'] = in_array($t, self::TIME_RANGES, true) ? $t : 'day';
        }

        if (! empty($params['limit'])) {
            $query['limit'] = $params['limit'];
        }

        return $query;
    }

    protected function normalizeSort(string $sort): string
    {
        $sort = strtolower($sort);

        return in_array($sort, self::SORTS, true) ? $sort : 'hot';
    }

    /**
     * Perform the actual HTTP request, translating transport/HTTP failures
     * into a RedditException that controllers can turn into a friendly page.
     *
     * @param  array<string, mixed>  $query
     */
    protected function request(string $path, array $query): ?array
    {
        try {
            $client = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])
                ->timeout(8)
                ->connectTimeout(5);

            $baseUrl = self::BASE_URL;

            if ($token = $this->accessToken()) {
                $client = $client->withToken($token);
                $baseUrl = self::OAUTH_BASE_URL;
            }

            $response = $client->get($baseUrl.$path, $query);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }

        // An expired/revoked token comes back as 401; drop it so the next
        // request mints a fresh one instead of caching the failure for 90s.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            throw RedditException::unexpected(401);
        }

        if ($response->status() === 429) {
            throw RedditException::rateLimited();
        }

        if ($response->status() === 403) {
            throw RedditException::forbidden();
        }

        if ($response->status() === 404) {
            throw RedditException::notFound();
        }

        if ($response->failed()) {
            throw RedditException::unexpected($response->status());
        }

        try {
            return $response->json();
        } catch (\Throwable $e) {
            throw RedditException::unexpected($response->status());
        }
    }

    /**
     * App-only OAuth token (client_credentials), or null when no Reddit app
     * credentials are configured. Tokens last an hour; cache for 50 minutes.
     */
    protected function accessToken(): ?string
    {
        $clientId = config('services.reddit.client_id');
        $clientSecret = config('services.reddit.client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        return Cache::remember(self::TOKEN_CACHE_KEY, 3000, function () use ($clientId, $clientSecret) {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->asForm()
                ->timeout(8)
                ->connectTimeout(5)
                ->post(self::BASE_URL.'/api/v1/access_token', [
                    'grant_type' => 'client_credentials',
                ]);

            $token = $response->json('access_token');

            if ($response->failed() || ! is_string($token) || $token === '') {
                throw RedditException::unexpected($response->status());
            }

            return $token;
        });
    }

    /**
     * Format a score/comment count the way Reddit's own UI does: 1234 -> "1.2k".
     */
    public static function formatScore(int $n): string
    {
        $abs = abs($n);

        if ($abs < 1000) {
            return (string) $n;
        }

        if ($abs < 1_000_000) {
            return round($n / 1000, 1).'k';
        }

        return round($n / 1_000_000, 1).'m';
    }

    /**
     * Strip <script> tags (and their contents) from Reddit's pre-rendered
     * HTML (selftext_html / body_html) before it's echoed with {!! !!}.
     * Reddit's HTML is otherwise safe-ish (it's their own sanitized markdown
     * render), but we don't trust it blindly since this is rendered raw.
     */
    public static function sanitizeHtml(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', '', $html) ?? '';

        return $html;
    }
}
