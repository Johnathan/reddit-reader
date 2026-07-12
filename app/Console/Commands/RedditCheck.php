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
    protected $signature = 'reddit:check {subreddit=programming} {--fresh : Discard cached cookies and re-solve the challenge from scratch}';

    protected $description = 'Verify Reddit API connectivity and print a sample listing';

    public function handle(Reddit $reddit): int
    {
        $sub = $this->argument('subreddit');

        $hasCreds = config('services.reddit.client_id') && config('services.reddit.client_secret');

        $this->line($hasCreds
            ? '<fg=green>✓</> Credentials found — using OAuth (oauth.reddit.com)'
            : '<fg=cyan>·</> No credentials (not needed) — using public .json + solving Reddit’s JS challenge');

        Cache::forget('reddit:oauth:token');

        if ($this->option('fresh')) {
            @unlink(storage_path('app/reddit-cookies.json'));
            Cache::flush();
            $this->line('<fg=cyan>·</> Cleared cached cookies — forcing a full challenge solve');
        }

        try {
            $listing = $reddit->listing($sub, 'hot', ['limit' => 5]);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('✗ '.$e->getMessage());

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
