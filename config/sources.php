<?php

/*
|--------------------------------------------------------------------------
| Feed sources
|--------------------------------------------------------------------------
|
| Everything that feeds the unified home timeline besides Reddit and Hacker
| News. Each RSS source is one entry here — adding a source tomorrow is
| adding an array element, not writing code.
|
|   key     stable id; stored on reading-list/archive rows, used in URLs
|   name    what the card's source chip shows
|   feed    the RSS/Atom URL
|   hosts   hosts the article extractor may fetch for this source. This is
|           the SSRF guard: /article will only ever fetch pages whose host
|           matches a configured source. Keep it tight.
|
*/

return [

    'rss' => [
        'bbcni' => [
            'name' => 'BBC NI',
            'feed' => 'https://feeds.bbci.co.uk/news/northern_ireland/rss.xml',
            'hosts' => ['bbc.co.uk', 'bbc.com', 'bbci.co.uk'],
        ],
    ],

];
