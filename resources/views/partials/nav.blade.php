<nav class="sticky top-0 z-40 bg-neutral-950/90 backdrop-blur border-b border-neutral-800"
     style="padding-top: env(safe-area-inset-top);">
    <div class="max-w-2xl mx-auto h-12 flex items-center justify-between gap-3"
         style="padding-left: max(0.75rem, env(safe-area-inset-left)); padding-right: max(0.75rem, env(safe-area-inset-right));">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('home') }}" class="font-semibold text-orange-400 shrink-0">reader</a>
            @hasSection('subnav-title')
                <span class="text-neutral-600 shrink-0">/</span>
                <span class="text-neutral-400 truncate text-sm">@yield('subnav-title')</span>
            @endif
        </div>

        @php $navActive = 'text-orange-500 font-medium'; $navIdle = 'text-neutral-300 hover:text-neutral-900'; @endphp
        <div class="flex items-center gap-4 text-sm shrink-0">
            <a href="{{ route('explore') }}" class="{{ request()->routeIs('explore') ? $navActive : $navIdle }}">explore</a>
            <a href="{{ route('feeds.index') }}" class="{{ request()->routeIs('feeds.*') ? $navActive : $navIdle }}">feeds</a>
            <a href="{{ route('subs.index') }}" class="{{ request()->routeIs('subs.*') ? $navActive : $navIdle }}">subs</a>
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}" data-turbo="false">
                @csrf
                <button type="submit" class="text-neutral-400 hover:text-neutral-900">logout</button>
            </form>
        </div>
    </div>
</nav>
