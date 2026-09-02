@php
    /**
     * The floating "sticky note": a pinned pad that sits above the tab bar,
     * counts what you've stacked up, and flips open so you can peek at (or
     * work through) the pile without leaving the feed.
     */
    $items = $myReadingList ?? collect();
    $unread = $items->whereNull('read_at')->count();
@endphp

<div id="reading-dock" data-count="{{ $items->count() }}"
     class="fixed right-3 z-40 flex flex-col items-end gap-2 {{ $items->isEmpty() ? 'hidden' : '' }}"
     style="bottom: calc(var(--tabbar-h) + env(safe-area-inset-bottom) + 0.75rem);
            padding-right: env(safe-area-inset-right);">

    {{-- The pad itself, hidden until tapped. --}}
    <div data-dock-panel hidden
         class="w-72 max-w-[calc(100vw-1.5rem)] rounded-xl border border-neutral-700 bg-neutral-900 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 border-b border-neutral-800">
            <span class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Reading list</span>
            <a href="{{ route('reading.index') }}" class="text-xs text-orange-400 hover:underline">Open all &rarr;</a>
        </div>

        <div data-dock-items class="max-h-72 overflow-y-auto overscroll-contain divide-y divide-neutral-800">
            @foreach ($items as $item)
                @include('partials.reading-dock-row', ['item' => $item])
            @endforeach
        </div>

        <p data-dock-empty class="px-3 py-4 text-xs text-neutral-500 text-center {{ $items->isNotEmpty() ? 'hidden' : '' }}">
            Nothing saved yet.
        </p>
    </div>

    {{-- The pinned tab you actually tap. --}}
    <button type="button" data-dock-toggle aria-expanded="false" aria-label="Reading list"
            class="relative flex items-center gap-2 pl-3 pr-3.5 py-2 rounded-full bg-neutral-900 border border-neutral-700 shadow-lg text-neutral-200 hover:border-orange-500 active:opacity-70">
        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 3h12a1 1 0 0 1 1 1v16l-7-4-7 4V4a1 1 0 0 1 1-1z"/>
        </svg>
        <span data-dock-count class="text-sm font-semibold tabular-nums">{{ $items->count() }}</span>
        <span data-dock-unread
              class="absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] px-1 grid place-items-center rounded-full bg-orange-500 text-white text-[10px] font-bold {{ $unread === 0 ? 'hidden' : '' }}">{{ $unread }}</span>
    </button>
</div>
