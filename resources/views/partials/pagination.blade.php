{{-- Paper-theme paginator: Laravel's default Tailwind view uses gray/white
     utilities the palette remap doesn't cover. Simple prev/next is all a
     personal archive needs. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between text-sm">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1.5 rounded-full bg-neutral-900 text-neutral-600">&larr; Newer</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-neutral-700">&larr; Newer</a>
        @endif

        <span class="text-xs text-neutral-500">
            Page {{ $paginator->currentPage() }}@if (method_exists($paginator, 'lastPage')) of {{ $paginator->lastPage() }}@endif
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="px-3 py-1.5 rounded-full bg-neutral-800 text-neutral-300 hover:bg-neutral-700">Older &rarr;</a>
        @else
            <span class="px-3 py-1.5 rounded-full bg-neutral-900 text-neutral-600">Older &rarr;</span>
        @endif
    </nav>
@endif
