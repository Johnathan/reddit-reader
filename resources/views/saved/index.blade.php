@extends('layouts.app')

@section('subnav-title', 'saved')

@section('title', 'Saved · reader')

@section('content')
    <form method="GET" action="{{ route('saved.index') }}" class="mb-3 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search everything you&rsquo;ve saved&hellip;"
               class="flex-1 bg-neutral-900 border border-neutral-700 rounded-lg px-3 py-2 text-sm placeholder-neutral-500 focus:outline-none focus:border-orange-500">
        @foreach (['folder' => $activeFolder, 'tag' => $activeTag, 'kind' => $activeKind] as $key => $value)
            @if ($value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <button type="submit" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">Search</button>
    </form>

    @php
        $chip = 'px-2.5 py-1 rounded-full whitespace-nowrap text-xs';
        $chipOn = 'bg-orange-500 text-white';
        $chipOff = 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700';
        $without = fn (array $drop) => route('saved.index', array_diff_key(array_filter([
            'q' => $q, 'folder' => $activeFolder, 'tag' => $activeTag, 'kind' => $activeKind,
        ]), array_flip($drop)));
        $with = fn (array $add) => route('saved.index', array_filter(array_merge([
            'q' => $q, 'folder' => $activeFolder, 'tag' => $activeTag, 'kind' => $activeKind,
        ], $add)));
    @endphp

    <div class="flex items-center gap-1.5 mb-3 overflow-x-auto pb-1">
        <a href="{{ $without(['kind']) }}" class="{{ $chip }} {{ $activeKind === '' ? $chipOn : $chipOff }}">All</a>
        <a href="{{ $with(['kind' => 'post']) }}" class="{{ $chip }} {{ $activeKind === 'post' ? $chipOn : $chipOff }}">Posts</a>
        <a href="{{ $with(['kind' => 'comment']) }}" class="{{ $chip }} {{ $activeKind === 'comment' ? $chipOn : $chipOff }}">Comments</a>

        @if ($folders->isNotEmpty())
            <span class="text-neutral-700">&middot;</span>
            @foreach ($folders as $folder)
                <a href="{{ $activeFolder === $folder->slug ? $without(['folder']) : $with(['folder' => $folder->slug]) }}"
                   class="{{ $chip }} {{ $activeFolder === $folder->slug ? $chipOn : $chipOff }}">
                    {{ $folder->name }} <span class="opacity-60">{{ $folder->items_count }}</span>
                </a>
            @endforeach
        @endif

        @foreach ($tags as $tag)
            <a href="{{ $activeTag === $tag->slug ? $without(['tag']) : $with(['tag' => $tag->slug]) }}"
               class="{{ $chip }} {{ $activeTag === $tag->slug ? $chipOn : $chipOff }}">#{{ $tag->name }}</a>
        @endforeach
    </div>

    <div class="flex items-center justify-between gap-2 mb-3 text-xs text-neutral-500">
        <span>
            @if ($q !== '')
                {{ $items->total() }} {{ Str::plural('result', $items->total()) }} for &ldquo;{{ $q }}&rdquo;
            @else
                {{ $total }} saved
            @endif
        </span>
        {{-- On phones the tab bar has Saved but not the reading list, so this
             is the mobile route in (the dock only shows when non-empty). --}}
        <a href="{{ route('reading.index') }}" class="ml-auto hover:text-orange-500">Reading list &rarr;</a>
        <details class="relative">
            <summary class="list-none cursor-pointer hover:text-orange-500 select-none">New folder</summary>
            <form method="POST" action="{{ route('saved.folders.store') }}"
                  class="absolute right-0 mt-1 z-20 w-56 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl p-2 flex gap-1">
                @csrf
                <input type="text" name="name" maxlength="50" required placeholder="Folder name"
                       class="flex-1 min-w-0 bg-neutral-950 border border-neutral-700 rounded px-2 py-1 text-xs">
                <button type="submit" class="px-2 py-1 rounded bg-orange-500 text-white text-xs">Add</button>
            </form>
        </details>
    </div>

    @forelse ($items as $item)
        @include('saved.row', ['item' => $item])
    @empty
        <div class="text-center py-16">
            <p class="text-neutral-400 mb-1">
                {{ $q !== '' ? 'Nothing matches that.' : 'You haven’t saved anything yet.' }}
            </p>
            <p class="text-sm text-neutral-500">
                Saving keeps a copy of the text, so it stays readable even if the original is deleted.
            </p>
        </div>
    @endforelse

    {{-- Custom view: Laravel's default paginator uses gray/white classes the
         paper palette doesn't remap, which rendered as a stark white block. --}}
    <div class="mt-4">{{ $items->links('partials.pagination') }}</div>
@endsection
