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

    // 'best' is deliberately absent: it's a front-page-only sort, and
    // /r/{sub}/best.json is not a thing — it falls back to hot here.
    protected const SORTS = ['hot', 'new', 'top', 'rising', 'controversial'];

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
     * The interstitial ships a one-line script that computes a solution, drops
     * it into a hidden form, and submits it. Reddit answers with the clearance
     * cookies that make the .json endpoints work. We do the same without a
     * browser: read the puzzle, read the form, submit it.
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

            $solved = Http::withHeaders($headers)
                ->withOptions(['cookies' => $jar])
                ->timeout(12)
                ->get(self::BASE_URL.'/', $this->challengeParams($body));

            if ($solved->failed()) {
                throw RedditException::challengeFailed();
            }
        } catch (ConnectionException $e) {
            throw RedditException::timedOut();
        }
    }

    /**
     * Rebuild the query the challenge form would have submitted.
     *
     * We echo back every hidden input the form carries (token, js_challenge,
     * jsc_orig_r, …) rather than naming them one by one, so Reddit adding or
     * renaming a field doesn't break us — only a change to the puzzle itself
     * does, and that fails loudly.
     *
     * @return array<string, string>
     */
    protected function challengeParams(string $body): array
    {
        // Only the challenge form's own inputs, if we can isolate it.
        $scope = preg_match('/<form\b[^>]*>(.*?)<\/form>/is', $body, $f) ? $f[1] : $body;

        $params = [];

        if (preg_match_all('/<input\b[^>]*>/i', $scope, $inputs)) {
            foreach ($inputs[0] as $tag) {
                if (! preg_match('/\bname="([^"]*)"/i', $tag, $n)) {
                    continue;
                }
                $params[$n[1]] = preg_match('/\bvalue="([^"]*)"/i', $tag, $v)
                    ? html_entity_decode($v[1])
                    : '';
            }
        }

        if ($params === []) {
            throw RedditException::challengeChanged();
        }

        // The form's onsubmit appends the pairs in this string as extra hidden
        // inputs before submitting. It is usually empty — but honour it if not.
        if (preg_match('/new\s+URLSearchParams\(\s*"([^"]*)"\s*\)/', $body, $extra) && $extra[1] !== '') {
            parse_str(html_entity_decode($extra[1]), $parsed);
            $params = array_merge($params, array_map('strval', $parsed));
        }

        $params['solution'] = $this->challengeSolution($body);

        return $params;
    }

    /**
     * Compute the puzzle's answer.
     *
     * The script is of the shape `(async e => e + e)("<seed>")` — "the answer
     * is the seed concatenated with itself", a proof you can run JS rather than
     * a real crypto puzzle. The seed's alphabet is Reddit's business: it was
     * lowercase hex, and is now base64 (`4C19qvYRXZbyHnM=`), which is exactly
     * what broke this. So accept any seed, and only throw if the puzzle stops
     * being "double it" — a real change we cannot answer by guessing.
     */
    protected function challengeSolution(string $body): string
    {
        $puzzle = '/\(\s*async\s*\(?\s*(\w+)\s*\)?\s*=>\s*\1\s*\+\s*\1\s*\)\s*\(\s*"([^"]*)"\s*\)/i';

        if (! preg_match($puzzle, $body, $m) || $m[2] === '') {
            throw RedditException::challengeChanged();
        }

        return $m[2].$m[2];
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
        // Hashed like every sibling key: {subreddit} is an unconstrained route
        // segment, so anything could land in it.
        $cacheKey = 'reddit:about:'.md5($subreddit);

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
     * Full-size image URLs a feed card can expand inline, RES-style.
     *
     * Galleries return every image; a direct image link returns itself; any
     * other post with a preview returns that preview's source. Text posts and
     * link posts with no preview return an empty array — meaning "no expando".
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     * @return array<int, string>
     */
    public static function expandableImages(array $post): array
    {
        if (($post['is_gallery'] ?? false) && ! empty($post['gallery_data']['items']) && ! empty($post['media_metadata'])) {
            $images = [];

            foreach ($post['gallery_data']['items'] as $item) {
                $meta = $post['media_metadata'][$item['media_id'] ?? ''] ?? null;
                $url = $meta['s']['u'] ?? $meta['s']['gif'] ?? null;

                if ($url) {
                    $images[] = html_entity_decode($url);
                }
            }

            return $images;
        }

        $url = ! empty($post['url']) ? html_entity_decode($post['url']) : null;
        $path = $url ? (string) parse_url($url, PHP_URL_PATH) : '';

        if ($url && preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
            return [$url];
        }

        $source = $post['preview']['images'][0]['source']['url'] ?? null;

        return $source ? [html_entity_decode($source)] : [];
    }

    /**
     * A text post's body, sanitized and ready to render inside an expando.
     * Empty string for link posts and for self posts with no body.
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     */
    public static function expandableText(array $post): string
    {
        return self::sanitizeHtml($post['selftext_html'] ?? null);
    }

    /**
     * A Reddit-hosted video, with a poster frame. The player is built only
     * when the card unfolds and never autoplays.
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     * @return array{src: string, poster: ?string}|null
     */
    public static function expandableVideo(array $post): ?array
    {
        $rv = $post['media']['reddit_video']
            ?? $post['preview']['reddit_video_preview']
            ?? null;

        if (! $rv || empty($rv['fallback_url'])) {
            return null;
        }

        $poster = $post['preview']['images'][0]['source']['url'] ?? null;

        return [
            'src' => html_entity_decode($rv['fallback_url']),
            // The fallback mp4 is the DASH video track alone — Reddit ships
            // audio separately, which is why it plays silent. The HLS playlist
            // muxes both, so any player that can use it gets sound.
            'hls' => ! empty($rv['hls_url']) ? html_entity_decode($rv['hls_url']) : null,
            'poster' => $poster ? html_entity_decode($poster) : null,
        ];
    }

    /**
     * Third-party embed markup (YouTube, Vimeo, …) as Reddit's oEmbed gives
     * it, minus scripts. Injected only on unfold — a hidden <iframe> still
     * loads, so rendering these up front would phone home to YouTube for
     * every card in the feed whether or not you opened it.
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     */
    public static function expandableEmbed(array $post): string
    {
        $html = $post['secure_media']['oembed']['html']
            ?? $post['media']['oembed']['html']
            ?? null;

        return self::sanitizeHtml($html ? html_entity_decode($html) : null);
    }

    /**
     * Everything a feed card can unfold, resolved to one shape so the card and
     * the "expand all" control can never disagree about what is expandable.
     *
     * Video and embeds win over stills: a video post's preview image is just a
     * poster frame, so showing both would be showing the same thing twice.
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     * @return array{text: string, video: ?array{src: string, poster: ?string}, embed: string, images: array<int, string>}
     */
    public static function expando(array $post): array
    {
        $video = self::expandableVideo($post);
        $embed = $video ? '' : self::expandableEmbed($post);
        $images = ($video || $embed !== '') ? [] : self::expandableImages($post);

        if ($video) {
            $video['src'] = ImageProxy::wrap($video['src']);
            $video['hls'] = ImageProxy::wrap($video['hls']);
            $video['poster'] = ImageProxy::wrap($video['poster']);
        }

        return [
            'text' => self::expandableText($post),
            'video' => $video,
            'embed' => $embed,
            // Imgur is geo-blocked in the UK; route those through the server.
            'images' => ImageProxy::wrapAll($images),
        ];
    }

    /**
     * Does this post have anything worth unfolding in the feed?
     *
     * @param  array<string, mixed>  $post  A listing child's `data` payload.
     */
    public static function isExpandable(array $post): bool
    {
        $e = self::expando($post);

        return $e['text'] !== '' || $e['video'] !== null || $e['embed'] !== '' || $e['images'] !== [];
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
        // javascript:/vbscript: URLs execute where a link merely navigates —
        // neutralise them in any attribute. data: is only blocked where it
        // navigates (href/formaction): data:image/* in a src is legitimate
        // in extracted articles and can't run script from an <img>.
        $html = preg_replace('#\s(href|src|xlink:href|formaction)\s*=\s*(["\']?)\s*(javascript|vbscript)\s*:#is', ' $1=$2#blocked:', $html) ?? '';
        $html = preg_replace('#\s(href|formaction)\s*=\s*(["\']?)\s*data\s*:#is', ' $1=$2#blocked:', $html) ?? '';

        return $html;
    }
}
