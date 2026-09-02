@php
    // data-turbo-permanent: the tab bar is never re-rendered during navigation
    // (no flicker); its active state is updated client-side on turbo:load.
    // Hidden on desktop, where partials.sidebar takes over the same config.
    $icon = fn ($paths) => '<svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
@endphp
<nav id="app-tabbar" data-turbo-permanent class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-neutral-950/90 backdrop-blur border-t border-neutral-800"
     style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="max-w-2xl mx-auto flex items-stretch" style="height: var(--tabbar-h);
             padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right);">
        @foreach (config('nav') as $tab)
            <a href="{{ route($tab['route']) }}" data-tab data-active-when="{{ $tab['when'] }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium active:opacity-60 {{ request()->routeIs(...$tab['active']) ? 'text-orange-500' : 'text-neutral-400' }}">
                {!! $icon($tab['icon']) !!}
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
