@php
    $p = $postData;
    $internalUrl = route('posts.show', [
        'subreddit' => $p['subreddit'],
        'id' => $p['id'],
        'slug' => \Illuminate\Support\Str::slug($p['title'] ?? ''),
    ]);

    // The card NEVER links out — every tap opens the in-app post page. Any link
    // to Reddit or the original source lives on that page, behind a menu.

    $previewImage = null;
    if (!empty($p['preview']['images'][0])) {
        $img = $p['preview']['images'][0];
        $resolutions = $img['resolutions'] ?? [];
        $chosen = collect($resolutions)->first(fn ($r) => ($r['width'] ?? 0) >= 400);
        if (!$chosen) {
            $chosen = !empty($resolutions) ? end($resolutions) : ($img['source'] ?? null);
        }
        if ($chosen) {
            $previewImage = html_entity_decode($chosen['url']);
        }
    } elseif (!empty($p['thumbnail']) && str_starts_with($p['thumbnail'], 'http')) {
        $previewImage = $p['thumbnail'];
    }

    $isNsfw = $p['over_18'] ?? false;
    $hideSubreddit = $hideSubreddit ?? false;
@endphp

<article class="border-b border-neutral-800 py-3">
    <div class="flex gap-3">
        @if ($previewImage)
            <div class="shrink-0 relative w-20 h-20 rounded-lg overflow-hidden bg-neutral-800">
                <a href="{{ $internalUrl }}" class="block w-full h-full">
                    <img src="{{ $previewImage }}" loading="lazy"
                         class="w-full h-full object-cover {{ $isNsfw ? 'blur-md nsfw-img' : '' }}"
                         @if ($isNsfw) onclick="event.preventDefault(); this.classList.toggle('blur-md')" @endif>
                    @if ($isNsfw)
                        <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-red-300 bg-black/40 pointer-events-none">NSFW</span>
                    @endif
                </a>
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 text-xs text-neutral-500 mb-1 flex-wrap">
                @unless ($hideSubreddit)
                    <a href="{{ route('subreddit.show', $p['subreddit']) }}" class="text-neutral-300 hover:text-white font-medium">r/{{ $p['subreddit'] }}</a>
                    <span>&middot;</span>
                @endunless
                <span>u/{{ $p['author'] ?? '[deleted]' }}</span>
                <span>&middot;</span>
                <span>{{ \Carbon\Carbon::createFromTimestamp($p['created_utc'])->diffForHumans() }}</span>
                @if ($p['stickied'] ?? false)
                    <span class="text-emerald-400 font-medium">pinned</span>
                @endif
            </div>

            <a href="{{ $internalUrl }}" class="font-medium leading-snug hover:text-orange-300">
                {{ $p['title'] }}
            </a>
            @if (!($p['is_self'] ?? false) && !empty($p['domain']) && !str_starts_with($p['domain'], 'self.'))
                <div class="text-xs text-neutral-500 mt-0.5">{{ $p['domain'] }}</div>
            @endif

            <div class="flex items-center gap-3 mt-2 text-xs text-neutral-400">
                <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}</span>
                <a href="{{ $internalUrl }}" class="hover:text-white">&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }} comments</a>
            </div>
        </div>
    </div>
</article>
