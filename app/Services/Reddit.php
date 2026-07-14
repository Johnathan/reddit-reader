<?php

namespace App\Services;

use App\Exceptions\RedditException;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
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

    protected const BROWSER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

    protected ?CookieJar $jar = null;

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
    protected function request(string $path, array $query, bool $retryingAfterChallenge = false): ?array
    {
        try {
            $client = Http::withHeaders($this->browserHeaders())
                ->withOptions(['cookies' => $this->cookieJar()])
                ->timeout(12)
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

        // Reddit gates logged-out traffic behind a JS challenge. Without a
        // clearance cookie the JSON endpoints 403 (or the challenge page is
        // served in place of the JSON). Solve it, then retry exactly once.
        if (! $retryingAfterChallenge && ! $this->accessToken()
            && ($response->status() === 403 || $this->isChallenge($response->body()))) {
            $this->solveChallenge();

            return $this->request($path, $query, retryingAfterChallenge: true);
        }

        // An expired/revoked token comes back as 401; drop it so the next
        // request mints a fresh one instead of caching the failure for 90s.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY.':user');
            Cache::forget(self::TOKEN_CACHE_KEY.':app');
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
     * Headers a real browser sends. Reddit serves its JS challenge (rather than
     * a hard 403) to clients that look like browsers, and the challenge is the
     * thing we can actually solve — so we present as one.
     */
    protected function browserHeaders(): array
    {
        return [
            'User-Agent' => self::BROWSER_USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
        ];
    }

    /**
     * One cookie jar for the life of this instance, persisted to disk so the
     * clearance cookie survives across requests (and app restarts) instead of
     * re-solving the challenge on every page load.
     */
    protected function cookieJar(): CookieJar
    {
        return $this->jar ??= new FileCookieJar(storage_path('app/reddit-cookies.json'), true);
    }

    /**
     * Is this response Reddit's "Please wait for verification" interstitial
     * rather than the content we asked for?
     */
    protected function isChallenge(string $body): bool
    {
        return str_contains($body, 'js_challenge') || str_contains($body, 'Please wait for verification');
    }

    /**
     * Solve Reddit's logged-out JS challenge and bank the resulting cookies.
     *
     * The interstitial ships a one-line script of the shape
     *
     *     await (async e => e + e)("<hex seed>")
     *
     * i.e. "the answer is the seed concatenated with itself" — a proof you can
     * run JS, not a real crypto puzzle. It then GETs the form back with that
     * solution plus the page's token, and Reddit sets the clearance cookies
     * that make the .json endpoints work. We do the same thing without a
     * browser. If Reddit ever changes the puzzle shape, the seed regex stops
     * matching and we throw rather than silently looping.
     */
    protected function solveChallenge(): void
    {
        $jar = $this->cookieJar();
        $headers = $this->browserHeaders();

        try {
            $page = Http::withHeaders($headers)
                ->withOptions(['cookies' => $jar])
                ->timeout(12)
                ->get(self::BASE_URL.'/');

            $body = $page->body();

            if (! $this->isChallenge($body)) {
                return; // No challenge in the way; whatever 403'd was not this.
            }

            // (async e => e + e)("<seed>")  ->  solution is seed.seed
            if (! preg_match('/\(\s*async\s+(\w+)\s*=>\s*\1\s*\+\s*\1\s*\)\s*\(\s*"([0-9a-f]+)"\s*\)/i', $body, $m)) {
                throw RedditException::challengeChanged();
            }

            $seed = $m[2];

            if (! preg_match('/name="token"\s+value="([^"]+)"/', $body, $t)) {
                throw RedditException::challengeChanged();
            }

            $solved = Http::withHeaders($headers)
                ->withOptions(['cookies' => $jar])
                ->timeout(12)
                ->get(self::BASE_URL.'/', [
                    'solution' => $seed.$seed,
                    'js_challenge' => 1,
                    'token' => $t[1],
                    'jsc_orig_r' => '',
                ]);

            if ($solved->failed()) {
                throw RedditException::challengeFailed();
            }
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }
    }

    /**
     * App-only OAuth token (client_credentials), or null when no Reddit app
     * credentials are configured. Optional — the challenge flow above needs no
     * credentials at all — but if creds exist we prefer the official API.
     */
    protected function accessToken(): ?string
    {
        $clientId = config('services.reddit.client_id');
        $clientSecret = config('services.reddit.client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        // A linked Reddit account is the only way to see age-gated (18+) subs,
        // so prefer the user's refresh token over an app-only one when present.
        $refreshToken = $this->userRefreshToken();

        $grant = $refreshToken
            ? ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]
            : ['grant_type' => 'client_credentials'];

        $cacheKey = self::TOKEN_CACHE_KEY.($refreshToken ? ':user' : ':app');

        return Cache::remember($cacheKey, 3000, function () use ($clientId, $clientSecret, $grant) {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->asForm()
                ->timeout(8)
                ->connectTimeout(5)
                ->post(self::BASE_URL.'/api/v1/access_token', $grant);

            $token = $response->json('access_token');

            if ($response->failed() || ! is_string($token) || $token === '') {
                throw RedditException::unexpected($response->status());
            }

            return $token;
        });
    }

    /**
     * The signed-in user's Reddit refresh token, if they've linked an account.
     * Falls back to null in console/queue contexts where there is no auth user.
     */
    protected function userRefreshToken(): ?string
    {
        $user = auth()->user();

        if ($user && ! empty($user->reddit_refresh_token)) {
            return $user->reddit_refresh_token;
        }

        // Single-user app: outside a request (e.g. `reddit:check`) just use the
        // owner's token if one exists.
        return User::query()->whereNotNull('reddit_refresh_token')->value('reddit_refresh_token');
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
