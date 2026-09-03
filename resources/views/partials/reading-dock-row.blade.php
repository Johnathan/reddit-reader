{{-- One line on the sticky pad. Kept in its own partial so the JS can mirror
     its shape when adding a row without a page load. --}}
<div data-dock-row data-post-id="{{ $item->post_id }}"
     class="flex items-start gap-2 px-3 py-2 hover:bg-neutral-800/60 {{ $item->isRead() ? 'opacity-50' : '' }}">
    <a href="{{ $item->url() }}" class="min-w-0 flex-1 group">
        <span class="block text-xs leading-snug text-neutral-200 group-hover:text-orange-500 line-clamp-2">{{ $item->title }}</span>
        <span class="block text-[11px] text-neutral-500 mt-0.5">{{ $item->sourceLabel() }} &middot; {{ \App\Services\Reddit::formatScore($item->num_comments) }} comments</span>
    </a>
    <button type="button" data-dock-remove data-post-id="{{ $item->post_id }}" data-source="{{ $item->source ?? 'reddit' }}"
            aria-label="Remove from reading list"
            class="shrink-0 p-1 -mr-1 text-neutral-600 hover:text-red-400">
        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
    </button>
</div>
