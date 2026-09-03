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
        // Normal form posts should redirect back with their errors, not get
        // JSON — which expectsJson() already handles: every fetch() caller in
        // the app sends Accept: application/json, browsers' form posts don't.
        // (Overriding this hook REPLACES that default, so it must be restated
        // — leaving it out once sent /seen validation failures off as 302s.)
        // The reading-list/saved mutations are pinned as JSON by path as well,
        // belt-and-braces, since their callers can't use a redirect at all;
        // their GETs are normal pages and still redirect a logged-out visitor
        // to the login screen.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->is('api/*')
                || ($request->is('reading-list', 'reading-list/*') && ! $request->isMethod('GET'))
                || ($request->is('saved', 'saved/*') && ! $request->isMethod('GET') && ! $request->is('saved/folders*')),
        );
    })->create();
