@extends('layouts.app')

@section('subnav-title', 'r/'.$subreddit)

@section('content')
    <div class="flex items-center justify-between mb-3 gap-2">
        <h1 class="text-lg font-semibold truncate">r/{{ $subreddit }}</h1>
        @if ($isSubscribed)
            <form method="POST" action="{{ route('subs.destroy', $subreddit) }}">
                @csrf
                @method('DELETE')
                <button class="text-xs px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-red-900 hover:text-red-200 whitespace-nowrap">
                    Unsubscribe
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('subs.store') }}">
                @csrf
                <input type="hidden" name="subreddit" value="{{ $subreddit }}">
                <button class="text-xs px-3 py-1.5 rounded-full bg-orange-500 text-white hover:bg-orange-400 whitespace-nowrap">
                    Subscribe
                </button>
            </form>
        @endif
    </div>

    @include('partials.sort-switcher', ['routeName' => 'subreddit.show', 'routeParams' => ['subreddit' => $subreddit], 'sort' => $sort])

    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">
            {{ $error }}
        </div>
    @endif

    <div>
        @forelse ($posts as $post)
            @include('partials.post-card', ['postData' => $post['data'], 'hideSubreddit' => true])
        @empty
            @unless ($error)
                <p class="text-neutral-500 text-sm py-8 text-center">No posts found.</p>
            @endunless
        @endforelse
    </div>

    @if ($after)
        <div class="text-center mt-4">
            <a href="{{ route('subreddit.show', array_filter(['subreddit' => $subreddit, 'sort' => $sort, 'after' => $after, 't' => request('t')])) }}"
               class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">
                Load more
            </a>
        </div>
    @endif
@endsection
