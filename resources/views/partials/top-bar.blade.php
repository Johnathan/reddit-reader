@php
    // Tab roots show the wordmark; drilled-in pages show a back chevron.
    $isTabRoot = request()->routeIs('home', 'explore', 'feeds.index', 'subs.index');
@endphp
<header id="app-topbar" class="fixed top-0 inset-x-0 z-40 bg-neutral-950/85 backdrop-blur border-b border-neutral-800"
        style="padding-top: env(safe-area-inset-top);">
    <div class="relative max-w-2xl mx-auto flex items-center" style="height: var(--topbar-h);
             padding-left: max(0.75rem, env(safe-area-inset-left)); padding-right: max(0.75rem, env(safe-area-inset-right));">
        <div class="flex items-center min-w-0">
            @if ($isTabRoot)
                <a href="{{ route('home') }}" class="font-semibold text-orange-500 text-lg tracking-tight leading-none">r<span class="text-neutral-500">.</span></a>
            @else
                <button type="button" onclick="history.back()" aria-label="Back" class="-ml-1.5 p-1 text-orange-500 active:opacity-60">
                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
            @endif
        </div>
        <div class="absolute left-1/2 -translate-x-1/2 max-w-[62%] truncate text-center text-sm font-semibold text-neutral-200">
            @yield('subnav-title')
        </div>
    </div>
</header>
