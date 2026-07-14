<nav class="sticky top-0 z-40 bg-neutral-950/90 backdrop-blur border-b border-neutral-800">
    <div class="max-w-2xl mx-auto px-3 h-12 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('home') }}" class="font-semibold text-orange-400 shrink-0">reader</a>
            @hasSection('subnav-title')
                <span class="text-neutral-600 shrink-0">/</span>
                <span class="text-neutral-400 truncate text-sm">@yield('subnav-title')</span>
            @endif
        </div>

        <div class="flex items-center gap-4 text-sm shrink-0">
            <a href="{{ route('explore') }}" class="text-neutral-300 hover:text-white">explore</a>
            <a href="{{ route('feeds.index') }}" class="text-neutral-300 hover:text-white">feeds</a>
            <a href="{{ route('subs.index') }}" class="text-neutral-300 hover:text-white">subs</a>
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
                @csrf
                <button type="submit" class="text-neutral-400 hover:text-white">logout</button>
            </form>
        </div>
    </div>
</nav>
