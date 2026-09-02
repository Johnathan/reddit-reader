<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageProxy;
use App\Services\Reddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ImageProxyTest extends TestCase
{
    use RefreshDatabase;

    /** Build a self-proxied URL regardless of the configured default. */
    private function selfProxied(string $url): string
    {
        config(['media.proxy_template' => 'self']);

        return ImageProxy::wrap($url);
    }

    // ---- what gets rewritten --------------------------------------------

    /**
     * The out-of-the-box behaviour must match the Imgur Unblocker extension:
     *   regexSubstitution "https://external-content.duckduckgo.com/iu/?u=\0.png"
     * i.e. the raw original URL with ".png" stuck on the end.
     */
    public function test_by_default_imgur_urls_go_to_the_duckduckgo_proxy(): void
    {
        $this->assertSame(
            'https://external-content.duckduckgo.com/iu/?u=https://i.imgur.com/abc123.jpg.png',
            ImageProxy::wrap('https://i.imgur.com/abc123.jpg'),
        );
    }

    public function test_every_imgur_subdomain_and_path_is_covered(): void
    {
        foreach ([
            'https://i.imgur.com/abc.jpg',
            'https://imgur.com/a/AbCdEf',
            'https://m.imgur.com/abc.png',
            'https://p.imgur.com/album/x',
            'http://i.imgur.com/abc.gif',
        ] as $url) {
            $this->assertTrue(ImageProxy::needsProxy($url), "{$url} should be proxied");
        }
    }

    public function test_imgur_urls_can_be_routed_through_our_own_endpoint_instead(): void
    {
        $wrapped = $this->selfProxied('https://i.imgur.com/abc123.jpg');

        $this->assertStringContainsString('/img?', $wrapped);
        $this->assertStringContainsString('signature=', $wrapped);
    }

    public function test_reddits_own_cdn_is_left_alone(): void
    {
        foreach ([
            'https://i.redd.it/abc.jpg',
            'https://preview.redd.it/abc.jpg?width=1080',
            'https://v.redd.it/abc/DASH_720.mp4',
        ] as $url) {
            $this->assertSame($url, ImageProxy::wrap($url), "{$url} should not be proxied");
        }
    }

    public function test_our_own_endpoint_refuses_to_fetch_over_plain_http(): void
    {
        // The extension proxies http too, but our endpoint makes the outbound
        // request itself and will not be talked into plaintext.
        $this->assertFalse(ImageProxy::isFetchable('http://i.imgur.com/abc.jpg'));
        $this->assertTrue(ImageProxy::isFetchable('https://i.imgur.com/abc.jpg'));
    }

    public function test_lookalike_hosts_are_not_proxied(): void
    {
        foreach ([
            'https://imgur.com.evil.test/a.jpg',
            'https://notimgur.com/a.jpg',
            'https://evil.test/?x=i.imgur.com',
        ] as $url) {
            $this->assertFalse(ImageProxy::needsProxy($url), "{$url} must not be proxiable");
        }
    }

    public function test_the_feed_resolver_proxies_imgur_images(): void
    {
        $expando = Reddit::expando([
            'url' => 'https://i.imgur.com/abc123.jpg',
        ]);

        $this->assertSame(
            'https://external-content.duckduckgo.com/iu/?u=https://i.imgur.com/abc123.jpg.png',
            $expando['images'][0],
        );
    }

    // ---- external-proxy mode ---------------------------------------------

    public function test_an_external_proxy_template_is_used_when_configured(): void
    {
        config(['media.proxy_template' => 'https://imgup.uk/proxy/?url={url_encoded}']);

        $this->assertSame(
            'https://imgup.uk/proxy/?url=https%3A%2F%2Fi.imgur.com%2Fabc123.jpg',
            ImageProxy::wrap('https://i.imgur.com/abc123.jpg'),
        );
    }

    public function test_the_external_template_still_only_applies_to_allowed_hosts(): void
    {
        config(['media.proxy_template' => 'https://imgup.uk/proxy/?url={url}']);

        $this->assertSame('https://i.redd.it/abc.jpg', ImageProxy::wrap('https://i.redd.it/abc.jpg'));
    }

    // ---- the endpoint itself ---------------------------------------------

    public function test_it_streams_an_allowed_image_back(): void
    {
        Http::fake(['i.imgur.com/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg'])]);

        $res = $this->actingAs(User::factory()->create())
            ->get($this->selfProxied('https://i.imgur.com/abc123.jpg'));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('JPEGBYTES', $res->getContent());
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get('/img?u='.urlencode('https://i.imgur.com/abc123.jpg'))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_tampered_url_is_rejected(): void
    {
        Http::fake();
        $signed = $this->selfProxied('https://i.imgur.com/abc123.jpg');

        // Swap the target for an internal address, keeping the signature.
        $tampered = str_replace(
            urlencode('https://i.imgur.com/abc123.jpg'),
            urlencode('http://169.254.169.254/latest/meta-data/'),
            $signed,
        );

        $this->actingAs(User::factory()->create())->get($tampered)->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_validly_signed_but_disallowed_host_is_still_refused(): void
    {
        Http::fake();

        // Sign an internal address directly — the host check must still bite.
        $url = URL::signedRoute('image.proxy', ['u' => 'https://169.254.169.254/latest/meta-data/']);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_block_page_is_not_passed_off_as_an_image(): void
    {
        // Imgur answers a UK request with HTML, not an image.
        Http::fake(['i.imgur.com/*' => Http::response('<html>blocked</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs(User::factory()->create())
            ->get($this->selfProxied('https://i.imgur.com/abc123.jpg'))
            ->assertStatus(502);
    }

    public function test_it_works_without_authentication_when_signed(): void
    {
        // Shared post links are viewable logged-out, so their media must be
        // too. The signature is the gate, not the session.
        Http::fake(['i.imgur.com/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->get($this->selfProxied('https://i.imgur.com/abc123.jpg'))->assertOk();
    }
}
