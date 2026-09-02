{{--
    Shared post list + append-based "load more". Props:
      $posts        - Reddit children array
      $error        - error string|null
      $loadMoreUrl  - URL for the next page (with ?after=), or null
      $hideSubreddit- bool (home/feed show the sub; subreddit page hides it)
    Load-more fetches the next page and appends its #post-list items in place,
    so the feed never navigates or jumps.
--}}
@if ($error)
    <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">{{ $error }}</div>
@endif

@php
    // Reddit passes listing children ({kind, data}); the unified timeline
    // passes flat item arrays. Accept both.
    $normalized = collect($posts)->map(fn ($post) => $post['data'] ?? $post);

    // Only offer "expand all" when something on the page can actually expand.
    // RSS items always can — their expando is the extracted article.
    $anyExpandable = $normalized->contains(
        fn ($data) => \App\Services\Reddit::isExpandable($data) || ($data['_source'] ?? null) === 'rss'
    );

    // Read-tracking for exactly the items on this page: one query, keyed by
    // seen_key, so cards can dim read posts and badge new comments. Lives on
    // the server so phone and desktop agree.
    $seenMap = auth()->check()
        ? auth()->user()->seenItems()
            ->whereIn('seen_key', $normalized->map(fn ($d) => \App\Models\SeenItem::keyFor($d)))
            ->get()
            ->keyBy('seen_key')
        : collect();
@endphp

@if ($anyExpandable)
    <div class="flex justify-end -mt-1 mb-1">
        <button type="button" data-expando-all aria-pressed="false"
                class="flex items-center gap-1.5 text-xs text-neutral-500 hover:text-orange-500 px-2 py-1">
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/>
            </svg>
            <span data-expando-all-label>Expand all</span>
        </button>
    </div>
@endif

<div id="post-list">
    @forelse ($normalized as $post)
        @include('partials.post-card', ['postData' => $post, 'hideSubreddit' => $hideSubreddit ?? false, 'seenMap' => $seenMap])
    @empty
        @unless ($error)
            <p class="text-neutral-500 text-sm py-8 text-center">No posts found.</p>
        @endunless
    @endforelse
</div>

@if ($loadMoreUrl)
    <div id="load-more" class="text-center mt-4">
        <a href="{{ $loadMoreUrl }}" data-load-more data-turbo="false"
           class="inline-block px-4 py-2 rounded-lg bg-neutral-800 text-neutral-200 text-sm hover:bg-neutral-700">Load more</a>
    </div>
@endif
