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
     * The interstitial Reddit actually serves, trimmed to the parts we parse.
     */
    private function challengePage(string $seed = 'a1b2c3d4e5f60789', string $token = 'tok123'): string
    {
        return <<<HTML
        <html><head><title>Reddit - Please wait for verification</title>
        <script>document.addEventListener("DOMContentLoaded",async function(){var e=document.forms[0],n=(e.onsubmit=function(t){return !0},await(async e=>e+e)("{$seed}"));e.elements.namedItem("solution").value=n,e.requestSubmit()},{once:!0});</script>
        </head><body><form hidden method="GET" action="/">
        <input type="hidden" name="solution" />
        <input type="hidden" name="js_challenge" value="1"/>
        <input type="hidden" name="token" value="{$token}"/>
        </form></body></html>
        HTML;
    }

    private function listing(): array
    {
        return ['kind' => 'Listing', 'data' => ['children' => [], 'after' => null]];
    }

    public function test_it_solves_the_js_challenge_on_a_403_and_retries(): void
    {
        $seed = 'a1b2c3d4e5f60789';
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

        // The solution is the seed concatenated with itself, sent with the token.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'js_challenge=1')
            && str_contains($r->url(), 'solution='.$seed.$seed)
            && str_contains($r->url(), 'token=tok123'));
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
