<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The feed is private: a guest is sent to log in with their passkey.
     */
    public function test_the_home_feed_requires_authentication(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
