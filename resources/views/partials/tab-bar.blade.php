@php
    // data-turbo-permanent: the tab bar is never re-rendered during navigation
    // (no flicker); its active state is updated client-side on turbo:load.
    $icon = fn ($paths) => '<svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $tabs = [
        ['label' => 'Home',    'route' => 'home',        'when' => '^/$',            'icon' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'],
        ['label' => 'Explore', 'route' => 'explore',     'when' => '^/explore',      'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>'],
        ['label' => 'Feeds',   'route' => 'feeds.index', 'when' => '^/(feeds|f/)',   'icon' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>'],
        ['label' => 'Subs',    'route' => 'subs.index',  'when' => '^/subs',         'icon' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/>'],
    ];
@endphp
<nav id="app-tabbar" data-turbo-permanent class="fixed bottom-0 inset-x-0 z-40 bg-neutral-950/90 backdrop-blur border-t border-neutral-800"
     style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="max-w-2xl mx-auto flex items-stretch" style="height: var(--tabbar-h);
             padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right);">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" data-tab data-active-when="{{ $tab['when'] }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium active:opacity-60 {{ request()->routeIs($tab['route']) || ($tab['route'] === 'feeds.index' && request()->routeIs('feeds.show')) ? 'text-orange-500' : 'text-neutral-400' }}">
                {!! $icon($tab['icon']) !!}
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
