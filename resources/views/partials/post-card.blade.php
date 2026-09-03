@php
    $p = $postData;
    $src = $p['_source'] ?? 'reddit';

    // Where the card opens: the in-app thread (reddit/HN) or the in-app
    // article reader (RSS). The card NEVER links out directly.
    $internalUrl = match ($src) {
        'hn' => route('hn.item', $p['id']),
        'rss' => route('article.show', ['source' => $p['source_key'], 'id' => $p['id']]),
        default => route('posts.show', [
            'subreddit' => $p['subreddit'],
            'id' => $p['id'],
            'slug' => \Illuminate\Support\Str::slug($p['title'] ?? ''),
        ]),
    };

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

    $previewImage = \App\Services\ImageProxy::wrap($previewImage);

    $isNsfw = $p['over_18'] ?? false;
    $hideSubreddit = $hideSubreddit ?? false;

    // RES-style inline expando. The body text is already in the payload so it
    // renders server-side, but media only rides along as data — the <img>,
    // <video> and embed markup are built on first open. A hidden iframe still
    // loads and a hidden <img> still downloads, so building them up front
    // would fetch every image and phone home to YouTube for all 25 cards.
    $expando = \App\Services\Reddit::expando($p);

    $expandMedia = [
        'images' => $expando['images'],
        'video' => $expando['video'],
        'embed' => $expando['embed'],
    ];

    $expandParts = [];
    if ($expando['text'] !== '') {
        $expandParts[] = 'text';
    }
    if ($expando['video']) {
        $expandParts[] = 'video';
    }
    if ($expando['embed'] !== '') {
        $expandParts[] = 'embed';
    }
    if ($expando['images'] !== []) {
        $expandParts[] = count($expando['images']) > 1 ? count($expando['images']).' images' : 'image';
    }

    // RSS items carry only a headline — their expando is the extracted full
    // article, fetched into the panel on first open.
    $articleFetchUrl = null;
    if ($src === 'rss') {
        $articleFetchUrl = route('article.body', ['source' => $p['source_key'], 'id' => $p['id']]);
        $expandParts = ['article'];
    }

    $isExpandable = $expandParts !== [];

    // Server-side read-tracking: dim posts the user has opened (on any
    // device) and badge threads that have grown comments since they last
    // looked. $seenMap comes from feed-list; surfaces without it (Explore's
    // sub previews) just render unread.
    $seenKey = \App\Models\SeenItem::keyFor($p);
    $seenRec = ($seenMap ?? collect())[$seenKey] ?? null;
    $isRead = $seenRec !== null;

    $newComments = 0;
    if ($seenRec?->comments_seen !== null && ($p['num_comments'] ?? null) !== null) {
        $newComments = max(0, $p['num_comments'] - $seenRec->comments_seen);
    }
@endphp

<div data-swipe-wrap>
    {{-- Revealed behind the card as it slides. aria-hidden: the save button is
         the accessible control, this is decoration. --}}
    <div data-swipe-bg aria-hidden="true" class="bg-orange-500/10 text-orange-500">
        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">
            <path d="M6 3h12a1 1 0 0 1 1 1v16l-7-4-7 4V4a1 1 0 0 1 1-1z"/>
        </svg>
    </div>

<article data-swipe data-post-id="{{ $p['id'] }}" data-seen="{{ $seenKey }}" class="border-b border-neutral-800 py-3">
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
                @if ($src === 'hn')
                    <span class="px-1.5 py-0.5 rounded bg-[#f60]/15 text-[#c50] font-semibold text-[10px]">HN</span>
                    <span>&middot;</span>
                @elseif ($src === 'rss')
                    <span class="px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300 font-semibold text-[10px]">{{ $p['source_name'] }}</span>
                    <span>&middot;</span>
                @elseif (! $hideSubreddit)
                    <a href="{{ route('subreddit.show', $p['subreddit']) }}" class="text-neutral-300 hover:text-neutral-900 font-medium">r/{{ $p['subreddit'] }}</a>
                    <span>&middot;</span>
                @endif
                @if ($p['author'] ?? null)
                    <span>u/{{ $p['author'] }}</span>
                    <span>&middot;</span>
                @endif
                <span>{{ isset($p['created_utc']) ? \Carbon\Carbon::createFromTimestamp($p['created_utc'])->diffForHumans() : '' }}</span>
                @if ($p['stickied'] ?? false)
                    <span class="text-emerald-400 font-medium">pinned</span>
                @endif
            </div>

            <a href="{{ $internalUrl }}" data-mark-read class="font-medium leading-snug hover:text-orange-300 {{ $isRead ? 'post-read' : '' }}">
                {{ $p['title'] }}
            </a>
            @if (! empty($p['summary']))
                <div class="text-xs text-neutral-500 mt-0.5 line-clamp-2">{{ $p['summary'] }}</div>
            @elseif (!($p['is_self'] ?? false) && !empty($p['domain']) && !str_starts_with($p['domain'], 'self.'))
                <div class="text-xs text-neutral-500 mt-0.5">{{ $p['domain'] }}</div>
            @endif

            <div class="flex items-center gap-3 mt-2 text-xs text-neutral-400">
                {{-- RSS items have no score or discussion — null, not zero. --}}
                @if (($p['score'] ?? null) !== null)
                    <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score']) }}</span>
                @endif
                @if (($p['num_comments'] ?? null) !== null)
                    <a href="{{ $internalUrl }}" data-mark-read class="hover:text-neutral-900 {{ $isRead ? 'post-read' : '' }}">&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments']) }} comments</a>
                    @if ($newComments > 0)
                        {{-- RES-style: comments that arrived since you last opened this post. --}}
                        <span class="text-[11px] font-semibold text-emerald-400">+{{ $newComments }} new</span>
                    @endif
                @endif

                @if ($isExpandable)
                    <button type="button" data-expando aria-expanded="false"
                            @if ($articleFetchUrl) data-fetch="{{ $articleFetchUrl }}"
                            @else data-media="{{ json_encode($expandMedia, JSON_UNESCAPED_SLASHES) }}" @endif
                            data-nsfw="{{ $isNsfw ? '1' : '0' }}"
                            class="flex items-center gap-1 hover:text-orange-500">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8" data-expando-plus/>
                        </svg>
                        <span>{{ implode(' + ', $expandParts) }}</span>
                    </button>
                @endif

                {{-- Last in the row: enlarged hit areas then overhang empty
                     space rather than stealing taps from the controls above. --}}
                @include('partials.read-later-button', ['p' => $p, 'saved' => isset($myReadingListIds[$p['id']])])

                @include('partials.save-button', [
                    'kind' => 'post', 'redditId' => $p['id'],
                    'subreddit' => $p['subreddit'] ?? ($src === 'hn' ? 'hackernews' : $p['source_key'] ?? ''),
                    'source' => $src === 'rss' ? $p['source_key'] : $src,
                    'itemUrl' => $src === 'rss' ? $p['url'] : null,
                    'postId' => null, 'saved' => isset($myArchivedIds[$p['id']]),
                ])
            </div>
        </div>
    </div>

    @if ($isExpandable)
        <div data-expando-panel hidden class="mt-2">
            @if ($expando['text'] !== '')
                <div class="reddit-html text-[15px] leading-relaxed text-neutral-200 mb-2">
                    {!! $expando['text'] !!}
                </div>
            @endif
        </div>
    @endif
</article>
</div>
