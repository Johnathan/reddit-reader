<?php

/*
|--------------------------------------------------------------------------
| Primary navigation
|--------------------------------------------------------------------------
|
| Single source of truth for the app's four top-level destinations: rendered
| as the bottom tab bar on mobile and the left sidebar on desktop, so the two
| cannot drift apart.
|
|   route   where the item links
|   active  routes that should light this item up
|   when    path regex used to re-highlight the tab bar client-side (it is
|           data-turbo-permanent, so it never re-renders during navigation)
|   icon    inner markup for a 24x24 stroked svg
|
*/

return [
    [
        'label' => 'Home',
        'route' => 'home',
        'active' => ['home'],
        'when' => '^/$',
        'icon' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
    ],
    [
        'label' => 'Explore',
        'route' => 'explore',
        'active' => ['explore'],
        'when' => '^/explore',
        'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
    ],
    [
        'label' => 'Feeds',
        'route' => 'feeds.index',
        'active' => ['feeds.index', 'feeds.show'],
        'when' => '^/(feeds|f/)',
        'icon' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
    ],
    [
        'label' => 'Saved',
        'route' => 'saved.index',
        'active' => ['saved.index'],
        'when' => '^/saved',
        'icon' => '<path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/>',
    ],
    [
        'label' => 'Subs',
        'route' => 'subs.index',
        'active' => ['subs.index'],
        'when' => '^/subs',
        'icon' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/>',
    ],
];
