@php $thumb = $item->thumbnailUrl(); @endphp

<article data-reading-row data-post-id="{{ $item->post_id }}"
         class="flex gap-3 border-b border-neutral-800 py-3 {{ $item->isRead() ? 'opacity-50' : '' }}">
    @if ($thumb)
        <a href="{{ $item->url() }}" class="shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-neutral-800 block">
            <img src="{{ $thumb }}" loading="lazy" alt="" class="w-full h-full object-cover">
        </a>
    @endif

    <div class="min-w-0 flex-1">
        <div class="text-xs text-neutral-500 mb-1">
            {{ $item->sourceLabel() }} &middot; saved {{ $item->created_at->diffForHumans() }}
        </div>

        <a href="{{ $item->url() }}" class="font-medium leading-snug hover:text-orange-300">{{ $item->title }}</a>

        <div class="flex items-center gap-3 mt-2 text-xs text-neutral-400">
            <span>&#9650; {{ \App\Services\Reddit::formatScore($item->score) }}</span>
            <a href="{{ $item->url() }}" class="hover:text-neutral-900">&#128172; {{ \App\Services\Reddit::formatScore($item->num_comments) }} comments</a>

            <button type="button" data-reading-read data-post-id="{{ $item->post_id }}" data-source="{{ $item->source ?? 'reddit' }}"
                    aria-pressed="{{ $item->isRead() ? 'true' : 'false' }}"
                    class="flex items-center gap-1 {{ $item->isRead() ? 'text-emerald-400' : 'hover:text-emerald-400' }}">
                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
                <span data-reading-read-label>{{ $item->isRead() ? 'Read' : 'Mark read' }}</span>
            </button>

            <button type="button" data-reading-remove data-post-id="{{ $item->post_id }}" data-source="{{ $item->source ?? 'reddit' }}"
                    class="ml-auto text-neutral-500 hover:text-red-400">Remove</button>
        </div>
    </div>
</article>
