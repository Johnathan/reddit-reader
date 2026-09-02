<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Served through a Cloudflare Tunnel, which terminates TLS and forwards
        // over plain HTTP. Without this Laravel would think the request is http,
        // generate http:// URLs, and WebAuthn would reject the origin mismatch.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Deliberately narrow: normal form posts should redirect back with
        // their errors, not get JSON. The reading-list endpoints are the
        // exception — they are only ever called by fetch(), so a validation
        // failure must come back as 422 JSON rather than a 302 to a page the
        // caller cannot use.
        // ...but not the GET, which is a normal page and should still redirect
        // a logged-out visitor to the login screen.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('reading-list', 'reading-list/*') && ! $request->isMethod('GET'))
                || ($request->is('saved', 'saved/*') && ! $request->isMethod('GET') && ! $request->is('saved/folders*')),
        );
    })->create();
