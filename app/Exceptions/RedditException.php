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

    /**
     * Reddit blocks anonymous access to its JSON endpoints, so a 403 without
     * configured OAuth credentials almost always means "no credentials" rather
     * than "private subreddit". Say so, since the fix is entirely different.
     */
    public static function forbidden(): self
    {
        if (! config('services.reddit.client_id') || ! config('services.reddit.client_secret')) {
            return new self('Reddit is blocking anonymous API access. Add REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET to .env — see the README.');
        }

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
