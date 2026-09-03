@php
    /**
     * The full post — body/media then its threaded comments. Shared by the
     * post page and the reading-list reader so media handling (imgur proxy,
     * galleries, video) and the comment tree never drift between them. Props:
     *   $p             - the post's data payload
     *   $comments      - listing children for the comment tree
     *   $focusComment  - id when drilled into one subtree (optional)
     *   $showActions   - render the read-later / save buttons (default true)
     *   $publicView    - a logged-out visitor on a shared link: read-only,
     *                    and nothing may link into the rest of the app
     */
    $publicView = $publicView ?? false;
    $showActions = ($showActions ?? true) && ! $publicView;

    $isSelf = $p['is_self'] ?? false;
    $permalink = 'https://www.reddit.com'.($p['permalink'] ?? '');
    $distinguished = $p['distinguished'] ?? null;
    $isNsfw = $p['over_18'] ?? false;
    $nsfwClass = $isNsfw ? 'blur-md nsfw-img' : '';
    $nsfwToggle = $isNsfw ? "this.classList.toggle('blur-md')" : '';

    $outUrl = !empty($p['url']) ? html_entity_decode($p['url']) : null;
    $isRedditHosted = $outUrl && preg_match('~^https?://([a-z]+\.)?redd(it\.com|\.it)~i', $outUrl);
    $showOpenOriginal = $outUrl && ! $isSelf && ! $isRedditHosted;

    // In-app media, in priority order. Nothing here links out.
    $galleryImgs = [];
    if (($p['is_gallery'] ?? false) && !empty($p['gallery_data']['items']) && !empty($p['media_metadata'])) {
        foreach ($p['gallery_data']['items'] as $it) {
            $m = $p['media_metadata'][$it['media_id'] ?? ''] ?? null;
            $u = $m['s']['u'] ?? $m['s']['gif'] ?? null;
            if ($u) { $galleryImgs[] = html_entity_decode($u); }
        }
    }
    $videoUrl = $p['media']['reddit_video']['fallback_url']
        ?? $p['preview']['reddit_video_preview']['fallback_url'] ?? null;
    $videoUrl = $videoUrl ? html_entity_decode($videoUrl) : null;

    // The fallback mp4 is video-only (Reddit ships audio as a separate DASH
    // track); the HLS playlist carries both. JS upgrades the player to it.
    $videoHls = $p['media']['reddit_video']['hls_url']
        ?? $p['preview']['reddit_video_preview']['hls_url'] ?? null;
    $videoHls = $videoHls ? html_entity_decode($videoHls) : null;

    $imageUrl = null;
    $hint = $p['post_hint'] ?? null;
    $isImageExt = $outUrl && preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) parse_url($outUrl, PHP_URL_PATH));
    if ($hint === 'image' && $isImageExt) {
        $imageUrl = $outUrl;
    } elseif (!empty($p['preview']['images'][0]['source']['url'])) {
        $imageUrl = html_entity_decode($p['preview']['images'][0]['source']['url']);
    }

    // Imgur is geo-blocked in the UK — fetch those bytes server-side.
    $galleryImgs = \App\Services\ImageProxy::wrapAll($galleryImgs);
    $videoUrl = \App\Services\ImageProxy::wrap($videoUrl);
    $imageUrl = \App\Services\ImageProxy::wrap($imageUrl);
@endphp

@isset($focusComment)
    <a href="{{ route('posts.show', ['subreddit' => $p['subreddit'], 'id' => $p['id'], 'slug' => \Illuminate\Support\Str::slug($p['title'] ?? '')]) }}"
       class="inline-block mb-3 text-sm text-orange-400 hover:underline">&larr; Back to the full discussion</a>
@endisset

