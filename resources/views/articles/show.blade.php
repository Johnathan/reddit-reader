@extends('layouts.app')

@section('subnav-title', strtolower($sourceName))

@section('title', Str::limit($article['title'] ?? data_get($entry, 'title', 'Article'), 70).' · '.$sourceName.' · reader')

@section('content')
    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-3">{{ $error }}</div>
        @if ($entry)
            <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="text-sm text-orange-400 hover:underline">Open on the site &#8599;</a>
        @endif
    @else
        <article>
            <div class="text-xs text-neutral-500 mb-1 flex items-center gap-1.5">
                <span class="px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300 font-semibold text-[10px]">{{ $sourceName }}</span>
                <span>&middot;</span>
                <span>{{ isset($entry['created_utc']) ? \Carbon\Carbon::createFromTimestamp($entry['created_utc'])->diffForHumans() : '' }}</span>
            </div>

            <h1 class="text-lg font-semibold leading-snug mb-1">{{ $article['title'] ?? $entry['title'] }}</h1>
            @if ($article['byline'] ?? null)
                <p class="text-xs text-neutral-500 mb-3">{{ $article['byline'] }}</p>
            @endif

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
            <div class="reddit-html text-[15px] leading-relaxed">{!! $article['html'] !!}</div>

            <div class="flex items-center gap-4 mt-4 pt-3 border-t border-neutral-800 text-sm text-neutral-400">
                <a href="{{ $entry['url'] }}" target="_blank" rel="noopener" class="text-xs text-neutral-500 hover:text-orange-500">Original article &#8599;</a>
                @include('partials.read-later-button', ['p' => $entry, 'saved' => isset($myReadingListIds[$entry['id']]), 'label' => true])
                @include('partials.save-button', [
                    'kind' => 'post', 'redditId' => $entry['id'], 'subreddit' => $sourceKey,
                    'source' => $sourceKey, 'itemUrl' => $entry['url'], 'postId' => null,
                    'saved' => isset($myArchivedIds[$entry['id']]), 'label' => true,
                ])
            </div>
        </article>
    @endif
@endsection
