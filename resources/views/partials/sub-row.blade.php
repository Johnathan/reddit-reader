@php
    /**
     * A single subreddit row: name, members/description, subscribe toggle and
     * an "add to feed" menu. Props:
     *   $name        - subreddit display name
     *   $subscribed  - bool
     *   $members     - int|null subscriber count
     *   $blurb       - string|null short description
     *   $seed        - string|null "because you follow r/$seed"
     */
    $name = $name ?? '';
    $subscribed = $subscribed ?? false;
    $members = $members ?? null;
    $blurb = $blurb ?? null;
    $seed = $seed ?? null;
@endphp

<div data-unfold-card class="bg-neutral-900 rounded-lg">
<div class="flex items-center justify-between gap-2 px-3 py-2">
    <div class="min-w-0">
        <a href="{{ route('subreddit.show', strtolower($name)) }}" class="font-medium hover:text-orange-300">r/{{ $name }}</a>
        <div class="text-xs text-neutral-500 truncate">
            @if (! is_null($members))
                {{ \App\Services\Reddit::formatScore((int) $members) }} members
            @endif
            @if ($blurb)
                &middot; {{ \Illuminate\Support\Str::limit($blurb, 60) }}
            @endif
            @if ($seed)
                <span class="text-neutral-600">&middot; because you follow r/{{ $seed }}</span>
            @endif
        </div>
    </div>

    <div class="flex items-center gap-1.5 shrink-0">
        @if ($myFeeds->isNotEmpty())
            <details class="relative">
                <summary class="list-none cursor-pointer text-xs px-2.5 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-neutral-700 select-none">+ feed</summary>
                <div class="absolute right-0 mt-1 z-20 w-44 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl py-1">
                    @foreach ($myFeeds as $feed)
                        <form method="POST" action="{{ route('feeds.subs.store', $feed) }}" data-ajax data-turbo="false">
                            @csrf
                            <input type="hidden" name="subreddit" value="{{ $name }}">
                            <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-neutral-200 hover:bg-neutral-800 truncate">{{ $feed->name }}</button>
                        </form>
                    @endforeach
                    <a href="{{ route('feeds.index') }}" class="block px-3 py-1.5 text-xs text-neutral-500 hover:bg-neutral-800 border-t border-neutral-800 mt-1">New feed&hellip;</a>
                </div>
            </details>
        @endif

        @include('partials.subscribe-button', ['subreddit' => $name, 'subscribed' => $subscribed, 'subscribedLabel' => 'Subscribed'])

        <button type="button" data-unfold aria-expanded="false"
                data-url="{{ route('subreddit.preview', strtolower($name)) }}"
                aria-label="Preview r/{{ $name }}"
                class="p-1 text-neutral-500 hover:text-orange-500">
            <svg viewBox="0 0 24 24" class="w-4 h-4 transition-transform duration-150" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </button>
    </div>
</div>

<div data-unfold-panel hidden class="border-t border-neutral-800 px-3 py-2"></div>
</div>