<article class="pb-4 border-b border-neutral-800">
    <div class="flex items-start justify-between gap-2">
        <div class="text-xs text-neutral-500 mb-1 flex items-center gap-1.5 flex-wrap">
            @if ($publicView)
                <span class="text-neutral-300 font-medium">r/{{ $p['subreddit'] }}</span>
            @else
                <a href="{{ route('subreddit.show', $p['subreddit']) }}" class="text-neutral-300 font-medium hover:text-neutral-100">r/{{ $p['subreddit'] }}</a>
            @endif
            <span>&middot;</span>
            <span>u/{{ $p['author'] ?? '[deleted]' }}</span>
            <span>&middot;</span>
            <span>{{ isset($p['created_utc']) ? \Carbon\Carbon::createFromTimestamp($p['created_utc'])->diffForHumans() : '' }}</span>
            @if ($p['stickied'] ?? false)
                <span class="text-emerald-400 font-medium">pinned</span>
            @endif
            @if ($distinguished)
                <span class="text-blue-400 font-medium uppercase text-[10px]">{{ $distinguished }}</span>
            @endif
        </div>

        {{-- The ONLY links that leave the app live in here, so a tap can't
             land you on Reddit by accident. --}}
        <details class="relative shrink-0">
            <summary class="list-none cursor-pointer px-2 -mt-1 text-lg leading-none text-neutral-500 hover:text-neutral-100 select-none">&#8943;</summary>
            <div class="absolute right-0 mt-1 z-20 w-52 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl py-1 text-sm">
                @if ($showOpenOriginal)
                    <a href="{{ $outUrl }}" target="_blank" rel="noopener" class="block px-3 py-2 hover:bg-neutral-800">Open original link &#8599;</a>
                @endif
                <a href="{{ $permalink }}" target="_blank" rel="noopener" class="block px-3 py-2 text-neutral-400 hover:bg-neutral-800">View on Reddit &#8599;</a>
            </div>
        </details>
    </div>

    <h1 class="text-lg font-semibold leading-snug mb-2">{{ $p['title'] }}</h1>

    @if ($isSelf)
        @if (!empty($p['selftext_html']))
            <div class="reddit-html text-[15px] leading-relaxed">
                {!! \App\Services\Reddit::sanitizeHtml($p['selftext_html']) !!}
            </div>
        @endif
    @elseif (!empty($galleryImgs))
        <div class="space-y-2">
            @foreach ($galleryImgs as $g)
                <img src="{{ $g }}" loading="lazy" alt="" class="w-full rounded-lg {{ $nsfwClass }}"
                     @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
            @endforeach
        </div>
    @elseif ($videoUrl)
        <video controls playsinline preload="metadata" class="w-full rounded-lg {{ $nsfwClass }}"
               @if ($videoHls) data-video-hls="{{ $videoHls }}" data-video-fallback="{{ $videoUrl }}" @endif
               @if ($imageUrl) poster="{{ $imageUrl }}" @endif
               @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
            <source src="{{ $videoUrl }}" type="video/mp4">
        </video>
    @elseif ($imageUrl)
        <img src="{{ $imageUrl }}" loading="lazy" alt="" class="w-full rounded-lg {{ $nsfwClass }}"
             @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
    @endif

    @if (! $isSelf && !empty($p['domain']) && !str_starts_with($p['domain'], 'self.'))
        <div class="text-xs text-neutral-500 mt-2">{{ $p['domain'] }}</div>
    @endif

    <div class="flex items-center gap-4 mt-3 text-sm text-neutral-400">
        <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}</span>
        <span>&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }}</span>
        @if ($showActions)
            @include('partials.read-later-button', ['p' => $p, 'saved' => isset($myReadingListIds[$p['id']]), 'label' => true])
            @include('partials.save-button', [
                'kind' => 'post', 'redditId' => $p['id'], 'subreddit' => $p['subreddit'],
                'postId' => null, 'saved' => isset($myArchivedIds[$p['id']]), 'label' => true,
            ])
            {{-- Post links work logged-out, so this hands out a viewable URL. --}}
            <button type="button" data-share
                    data-share-url="{{ route('posts.show', ['subreddit' => $p['subreddit'], 'id' => $p['id'], 'slug' => \Illuminate\Support\Str::slug($p['title'] ?? '')]) }}"
                    data-share-title="{{ \Illuminate\Support\Str::limit($p['title'] ?? '', 290) }}"
                    aria-label="Share a link to this post"
                    class="tap-target flex items-center gap-1 hover:text-orange-500">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <path d="M8.6 10.7l6.8-3.9M8.6 13.3l6.8 3.9"/>
                </svg>
                <span>Share</span>
            </button>
        @endif
    </div>
</article>

{{-- Comment sort — only where the page passes it (the post page; reader
     cards and shared links keep the default). Switching sort leaves a
     focused subtree: you're re-ordering the whole discussion. --}}
@isset($commentSort)
    @if (!empty($comments))
        <div class="flex items-center gap-1.5 text-xs mt-4 overflow-x-auto pb-1">
            <span class="text-neutral-500 shrink-0">Sorted by</span>
            @foreach (['best' => 'Best', 'top' => 'Top', 'new' => 'New', 'controversial' => 'Controversial', 'old' => 'Old', 'qa' => 'Q&A'] as $sKey => $sLabel)
                <a href="{{ route('posts.show', ['subreddit' => $p['subreddit'], 'id' => $p['id'], 'slug' => \Illuminate\Support\Str::slug($p['title'] ?? ''), 'sort' => $sKey]) }}"
                   class="px-2.5 py-1 rounded-full whitespace-nowrap {{ $commentSort === $sKey ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' }}">
                    {{ $sLabel }}
                </a>
            @endforeach
        </div>
    @endif
@endisset

<div class="mt-4">
    @forelse ($comments as $c)
        @include('partials.comment', [
            'comment' => $c,
            'depth' => 0,
            'postArgs' => ['subreddit' => $p['subreddit'], 'id' => $p['id'], 'slug' => \Illuminate\Support\Str::slug($p['title'] ?? '')],
        ])
    @empty
        <p class="text-neutral-500 text-sm py-6 text-center">No comments yet.</p>
    @endforelse
</div>
