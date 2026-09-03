@extends('layouts.app')

@section('subnav-title', 'reader')

@section('title', 'Reading list · reader')

@section('content')
    @if ($items->isEmpty())
        <div class="text-center py-16">
            <p class="text-neutral-400 mb-1">Nothing left to read.</p>
            <p class="text-sm text-neutral-500 mb-4">Save some posts, then work through them here.</p>
            <a href="{{ route('reading.index') }}" class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">Back to the list</a>
        </div>
    @else
        {{-- Progress bar: fills as you work through the deck. --}}
        <div class="flex items-center gap-3 mb-2">
            <div class="flex-1 h-1 rounded-full bg-neutral-800 overflow-hidden">
                <div data-reader-progress class="h-full bg-orange-500 transition-all duration-300" style="width: 0%"></div>
            </div>
            <span class="text-xs text-neutral-500 tabular-nums shrink-0">
                <span data-reader-position>1</span> / {{ $items->count() }}
            </span>
            <a href="{{ route('reading.index') }}" class="text-xs text-neutral-500 hover:text-orange-500 shrink-0">Done</a>
        </div>

        {{-- Horizontal snap deck: swipe left/right between posts, scroll a card
             to read it. CSS scroll-snap handles the swipe natively — no JS
             gesture engine. Each card lazy-loads its post + comments. --}}
        <div data-reader-deck data-total="{{ $items->count() }}"
             class="reader-deck flex overflow-x-auto snap-x snap-mandatory overscroll-x-contain -mx-3">
            @foreach ($items as $i => $item)
                <section data-reader-card data-index="{{ $i }}"
                         data-post-id="{{ $item->post_id }}"
                         data-url="{{ route('reading.content', ['postId' => $item->post_id, 'source' => $item->source ?? 'reddit']) }}"
                         data-mark-url="{{ route('reading.markRead', ['postId' => $item->post_id, 'source' => $item->source ?? 'reddit']) }}"
                         data-last="{{ $loop->last ? '1' : '0' }}"
                         class="reader-card shrink-0 w-full snap-center snap-always overflow-y-auto overscroll-contain px-3">
                    <div class="max-w-2xl mx-auto py-2">
                        <div class="flex items-center gap-2 mb-2">
                            <span data-card-read-chip hidden class="text-[11px] font-medium text-emerald-400 flex items-center gap-1">
                                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Read
                            </span>
                            @unless ($loop->last)
                                <button type="button" data-reader-next class="ml-auto text-xs text-neutral-500 hover:text-orange-500">Next &rarr;</button>
                            @endunless
                        </div>

                        {{-- Snapshot header, always visible even before content loads. --}}
                        <div class="text-xs text-neutral-500 mb-1">{{ $item->sourceLabel() }}</div>
                        <h2 class="font-semibold leading-snug mb-3">{{ $item->title }}</h2>

                        <div data-card-body>
                            {{-- Skeleton in the shape of a post + comments, so
                                 the card doesn't jump when content lands. --}}
                            <div data-card-loading class="py-2 space-y-2.5" role="status" aria-label="Loading">
                                <div class="skeleton h-40 w-full"></div>
                                <div class="skeleton h-3.5 w-full"></div>
                                <div class="skeleton h-3.5 w-5/6"></div>
                                <div class="skeleton h-3.5 w-2/3"></div>
                                <div class="h-2"></div>
                                <div class="skeleton h-3.5 w-1/3"></div>
                                <div class="skeleton h-3.5 w-full"></div>
                                <div class="skeleton h-3.5 w-4/6"></div>
                            </div>
                        </div>

                        <div class="py-10 text-center">
                            @if ($loop->last)
                                <a href="{{ route('reading.index') }}" class="inline-block px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">Finish</a>
                            @else
                                <button type="button" data-reader-next class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">Next post &rarr;</button>
                            @endif
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
