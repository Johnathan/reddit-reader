@extends('layouts.app')

@section('subnav-title', 'home')

@section('content')
    @if (empty($subreddits))
        <div class="text-center py-16">
            <p class="text-neutral-400 mb-4">You haven&rsquo;t subscribed to any subreddits yet.</p>
            <div class="flex items-center justify-center gap-2">
                <a href="{{ route('explore') }}" class="inline-block px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">
                    Explore subreddits
                </a>
                <a href="{{ route('subs.index') }}" class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm font-medium hover:bg-neutral-700">
                    Search
                </a>
            </div>
        </div>
    @else
        @include('partials.sort-switcher', ['routeName' => 'home', 'routeParams' => [], 'sort' => $sort])

        @include('partials.feed-list', [
            'posts' => $posts,
            'error' => $error,
            'hideSubreddit' => false,
            'loadMoreUrl' => $after ? route('home', array_filter(['sort' => $sort, 'after' => $after, 't' => request('t')])) : null,
        ])
    @endif
@endsection
