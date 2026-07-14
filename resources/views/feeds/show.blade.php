@extends('layouts.app')

@section('subnav-title', $feed->name)

@section('content')
    <div class="flex items-center justify-between mb-3 gap-2">
        <h1 class="text-lg font-semibold truncate">{{ $feed->name }}</h1>
        <a href="{{ route('feeds.index') }}" class="text-xs text-neutral-500 hover:text-neutral-300 whitespace-nowrap">all feeds</a>
    </div>

    {{-- The subs in this feed, each removable inline. --}}
    <div class="flex flex-wrap gap-1.5 mb-4">
        @foreach ($feed->subreddits as $fs)
            <span class="inline-flex items-center gap-1 bg-neutral-800 rounded-full pl-3 pr-1.5 py-1 text-xs">
                <a href="{{ route('subreddit.show', $fs->subreddit) }}" class="hover:text-orange-300">r/{{ $fs->subreddit }}</a>
                <form method="POST" action="{{ route('feeds.subs.destroy', [$feed, $fs->subreddit]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-neutral-500 hover:text-red-300 w-4 h-4 leading-none" title="Remove">&times;</button>
                </form>
            </span>
        @endforeach

        <details class="relative">
            <summary class="list-none cursor-pointer bg-neutral-800 rounded-full px-3 py-1 text-xs text-orange-300 hover:bg-neutral-700 select-none">+ add sub</summary>
            <form method="POST" action="{{ route('feeds.subs.store', $feed) }}" class="absolute left-0 mt-1 z-20 flex gap-1 bg-neutral-900 border border-neutral-700 rounded-lg p-2 shadow-xl">
                @csrf
                <input type="text" name="subreddit" placeholder="subreddit" pattern="[A-Za-z0-9_]{2,21}"
                       class="bg-neutral-800 border border-neutral-700 rounded px-2 py-1 text-xs w-32 focus:outline-none focus:border-orange-500">
                <button class="text-xs px-2 py-1 rounded bg-orange-500 text-white hover:bg-orange-400">Add</button>
            </form>
        </details>
    </div>

    @if (empty($subreddits))
        <div class="text-center py-12 text-neutral-500 text-sm">
            This feed is empty. Add subreddits above (or use the &ldquo;+ feed&rdquo; button on any subreddit).
        </div>
    @else
        @include('partials.sort-switcher', ['routeName' => 'feeds.show', 'routeParams' => ['slug' => $feed->slug], 'sort' => $sort])

        @if ($error)
            <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">{{ $error }}</div>
        @endif

        <div>
            @forelse ($posts as $post)
                @include('partials.post-card', ['postData' => $post['data'], 'hideSubreddit' => false])
            @empty
                @unless ($error)
                    <p class="text-neutral-500 text-sm py-8 text-center">No posts found.</p>
                @endunless
            @endforelse
        </div>

        @if ($after)
            <div class="text-center mt-4">
                <a href="{{ route('feeds.show', array_filter(['slug' => $feed->slug, 'sort' => $sort, 'after' => $after, 't' => request('t')])) }}"
                   class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">Load more</a>
            </div>
        @endif
    @endif
@endsection
