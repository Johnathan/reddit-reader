{{-- Bare fragment: the extracted article for a feed card's expando panel. --}}
@if ($error)
    <p class="text-sm text-red-300 py-2">{{ $error }}
        @if ($entry)
            <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="text-orange-400 hover:underline">Open on the site &#8599;</a>
        @endif
    </p>
@else
@php
    // The extraction often loses BBC's image markup; fall back to the feed's
    // own thumbnail, upgraded from the 240px variant to something readable.
    $hero = null;
    if (($entry['thumbnail'] ?? null) && ! str_contains($article['html'] ?? '', '<img')) {
        $hero = preg_replace('~/(\d{2,4})/cpsprodpb~', '/800/cpsprodpb', $entry['thumbnail']);
    }
@endphp
    @if ($hero)
        <img src="{{ $hero }}" loading="lazy" alt="" class="w-full rounded-lg mb-3">
    @endif
    @if ($article['byline'] ?? null)
        <p class="text-xs text-neutral-500 mb-2">{{ $article['byline'] }}</p>
    @endif
    <div class="reddit-html text-[15px] leading-relaxed">{!! $article['html'] !!}</div>
    <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="inline-block mt-3 text-xs text-neutral-500 hover:text-orange-500">Original article &#8599;</a>
@endif
