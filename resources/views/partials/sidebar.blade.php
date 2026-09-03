@php
    // Desktop only: `hidden lg:block` means this never renders on mobile, where
    // the bottom tab bar remains the only navigation.
    $icon = fn ($paths) => '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';

    $rowBase = 'flex items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm truncate';
    $rowIdle = 'text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200';
    $rowOn = 'bg-neutral-900 text-orange-500 font-medium';

    $heading = 'px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-500';
@endphp

<aside id="app-sidebar" class="hidden lg:block shrink-0" style="width: var(--sidebar-w);">
    <nav class="sidebar-sticky overflow-y-auto overscroll-contain pb-6 pr-1">
        @foreach (config('nav') as $item)
            <a href="{{ route($item['route']) }}"
               class="{{ $rowBase }} {{ request()->routeIs(...$item['active']) ? $rowOn : $rowIdle }}">
                {!! $icon($item['icon']) !!}
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        {{-- Not in config/nav.php on purpose: the mobile tab bar is full at
             five, and on phones the floating dock plus the Saved page's
             cross-link cover this. --}}
        @php $readingUnread = ($myReadingList ?? collect())->whereNull('read_at')->count(); @endphp
        <a href="{{ route('reading.index') }}"
           class="{{ $rowBase }} {{ request()->routeIs('reading.index') ? $rowOn : $rowIdle }}">
            <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3h12a1 1 0 0 1 1 1v16l-7-4-7 4V4a1 1 0 0 1 1-1z"/>
            </svg>
            <span>Reading list</span>
            @if ($readingUnread)
                <span class="ml-auto min-w-[1.25rem] px-1.5 py-0.5 rounded-full bg-orange-500 text-white text-[10px] font-bold text-center">{{ $readingUnread }}</span>
            @endif
        </a>

        <div class="mt-4 border-t border-neutral-800"></div>

        <div class="{{ $heading }} flex items-center justify-between">
            <span>Feeds</span>
            <a href="{{ route('feeds.index') }}" class="normal-case tracking-normal text-neutral-600 hover:text-orange-500">manage</a>
        </div>
        @forelse ($myFeeds as $feed)
            <a href="{{ route('feeds.show', $feed->slug) }}"
               class="{{ $rowBase }} {{ request()->routeIs('feeds.show') && request()->route('slug') === $feed->slug ? $rowOn : $rowIdle }}">
                <span class="w-5 h-5 shrink-0 grid place-items-center rounded-md bg-neutral-800 text-[10px] font-semibold text-neutral-400 uppercase">
                    {{ mb_substr($feed->name, 0, 1) }}
                </span>
                <span class="truncate">{{ $feed->name }}</span>
            </a>
        @empty
            <p class="px-3 py-1 text-xs text-neutral-600">No feeds yet.</p>
        @endforelse

        <div class="mt-4 border-t border-neutral-800"></div>

        <div class="{{ $heading }} flex items-center justify-between">
            <span>Communities</span>
            <a href="{{ route('subs.index') }}" class="normal-case tracking-normal text-neutral-600 hover:text-orange-500">manage</a>
        </div>
        @forelse ($mySubs as $sub)
            <a href="{{ route('subreddit.show', $sub->subreddit) }}"
               class="{{ $rowBase }} {{ request()->routeIs('subreddit.show') && strtolower((string) request()->route('subreddit')) === $sub->subreddit ? $rowOn : $rowIdle }}">
                <span class="w-5 h-5 shrink-0 grid place-items-center rounded-full bg-neutral-800 text-[10px] font-semibold text-neutral-400 uppercase">
                    {{ mb_substr($sub->subreddit, 0, 1) }}
                </span>
                <span class="truncate">r/{{ $sub->subreddit }}</span>
            </a>
        @empty
            <a href="{{ route('explore') }}" class="{{ $rowBase }} {{ $rowIdle }}">Find subreddits &rarr;</a>
        @endforelse
    </nav>
</aside>
