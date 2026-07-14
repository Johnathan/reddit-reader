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

        @if ($error)
            <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">
                {{ $error }}
            </div>
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
                <a href="{{ route('home', array_filter(['sort' => $sort, 'after' => $after, 't' => request('t')])) }}"
                   class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">
                    Load more
                </a>
            </div>
        @endif
    @endif
@endsection
