@extends('layouts.app')

@section('subnav-title', 'feeds')

@section('content')
    <form method="POST" action="{{ route('feeds.store') }}" class="mb-6 flex gap-2">
        @csrf
        <input type="text" name="name" maxlength="50" required placeholder="New feed name&hellip;"
               class="flex-1 bg-neutral-900 border border-neutral-700 rounded-lg px-3 py-2 text-sm placeholder-neutral-500 focus:outline-none focus:border-orange-500">
        <button type="submit" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">Create</button>
    </form>

    @error('name')
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-2 text-sm mb-4">{{ $message }}</div>
    @enderror

    @forelse ($feeds as $feed)
        <div data-removable class="bg-neutral-900 rounded-lg px-3 py-3 mb-2">
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('feeds.show', $feed->slug) }}" class="font-medium hover:text-orange-300">{{ $feed->name }}</a>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-neutral-500">{{ $feed->subreddits->count() }} subs</span>
                    <form method="POST" action="{{ route('feeds.destroy', $feed) }}"
                          data-ajax data-ajax-remove data-turbo="false" data-confirm="Delete “{{ $feed->name }}”?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-neutral-500 hover:text-red-300">delete</button>
                    </form>
                </div>
            </div>
            @if ($feed->subreddits->isNotEmpty())
                <div class="text-xs text-neutral-500 mt-1 truncate">
                    {{ $feed->subreddits->pluck('subreddit')->map(fn ($s) => 'r/'.$s)->join(', ') }}
                </div>
            @else
                <div class="text-xs text-neutral-600 mt-1">Empty &mdash; add subs from any subreddit or Explore.</div>
            @endif
        </div>
    @empty
        <div class="text-center py-12 text-neutral-400">
            <p class="mb-1">No custom feeds yet.</p>
            <p class="text-sm text-neutral-500">Create one above, then add subreddits to it to build a curated combined feed.</p>
        </div>
    @endforelse
@endsection
