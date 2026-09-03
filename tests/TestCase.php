<?php

namespace Tests;

use App\Services\HackerNews;
use App\Services\RssSource;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The home timeline now pulls HN and RSS as well as Reddit. Tests must
     * never make live network calls, so both extra sources default to inert
     * stubs; a test that cares about them rebinds with $this->instance().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(HackerNews::class, new class extends HackerNews
        {
            public function listing(string $sort, ?string $cursor = null): array
            {
                return ['items' => [], 'cursor' => null];
            }

            public function thread(string $id): ?array
            {
                return null;
            }

            public function feedCommentCount(string $id): ?int
            {
                return null;
            }
        });

        $this->instance(RssSource::class, new class extends RssSource
        {
            public function listing(string $key, ?string $cursor = null): array
            {
                return ['items' => [], 'cursor' => null];
            }

            public function resolve(string $key, string $id): ?array
            {
                return null;
            }
        });
    }
}
