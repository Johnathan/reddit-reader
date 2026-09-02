@extends('layouts.app')

@section('subnav-title', 'r/'.$subreddit)

@section('title', 'r/'.$subreddit.' · reader')

@section('content')
    <div class="flex items-center justify-between mb-3 gap-2">
        <h1 class="text-lg font-semibold truncate">r/{{ $subreddit }}</h1>
        @include('partials.subscribe-button', ['subreddit' => $subreddit, 'subscribed' => $isSubscribed, 'subscribedLabel' => 'Unsubscribe'])
    </div>

    @if (! empty($related))
        <div class="mb-3">
            <div class="text-xs text-neutral-500 mb-1.5">Related</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($related as $rel)
                    <a href="{{ route('subreddit.show', $rel) }}"
                       class="text-xs px-2.5 py-1 rounded-full bg-neutral-900 border border-neutral-800 text-neutral-300 hover:border-orange-500 hover:text-orange-300">
                        r/{{ $rel }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($myFeeds->isNotEmpty())
        <details class="relative mb-3 inline-block">
            <summary class="list-none cursor-pointer text-xs px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-neutral-700 select-none">+ add to feed</summary>
            <div class="absolute left-0 mt-1 z-20 w-44 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl py-1">
                @foreach ($myFeeds as $feed)
                    <form method="POST" action="{{ route('feeds.subs.store', $feed) }}" data-ajax data-turbo="false">
                        @csrf
                        <input type="hidden" name="subreddit" value="{{ $subreddit }}">
                        <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-neutral-200 hover:bg-neutral-800 truncate">{{ $feed->name }}</button>
                    </form>
                @endforeach
            </div>
        </details>
    @endif

    @include('partials.sort-switcher', ['routeName' => 'subreddit.show', 'routeParams' => ['subreddit' => $subreddit], 'sort' => $sort, 'currentT' => $t])

    @include('partials.feed-list', [
        'posts' => $posts,
        'error' => $error,
        'hideSubreddit' => true,
        'loadMoreUrl' => $after ? route('subreddit.show', array_filter(['subreddit' => $subreddit, 'sort' => $sort, 'after' => $after, 't' => $t])) : null,
    ])
@endsection
