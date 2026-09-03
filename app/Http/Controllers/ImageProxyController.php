<?php

namespace App\Http\Controllers;

use App\Services\ImageProxy;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stream a geo-blocked image/video back to the browser. See ImageProxy for
 * why this exists and why it is locked down the way it is.
 */
class ImageProxyController extends Controller
{
    /** Refuse anything implausibly large for a Reddit image or gif. */
    private const MAX_BYTES = 25 * 1024 * 1024;

    private const ALLOWED_TYPES = ['image/', 'video/'];

    public function __invoke(Request $request): Response
    {
        // The signature covers the whole URL, so `u` cannot be swapped for an
        // internal address after the fact. Belt and braces: re-check the host,
        // since a signature only proves *we* built this link, not that the
        // allowlist was the same when we did.
        $url = (string) $request->query('u', '');

        abort_unless(ImageProxy::isFetchable($url), 403, 'Not a proxiable URL.');

        try {
            // No redirects: the allowlist check above covers only the URL we
            // were handed. Followed blindly, a 302 from an allowed host could
            // point anywhere — including at this network's own services.
            // Direct imgur media URLs serve bytes without redirecting.
            $response = Http::withHeaders([
                'User-Agent' => $request->userAgent() ?: 'Mozilla/5.0',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,video/*;q=0.8,*/*;q=0.5',
                'Referer' => 'https://imgur.com/',
            ])->withoutRedirecting()->timeout(15)->connectTimeout(5)->get($url);
        } catch (ConnectionException $e) {
            abort(504, 'Upstream timed out.');
        }

        abort_if($response->status() === 404, 404, 'Not found upstream.');
        abort_unless($response->successful(), 502, 'Upstream refused.');

        $type = strtolower((string) $response->header('Content-Type'));

        // Imgur answers a removed/blocked image with an HTML page, not an
        // image — don't pass that through as if it were media.
        abort_unless(Str::startsWith($type, self::ALLOWED_TYPES), 502, 'Upstream did not return media.');

        $body = $response->body();

        abort_if(strlen($body) > self::MAX_BYTES, 502, 'Upstream media too large.');

        return response($body, 200, [
            'Content-Type' => $type,
            'Content-Length' => (string) strlen($body),
            // Signed URLs are stable per image, so let the browser keep it.
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
