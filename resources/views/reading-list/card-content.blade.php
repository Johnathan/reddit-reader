{{-- Bare fragment: the post + comments for one reader card, fetched on demand
     by reading.content. No layout — it is injected into an existing card. --}}
@if ($error)
    <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm">
        {{ $error }}
    </div>
    <p class="text-xs text-neutral-500 mt-3">
        Saved as &ldquo;{{ $item->title }}&rdquo; from r/{{ $item->subreddit }}.
    </p>
@elseif ($article !== null)
    <h1 class="text-lg font-semibold leading-snug mb-2">{{ $article['title'] ?? $item->title }}</h1>
    @if ($article['byline'] ?? null)
        <p class="text-xs text-neutral-500 mb-3">{{ $article['byline'] }}</p>
    @endif
    <div class="reddit-html text-[15px] leading-relaxed">{!! $article['html'] !!}</div>
    <a href="{{ $item->url }}" target="_blank" rel="noopener" class="inline-block mt-4 text-xs text-neutral-500 hover:text-orange-500">Original article &#8599;</a>
@elseif ($item->source === 'hn')
    @include('partials.hn-thread', ['p' => $post, 'comments' => $comments])
@else
    @include('partials.post-detail', ['p' => $post, 'comments' => $comments])
@endif
