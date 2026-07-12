<?php

namespace App\Console\Commands;

use App\Services\Reddit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Sanity-check Reddit connectivity: `php artisan reddit:check`.
 * Tells you which transport is in use (OAuth vs public .json) and whether
 * a real listing comes back, so a 403 is diagnosable without the browser.
 */
class RedditCheck extends Command
{
    protected $signature = 'reddit:check {subreddit=programming}';

    protected $description = 'Verify Reddit API connectivity and print a sample listing';

    public function handle(Reddit $reddit): int
    {
        $sub = $this->argument('subreddit');

        $hasCreds = config('services.reddit.client_id') && config('services.reddit.client_secret');

        $this->line($hasCreds
            ? '<fg=green>✓</> Credentials found — using OAuth (oauth.reddit.com)'
            : '<fg=yellow>!</> No REDDIT_CLIENT_ID/REDDIT_CLIENT_SECRET — falling back to public .json endpoints, which Reddit blocks from most IPs');

        Cache::forget('reddit:oauth:token');

        try {
            $listing = $reddit->listing($sub, 'hot', ['limit' => 5]);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('✗ '.$e->getMessage());

            if (! $hasCreds) {
                $this->newLine();
                $this->line('Fix: create a <options=bold>script</> app at https://www.reddit.com/prefs/apps');
                $this->line('then set REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET in .env');
            }

            return self::FAILURE;
        }

        $posts = $listing['data']['children'] ?? [];

        if ($posts === []) {
            $this->error('✗ Connected, but the listing came back empty.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=green>✓</> Fetched '.count($posts).' posts from r/'.$sub.':');
        $this->newLine();

        foreach ($posts as $post) {
            $d = $post['data'];
            $this->line(sprintf(
                '  <fg=gray>%6s</>  %s',
                Reddit::formatScore((int) ($d['score'] ?? 0)),
                mb_strimwidth($d['title'] ?? '', 0, 68, '…')
            ));
        }

        return self::SUCCESS;
    }
}
