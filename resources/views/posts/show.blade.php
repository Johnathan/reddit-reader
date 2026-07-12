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
        @endphp

        <article class="pb-4 border-b border-neutral-800">
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

            <h1 class="text-lg font-semibold leading-snug mb-2">{{ $p['title'] }}</h1>

            @if ($isSelf)
                @if (!empty($p['selftext_html']))
                    <div class="reddit-html text-[15px] leading-relaxed">
                        {!! \App\Services\Reddit::sanitizeHtml($p['selftext_html']) !!}
                    </div>
                @endif
            @else
                @php
                    $hint = $p['post_hint'] ?? null;
                    $previewSource = $p['preview']['images'][0]['source']['url'] ?? null;
                    $outUrl = !empty($p['url']) ? html_entity_decode($p['url']) : null;
                @endphp

                @if ($hint === 'image' && $outUrl)
                    <a href="{{ $outUrl }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ $outUrl }}"
                             class="w-full rounded-lg mb-1 {{ $isNsfw ? 'blur-md nsfw-img' : '' }}"
                             @if ($isNsfw) onclick="event.preventDefault(); this.classList.toggle('blur-md')" @endif>
                    </a>
                @elseif ($previewSource)
                    <a href="{{ $permalink }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ html_entity_decode($previewSource) }}"
                             class="w-full rounded-lg mb-1 {{ $isNsfw ? 'blur-md nsfw-img' : '' }}"
                             @if ($isNsfw) onclick="event.preventDefault(); this.classList.toggle('blur-md')" @endif>
                        <div class="text-xs text-orange-300 mt-1">view on reddit &rarr;</div>
                    </a>
                @elseif ($outUrl)
                    <a href="{{ $outUrl }}" target="_blank" rel="noopener" class="text-orange-300 break-all text-sm underline">{{ $outUrl }}</a>
                @endif

                @if (!empty($p['domain']))
                    <div class="text-xs text-neutral-500 mt-1">{{ $p['domain'] }}</div>
                @endif
            @endif

            <div class="flex items-center gap-4 mt-3 text-sm text-neutral-400">
                <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}</span>
                <span>&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }}</span>
                <a href="{{ $permalink }}" target="_blank" rel="noopener" class="hover:text-white ml-auto">view on reddit &#8599;</a>
            </div>
        </article>

        <div class="mt-4">
            @forelse ($comments as $c)
                @include('partials.comment', ['comment' => $c, 'depth' => 0, 'permalink' => $permalink])
            @empty
                <p class="text-neutral-500 text-sm py-6 text-center">No comments yet.</p>
            @endforelse
        </div>
    @endif
@endsection
