@php
    // Tab roots show the wordmark; drilled-in pages show a back chevron.
    $isTabRoot = request()->routeIs('home', 'explore', 'feeds.index', 'subs.index');
@endphp
<header id="app-topbar" class="fixed top-0 inset-x-0 z-40 bg-neutral-950/85 backdrop-blur border-b border-neutral-800"
        style="padding-top: env(safe-area-inset-top);">
    <div class="topbar-inner relative flex items-center" style="height: var(--topbar-h);
             padding-left: max(0.75rem, env(safe-area-inset-left)); padding-right: max(0.75rem, env(safe-area-inset-right));">
        {{-- Desktop always shows the wordmark: the sidebar carries navigation,
             so the back chevron is a phone-only affordance. --}}
        <div class="topbar-rail flex items-center min-w-0">
            @unless ($isTabRoot)
                <button type="button" onclick="history.back()" aria-label="Back" class="lg:hidden -ml-1.5 p-1 text-orange-500 active:opacity-60">
                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
            @endunless
            <a href="{{ route('home') }}"
               class="{{ $isTabRoot ? 'flex' : 'hidden lg:flex' }} font-semibold text-orange-500 text-lg tracking-tight leading-none">r<span class="text-neutral-500">.</span></a>
        </div>

        <div class="topbar-title absolute left-1/2 -translate-x-1/2 max-w-[62%] truncate text-center text-sm font-semibold text-neutral-200">
            @yield('subnav-title')
        </div>

        {{-- Room for a search box only exists on desktop. --}}
        <form method="GET" action="{{ route('subs.index') }}" class="hidden lg:block ml-auto w-56">
            <input type="text" name="q" placeholder="Search subreddits&hellip;" data-sub-autocomplete="navigate"
                   class="w-full bg-neutral-900 border border-neutral-700 rounded-full px-3 py-1 text-sm placeholder-neutral-500 focus:outline-none focus:border-orange-500">
        </form>
    </div>
</header>
