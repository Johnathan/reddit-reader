<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when Reddit's public JSON API can't be reached or returns an
 * error status (rate limited, private/banned subreddit, timeout, etc).
 * Controllers should catch this and render a friendly error rather than
 * letting it bubble up to a 500 page.
 */
class RedditException extends Exception
{
    public static function rateLimited(): self
    {
        return new self('Reddit is rate-limiting us right now. Please try again in a minute.');
    }

    public static function forbidden(): self
    {
        return new self('Reddit refused this request (the subreddit may be private, quarantined, or banned).');
    }

    public static function notFound(): self
    {
        return new self('That could not be found on Reddit.');
    }

    public static function timedOut(): self
    {
        return new self('Reddit took too long to respond. Please try again.');
    }

    public static function unexpected(?int $status = null): self
    {
        return new self('Reddit returned an unexpected response'.($status ? " (HTTP {$status})" : '').'.');
    }
}
