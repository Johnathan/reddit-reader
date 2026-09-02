<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geo-blocked media proxy
    |--------------------------------------------------------------------------
    |
    | Imgur withdrew from the UK rather than comply with the Online Safety Act,
    | so a UK browser hitting i.imgur.com gets a block page.
    |
    | The default rewrites imgur URLs to DuckDuckGo's image proxy — byte for
    | byte what the "Imgur Unblocker" Chrome extension does:
    |
    |     regexSubstitution: "https://external-content.duckduckgo.com/iu/?u=\0.png"
    |
    | ...including appending ".png" to the whole original URL, which is what
    | makes DDG's proxy treat it as an image. DDG fetches imgur, so it works no
    | matter where this server (or you) sits.
    |
    | Placeholders: {url} is the raw original, {url_encoded} the URL-encoded
    | one. The extension uses the raw form, so that is the default.
    |
    | Alternatives:
    |     MEDIA_PROXY_TEMPLATE="https://imgup.uk/proxy/?url={url_encoded}"
    |     MEDIA_PROXY_TEMPLATE="self"   -> fetch via this app's own /img route
    |                                      instead (only works if THIS server
    |                                      is outside the UK)
    |
    | The host allowlist deliberately lives in code (App\Services\ImageProxy),
    | not here — widening it via an env var is how a media proxy turns into an
    | SSRF gadget.
    |
    */

    'proxy_template' => env(
        'MEDIA_PROXY_TEMPLATE',
        'https://external-content.duckduckgo.com/iu/?u={url}.png',
    ),

];
