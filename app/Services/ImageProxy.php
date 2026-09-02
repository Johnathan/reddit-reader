<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;

/**
 * Fetch geo-blocked media server-side instead of from the browser.
 *
 * Imgur pulled out of the UK rather than comply with the Online Safety Act, so
 * a UK browser hitting i.imgur.com gets a block page. The server usually isn't
 * in the UK, so it can fetch the bytes and hand them back.
 *
 * This is deliberately NOT a general-purpose proxy: only the hosts below are
 * allowed, and every URL is signed, so the endpoint can't be turned into an
 * SSRF gadget or an open relay for arbitrary traffic.
 */
class ImageProxy
{
    /**
     * Domains routed through a proxy: these and any subdomain of them, which
     * is the same coverage as the extension's ^https?://(\w+\.)?imgur\.com/.*
     *
     * Matching is exact-or-dot-suffixed, so `evil-imgur.com` and
     * `imgur.com.evil.test` do NOT match — that distinction is the whole
     * defence against this becoming an open proxy.
     *
     * @var array<int, string>
     */
    public const PROXIED_DOMAINS = [
        'imgur.com',
        'imgur.io',
    ];

    /**
     * Rewrite a media URL to go via the proxy, if its host needs it. Anything
     * else (i.redd.it, preview.redd.it, v.redd.it) is returned untouched —
     * Reddit's own CDN is not blocked, so proxying it would only add a hop.
     */
    public static function wrap(?string $url): ?string
    {
        if (! $url || ! self::needsProxy($url)) {
            return $url;
        }

        // By default this hands off to DuckDuckGo's image proxy, exactly as the
        // Imgur Unblocker extension does — DDG fetches imgur, so it works from
        // the UK regardless of where this server runs. See config/media.php.
        $template = trim((string) config('media.proxy_template'));

        if ($template === '' || $template === 'self') {
            return URL::signedRoute('image.proxy', ['u' => $url]);
        }

        return str_replace(
            ['{url_encoded}', '{url}'],
            [rawurlencode($url), $url],
            $template,
        );
    }

    /**
     * Wrap every URL in a list.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    public static function wrapAll(array $urls): array
    {
        return array_map(fn ($u) => self::wrap($u), $urls);
    }

    public static function needsProxy(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return self::isProxiableHost((string) parse_url($url, PHP_URL_HOST));
    }

    /**
     * Stricter gate for our own /img endpoint: it makes the outbound request
     * itself, so it will not be talked into plaintext.
     */
    public static function isFetchable(?string $url): bool
    {
        return $url
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && self::isProxiableHost((string) parse_url($url, PHP_URL_HOST));
    }

    public static function isProxiableHost(?string $host): bool
    {
        $host = strtolower(trim((string) $host));

        if ($host === '') {
            return false;
        }

        foreach (self::PROXIED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
