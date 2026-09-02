<?php

namespace Tests\Feature;

use App\Exceptions\RedditException;
use App\Services\Reddit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RedditChallengeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        @unlink(storage_path('app/reddit-cookies.json'));
    }

    /**
     * The interstitial Reddit actually serves, captured live on 2026-07-29 and
     * trimmed to the parts we parse. Note the base64 seed — it used to be
     * lowercase hex, and that change is what broke the solver.
     */
    private function challengePage(string $seed = '4C19qvYRXZbyHnM=', string $token = 'd721d3518ce68ad05ff7aa14467076f6'): string
    {
        return <<<HTML
        <html><head><title>Reddit - Please wait for verification</title>
        <script>document.addEventListener("DOMContentLoaded",async function(){var e=document.forms[0],n=(e.onsubmit=function(t){return new URLSearchParams("").forEach((e,n)=>t.target.appendChild(Object.assign(document.createElement("input"),{name:n,type:"hidden",value:e}))),!0},await(async e=>e+e)("{$seed}"));e.elements.namedItem("solution").value=n,e.requestSubmit()},{once:!0});</script>
        </head><body><form hidden method="GET" action="/">
        <input type="hidden" name="solution" />
        <input type="hidden" name="js_challenge" value="1"/>
        <input type="hidden" name="token" value="{$token}"/>
        <input type="hidden" name="jsc_orig_r" value=""/>
        </form></body></html>
        HTML;
    }

    private function listing(): array
    {
        return ['kind' => 'Listing', 'data' => ['children' => [], 'after' => null]];
    }

    /** Decode the query string of the challenge submission we sent. */
    private function sentChallengeQuery(): array
    {
        $found = [];

        Http::assertSent(function ($r) use (&$found) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            if (isset($q['js_challenge'])) {
                $found = $q;
            }

            return true;
        });

        return $found;
    }

    public function test_it_solves_the_js_challenge_on_a_403_and_retries(): void
    {
        $seed = '4C19qvYRXZbyHnM=';
        $attempt = 0;

        Http::fake([
            // The listing 403s until a clearance cookie exists; once the
            // challenge is solved, the retry succeeds.
            'www.reddit.com/r/programming/hot.json*' => function () use (&$attempt) {
                return ++$attempt === 1
                    ? Http::response('blocked', 403)
                    : Http::response($this->listing());
            },
            'www.reddit.com/*' => Http::response($this->challengePage($seed)),
        ]);

        $result = app(Reddit::class)->listing('programming');

        $this->assertSame('Listing', $result['kind']);
        $this->assertSame(2, $attempt, 'Expected the 403 to be retried after solving the challenge.');

        // The solution is the seed concatenated with itself, sent with every
        // hidden field the form carried.
        $q = $this->sentChallengeQuery();

        $this->assertSame($seed.$seed, $q['solution'] ?? null, 'base64 seed must survive the round trip');
        $this->assertSame('1', $q['js_challenge'] ?? null);
        $this->assertSame('d721d3518ce68ad05ff7aa14467076f6', $q['token'] ?? null);
        $this->assertArrayHasKey('jsc_orig_r', $q, 'every hidden input should be echoed back');
    }

    /** The seed alphabet is Reddit's business — hex worked before, base64 now. */
    public function test_it_solves_a_hex_seed_too(): void
    {
        Http::fake([
            'www.reddit.com/r/programming/hot.json*' => Http::sequence()
                ->push('blocked', 403)
                ->push($this->listing()),
            'www.reddit.com/*' => Http::response($this->challengePage('a1b2c3d4e5f60789')),
        ]);

        app(Reddit::class)->listing('programming');

        $this->assertSame('a1b2c3d4e5f60789a1b2c3d4e5f60789', $this->sentChallengeQuery()['solution'] ?? null);
    }

    /** If the form's onsubmit injects extra params, send those as well. */
    public function test_it_honours_extra_params_injected_by_onsubmit(): void
    {
        Http::fake([
            'www.reddit.com/r/programming/hot.json*' => Http::sequence()
                ->push('blocked', 403)
                ->push($this->listing()),
            'www.reddit.com/*' => Http::response(str_replace(
                'new URLSearchParams("")',
                'new URLSearchParams("foo=bar&baz=qux")',
                $this->challengePage()
            )),
        ]);

        app(Reddit::class)->listing('programming');

        $q = $this->sentChallengeQuery();
        $this->assertSame('bar', $q['foo'] ?? null);
        $this->assertSame('qux', $q['baz'] ?? null);
    }

    /** A challenge with no form at all is a shape change we must not guess at. */
    public function test_it_fails_loudly_if_the_form_disappears(): void
    {
        Http::fake([
            'www.reddit.com/r/programming/hot.json*' => Http::response('blocked', 403),
            'www.reddit.com/*' => Http::response(
                '<html><title>Please wait for verification</title><body>js_challenge</body></html>'
            ),
        ]);

        $this->expectException(RedditException::class);
        $this->expectExceptionMessageMatches('/browser check has changed/');

        app(Reddit::class)->listing('programming');
    }

    public function test_it_fails_loudly_if_reddit_changes_the_puzzle_shape(): void
    {
        Http::fake([
            'www.reddit.com/r/programming/hot.json*' => Http::response('blocked', 403),
            // Same interstitial, but the puzzle is no longer "double the seed".
            'www.reddit.com/*' => Http::response(
                str_replace('async e=>e+e', 'async e=>sha256(e)', $this->challengePage())
            ),
        ]);

        $this->expectException(RedditException::class);
        $this->expectExceptionMessageMatches('/browser check has changed/');

        app(Reddit::class)->listing('programming');
    }

    public function test_it_does_not_solve_the_challenge_when_the_request_already_succeeds(): void
    {
        Http::fake([
            'www.reddit.com/r/php/hot.json*' => Http::response($this->listing()),
            'www.reddit.com/*' => Http::response($this->challengePage()),
        ]);

        app(Reddit::class)->listing('php');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'js_challenge'));
    }
}
