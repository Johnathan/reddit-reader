@extends('layouts.app')

@section('subnav-title', 'manage subscriptions')

@section('content')
    @if (session('status'))
        <div class="rounded-lg bg-green-900/30 border border-green-700 text-green-200 px-3 py-3 text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Reddit only serves 18+ subs to a logged-in account, so linking one is
         the only way to see them. Everything else works without this. --}}
    <div class="rounded-lg border border-neutral-800 bg-neutral-900/60 px-3 py-3 mb-5 flex items-center justify-between gap-3">
        @if (auth()->user()->reddit_username)
            <div class="text-sm">
                <span class="text-neutral-400">Reddit account</span>
                <span class="font-medium text-neutral-100">u/{{ auth()->user()->reddit_username }}</span>
                <span class="block text-xs text-neutral-500 mt-0.5">18+ subreddits visible (if enabled in your Reddit prefs)</span>
            </div>
            <form method="POST" action="{{ route('reddit.disconnect') }}">
                @csrf
                @method('DELETE')
                <button class="px-3 py-1.5 rounded-lg border border-neutral-700 text-neutral-300 text-xs hover:bg-neutral-800">
                    Disconnect
                </button>
            </form>
        @else
            <div class="text-sm">
                <span class="text-neutral-300">No Reddit account linked</span>
                <span class="block text-xs text-neutral-500 mt-0.5">Link one to see 18+ subreddits &mdash; they're hidden otherwise</span>
            </div>
            <a href="{{ route('reddit.connect') }}"
               class="px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-medium hover:bg-orange-400 whitespace-nowrap">
                Connect Reddit
            </a>
        @endif
    </div>

    <form method="GET" action="{{ route('subs.index') }}" class="mb-5 flex gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search subreddits&hellip;"
               class="flex-1 bg-neutral-900 border border-neutral-700 rounded-lg px-3 py-2 text-sm placeholder-neutral-500 focus:outline-none focus:border-orange-500">
        <button type="submit" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">
            Search
        </button>
    </form>

    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">
            {{ $error }}
        </div>
    @endif

    @if ($q !== '')
        <h2 class="text-sm font-semibold text-neutral-400 mb-2">Results for &ldquo;{{ $q }}&rdquo;</h2>
        <div class="space-y-2 mb-6">
            @php $subscribedNames = $subscriptions->pluck('subreddit'); @endphp
            @forelse ($results as $r)
                @php
                    $rd = $r['data'];
                    $name = $rd['display_name'] ?? '';
                    $subscribed = $subscribedNames->contains(strtolower($name));
                @endphp
                <div class="flex items-center justify-between gap-3 bg-neutral-900 rounded-lg px-3 py-2">
                    <div class="min-w-0">
                        <a href="{{ route('subreddit.show', strtolower($name)) }}" class="font-medium hover:text-orange-300">r/{{ $name }}</a>
                        <div class="text-xs text-neutral-500 truncate">
                            {{ \App\Services\Reddit::formatScore($rd['subscribers'] ?? 0) }} members
                            @if (!empty($rd['public_description']))
                                &middot; {{ Str::limit($rd['public_description'], 60) }}
                            @endif
                        </div>
                    </div>
                    @if ($subscribed)
                        <form method="POST" action="{{ route('subs.destroy', strtolower($name)) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 whitespace-nowrap hover:bg-red-900 hover:text-red-200">
                                Subscribed
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('subs.store') }}">
                            @csrf
                            <input type="hidden" name="subreddit" value="{{ $name }}">
                            <button class="text-xs px-3 py-1.5 rounded-full bg-orange-500 text-white whitespace-nowrap hover:bg-orange-400">
                                Subscribe
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-neutral-500 text-sm">No subreddits found.</p>
            @endforelse
        </div>
    @endif

    <h2 class="text-sm font-semibold text-neutral-400 mb-2">Your subscriptions</h2>
    @forelse ($subscriptions as $s)
        <div class="flex items-center justify-between gap-3 bg-neutral-900 rounded-lg px-3 py-2 mb-2">
            <a href="{{ route('subreddit.show', $s->subreddit) }}" class="font-medium hover:text-orange-300">r/{{ $s->subreddit }}</a>
            <form method="POST" action="{{ route('subs.destroy', $s->subreddit) }}">
                @csrf
                @method('DELETE')
                <button class="text-xs px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-red-900 hover:text-red-200">
                    Unsubscribe
                </button>
            </form>
        </div>
    @empty
        <p class="text-neutral-500 text-sm">No subscriptions yet &mdash; search above to find subreddits.</p>
    @endforelse
@endsection
