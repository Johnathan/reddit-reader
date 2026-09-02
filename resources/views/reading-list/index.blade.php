@extends('layouts.app')

@section('subnav-title', 'reading list')

@section('title', 'Reading list · reader')

@section('content')
    @php $isRead = $view === 'read'; @endphp

    {{-- Two tabs: the queue and the archive of things you've read. --}}
    <div class="flex items-center gap-1.5 text-sm mb-4">
        <a href="{{ route('reading.index') }}"
           class="px-3 py-1.5 rounded-full {{ ! $isRead ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' }}">
            To read <span class="opacity-70">{{ $unreadCount }}</span>
        </a>
        <a href="{{ route('reading.index', ['view' => 'read']) }}"
           class="px-3 py-1.5 rounded-full {{ $isRead ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' }}">
            Read <span class="opacity-70">{{ $readCount }}</span>
        </a>

        @if (! $isRead && $unreadCount > 0)
            <a href="{{ route('reading.reader') }}"
               class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-neutral-900 border border-neutral-700 text-neutral-200 hover:border-orange-500">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg>
                Read now
            </a>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="text-center py-16">
            <svg viewBox="0 0 24 24" class="w-10 h-10 mx-auto mb-3 text-neutral-700" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3h12a1 1 0 0 1 1 1v16l-7-4-7 4V4a1 1 0 0 1 1-1z"/>
            </svg>
            @if ($isRead)
                <p class="text-neutral-400 mb-1">Nothing read yet.</p>
                <p class="text-sm text-neutral-500">Posts you finish in the reader show up here.</p>
            @else
                <p class="text-neutral-400 mb-1">Your reading list is empty.</p>
                <p class="text-sm text-neutral-500">Tap the bookmark on any post to stack it up for later.</p>
            @endif
        </div>
    @else
        @if ($isRead)
            <div class="flex justify-end mb-2">
                <button type="button" data-list-clear data-read-only class="text-xs text-neutral-500 hover:text-red-400">Clear read</button>
            </div>
        @endif

        <div id="reading-items">
            @foreach ($items as $item)
                @include('reading-list.row', ['item' => $item])
            @endforeach
        </div>
    @endif
@endsection
