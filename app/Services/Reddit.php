<?php

namespace App\Services;

use App\Exceptions\RedditException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Reddit's public JSON endpoints (the "add .json to any
 * URL" trick), with raw_json=1 so entities come back unescaped and short-lived
 * caching so a personal single-user reader doesn't hammer Reddit.
 *
 * No account, no API key, no OAuth. Reddit serves logged-out clients a JS
 * challenge instead of the JSON; we solve it (see solveChallenge) and bank the
 * clearance cookie. That's the whole trick — everything here is anonymous.
 */
class Reddit
{
    protected const BASE_URL = 'https://www.reddit.com';

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
            $response = Http::withHeaders($this->browserHeaders())
                ->withOptions(['cookies' => $this->cookieJar()])
                ->timeout(12)
                ->connectTimeout(5)
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }

        // Reddit gates logged-out traffic behind a JS challenge. Without a
        // clearance cookie the JSON endpoints 403 (or the challenge page is
        // served in place of the JSON). Solve it, then retry exactly once.
        if (! $retryingAfterChallenge
            && ($response->status() === 403 || $this->isChallenge($response->body()))) {
            $this->solveChallenge();

            return $this->request($path, $query, retryingAfterChallenge: true);
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
     * The most popular subreddits right now (for the Explore page).
     */
    public function popularSubreddits(array $params = []): ?array
    {
        $query = array_filter([
            'raw_json' => 1,
            'limit' => $params['limit'] ?? 25,
            'after' => $params['after'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $cacheKey = 'reddit:popular:'.md5(json_encode($query));

        return Cache::remember($cacheKey, 1800, function () use ($query) {
            return $this->request('/subreddits/popular.json', $query);
        });
    }

    /**
     * Typeahead suggestions for a partial subreddit name — the endpoint that
     * powers Reddit's own search box. Returns a lean, view-ready shape.
     *
     * @return array<int, array{name: string, subscribers: int, icon: ?string, nsfw: bool}>
     */
    public function suggestSubreddits(string $query): array
    {
        $q = trim($query);

        if (mb_strlen($q) < 2) {
            return [];
        }

        $cacheKey = 'reddit:suggest:'.md5(mb_strtolower($q));

        return Cache::remember($cacheKey, 300, function () use ($q) {
            $data = $this->request('/api/subreddit_autocomplete_v2.json', [
                'query' => $q,
                'limit' => 10,
                'include_over_18' => 'false',
                'include_profiles' => 'false',
                'raw_json' => 1,
            ]);

            $children = $data['data']['children'] ?? [];

            return array_values(array_filter(array_map(function ($child) {
                $d = $child['data'] ?? [];

                // Autocomplete can return user profiles (t2) and empty rows; keep real subs.
                if (($child['kind'] ?? null) !== 't5' || empty($d['display_name'])) {
                    return null;
                }

                $icon = $d['community_icon'] ?: ($d['icon_img'] ?? '');
                $icon = $icon ? strtok($icon, '?') : null; // Drop Reddit's cache-busting query string.

                return [
                    'name' => $d['display_name'],
                    'subscribers' => (int) ($d['subscribers'] ?? 0),
                    'icon' => $icon ?: null,
                    'nsfw' => (bool) ($d['over_18'] ?? false),
                ];
            }, $children)));
        });
    }

    /**
     * A subreddit's "about" record: subscriber count, description, icon, etc.
     */
    public function about(string $subreddit): ?array
    {
        $subreddit = strtolower($subreddit);
        $cacheKey = 'reddit:about:'.$subreddit;

        return Cache::remember($cacheKey, 3600, function () use ($subreddit) {
            $data = $this->request("/r/{$subreddit}/about.json", ['raw_json' => 1]);

            return $data['data'] ?? null;
        });
    }

    /**
     * Subreddits related to the given one, mined from its sidebar markdown.
     *
     * Reddit's official "related communities" widget is OAuth-only, but the
     * sidebar (`description`) almost always links sibling subs as r/name — so
     * we pull those out. Not exhaustive, but it needs no account and it's the
     * curators' own hand-picked list. Names are returned lowercased, de-duped,
     * and with the subreddit itself removed.
     *
     * @return array<int, string>
     */
    public function relatedSubreddits(string $subreddit): array
    {
        $about = $this->about($subreddit);

        $markdown = ($about['description'] ?? '').' '.($about['public_description'] ?? '');

        if (trim($markdown) === '') {
            return [];
        }

        preg_match_all('~(?:^|[\s(\[/])/?r/([A-Za-z0-9_]{2,21})\b~', $markdown, $matches);

        $self = strtolower($subreddit);

        $related = [];
        foreach ($matches[1] as $name) {
            $name = strtolower($name);
            // Skip self, Reddit's meta-subs, and anything already collected.
            if ($name === $self || in_array($name, ['all', 'popular', 'random'], true)) {
                continue;
            }
            $related[$name] = true;
        }

        return array_keys($related);
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
