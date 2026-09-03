<?php

namespace App\Exceptions;

/**
 * A server-side fetch was redirected to a host outside its allowlist.
 * Thrown from the redirect callback so the transfer stops before the
 * disallowed request is ever made.
 */
class DisallowedRedirect extends \RuntimeException
{
    public function __construct(string $target)
    {
        parent::__construct("Redirected to a disallowed URL: {$target}");
    }
}
