@php
    /**
     * An HN story (or comment-as-root) plus its comment tree, both already
     * normalized by App\Services\HackerNews. Props:
     *   $p         - normalized item
     *   $comments  - t1-shaped children for partials.comment
     */
    $isComment = ($p['type'] ?? 'story') === 'comment';
@endphp

<article class="pb-4 border-b border-neutral-800">
    <div class="flex items-start justify-between gap-2">
        <div class="text-xs text-neutral-500 mb-1 flex items-center gap-1.5 flex-wrap">
            <span class="px-1.5 py-0.5 rounded bg-[#f60]/15 text-[#c50] font-semibold text-[10px]">HN</span>
            @if ($p['author'])
                <span>&middot;</span>
                <span>u/{{ $p['author'] }}</span>
            @endif
            <span>&middot;</span>
            <span>{{ \Carbon\Carbon::createFromTimestamp($p['created_utc'])->diffForHumans() }}</span>
        </div>

        <details class="relative shrink-0">
            <summary class="list-none cursor-pointer px-2 -mt-1 text-lg leading-none text-neutral-500 hover:text-neutral-800 select-none">&#8943;</summary>
            <div class="absolute right-0 mt-1 z-20 w-52 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl py-1 text-sm">
                @if ($p['url'])
                    <a href="{{ $p['url'] }}" target="_blank" rel="noopener" class="block px-3 py-2 hover:bg-neutral-800">Open original link &#8599;</a>
                @endif
                <a href="{{ $p['permalink'] }}" target="_blank" rel="noopener" class="block px-3 py-2 text-neutral-400 hover:bg-neutral-800">View on Hacker News &#8599;</a>
            </div>
        </details>
    </div>

    @if ($isComment)
        <p class="text-xs text-neutral-500 mb-2">A single comment thread.
            @if ($p['story_id'])
                <a href="{{ route('hn.item', $p['story_id']) }}" class="text-orange-400 hover:underline">Back to the full discussion &rarr;</a>
            @endif
        </p>
    @else
        <h1 class="text-lg font-semibold leading-snug mb-2">{{ $p['title'] }}</h1>
    @endif

    @if (! empty($p['selftext_html']))
        <div class="reddit-html text-[15px] leading-relaxed">{!! $p['selftext_html'] !!}</div>
    @endif

    @if ($p['url'] && $p['domain'])
        <div class="text-xs text-neutral-500 mt-2">{{ $p['domain'] }}</div>
    @endif

    <div class="flex items-center gap-4 mt-3 text-sm text-neutral-400">
        <span>&#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}</span>
        <span>&#128172; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }}</span>
        @include('partials.read-later-button', ['p' => $p, 'saved' => isset($myReadingListIds[$p['id']]), 'label' => true])
        @include('partials.save-button', [
            'kind' => $isComment ? 'comment' : 'post', 'redditId' => $p['id'], 'subreddit' => 'hackernews',
            'source' => 'hn', 'postId' => $p['story_id'] ?? null,
            'saved' => isset($myArchivedIds[$p['id']]), 'label' => true,
        ])
    </div>
</article>

<div class="mt-4">
    @forelse ($comments as $c)
        @include('partials.comment', [
            'comment' => $c,
            'depth' => 0,
            'postArgs' => ['source' => 'hn', 'subreddit' => 'hackernews', 'id' => $p['id'], 'slug' => '-'],
        ])
    @empty
        <p class="text-neutral-500 text-sm py-6 text-center">No comments yet.</p>
    @endforelse
</div>
