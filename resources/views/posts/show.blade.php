@extends('layouts.app')

@section('subnav-title', 'r/'.$subreddit)

@section('content')
    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm">
            {{ $error }}
        </div>
    @else
        @php
            $p = $post;
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

            $imageUrl = null;
            $hint = $p['post_hint'] ?? null;
            $isImageExt = $outUrl && preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) parse_url($outUrl, PHP_URL_PATH));
            if ($hint === 'image' && $isImageExt) {
                $imageUrl = $outUrl;
            } elseif (!empty($p['preview']['images'][0]['source']['url'])) {
                $imageUrl = html_entity_decode($p['preview']['images'][0]['source']['url']);
            }
        @endphp

        @isset($focusComment)
            <a href="{{ route('posts.show', ['subreddit' => $p['subreddit'], 'id' => $p['id'], 'slug' => \Illuminate\Support\Str::slug($p['title'] ?? '')]) }}"
               class="inline-block mb-3 text-sm text-orange-400 hover:underline">&larr; Back to the full discussion</a>
        @endisset

        <article class="pb-4 border-b border-neutral-800">
            <div class="flex items-start justify-between gap-2">
                <div class="text-xs text-neutral-500 mb-1 flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('subreddit.show', $p['subreddit']) }}" class="text-neutral-300 font-medium hover:text-white">r/{{ $p['subreddit'] }}</a>
                    <span>&middot;</span>
                    <span>u/{{ $p['author'] ?? '[deleted]' }}</span>
                    <span>&middot;</span>
                    <span>{{ \Carbon\Carbon::createFromTimestamp($p['created_utc'])->diffForHumans() }}</span>
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
                    <summary class="list-none cursor-pointer px-2 -mt-1 text-lg leading-none text-neutral-500 hover:text-neutral-800 select-none">&#8943;</summary>
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
                        <img src="{{ $g }}" loading="lazy" class="w-full rounded-lg {{ $nsfwClass }}"
                             @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
                    @endforeach
                </div>
            @elseif ($videoUrl)
                <video controls playsinline preload="metadata" class="w-full rounded-lg {{ $nsfwClass }}"
                       @if ($imageUrl) poster="{{ $imageUrl }}" @endif
                       @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
                    <source src="{{ $videoUrl }}" type="video/mp4">
                </video>
            @elseif ($imageUrl)
                <img src="{{ $imageUrl }}" loading="lazy" class="w-full rounded-lg {{ $nsfwClass }}"
                     @if ($isNsfw) onclick="{{ $nsfwToggle }}" @endif>
            @endif

            @if (! $isSelf && !empty($p['domain']) && !str_starts_with($p['domain'], 'self.'))
                <div class="text-xs text-neutral-500 mt-2">{{ $p['domain'] }}</div>
            @endif

            <div class="flex items-center gap-4 mt-3 text-sm text-neutral-400">
                <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}</span>
                <span>&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }}</span>
            </div>
        </article>

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
    @endif
@endsection
