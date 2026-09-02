@extends('layouts.app')

@section('subnav-title', $feed->name)

@section('title', $feed->name.' · reader')

@section('content')
    <div class="flex items-center justify-between mb-3 gap-2">
        <h1 class="text-lg font-semibold truncate">{{ $feed->name }}</h1>
        <a href="{{ route('feeds.index') }}" class="text-xs text-neutral-500 hover:text-neutral-300 whitespace-nowrap">all feeds</a>
    </div>

    {{-- The subs in this feed, each removable inline (no reload). --}}
    <div class="flex flex-wrap gap-1.5 mb-4">
        @foreach ($feed->subreddits as $fs)
            @include('feeds.partials.chip', ['feed' => $feed, 'fs' => $fs])
        @endforeach

        <details id="feed-add-sub" class="relative">
            <summary class="list-none cursor-pointer bg-neutral-800 rounded-full px-3 py-1 text-xs text-orange-300 hover:bg-neutral-700 select-none">+ add sub</summary>
            <form method="POST" action="{{ route('feeds.subs.store', $feed) }}" data-ajax data-turbo="false" data-ajax-append="#feed-add-sub"
                  class="absolute left-0 mt-1 z-20 flex gap-1 bg-neutral-900 border border-neutral-700 rounded-lg p-2 shadow-xl">
                @csrf
                <input type="text" name="subreddit" placeholder="subreddit" pattern="[A-Za-z0-9_]{2,21}" data-sub-autocomplete="fill"
                       class="bg-neutral-800 border border-neutral-700 rounded px-2 py-1 text-xs w-32 focus:outline-none focus:border-orange-500">
                <button type="submit" class="text-xs px-2 py-1 rounded bg-orange-500 text-white hover:bg-orange-400">Add</button>
            </form>
        </details>
    </div>

    @if (empty($subreddits))
        <div class="text-center py-12 text-neutral-500 text-sm">
            This feed is empty. Add subreddits above (or use the &ldquo;+ feed&rdquo; button on any subreddit).
        </div>
    @else
        @include('partials.sort-switcher', ['routeName' => 'feeds.show', 'routeParams' => ['slug' => $feed->slug], 'sort' => $sort])

        @include('partials.feed-list', [
            'posts' => $posts,
            'error' => $error,
            'hideSubreddit' => false,
            'loadMoreUrl' => $after ? route('feeds.show', array_filter(['slug' => $feed->slug, 'sort' => $sort, 'after' => $after, 't' => request('t')])) : null,
        ])
    @endif
@endsection
