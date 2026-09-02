@php
    /**
     * One-tap "read later". Deliberately NOT called Save — saving is the
     * separate archive feature. Props:
     *   $p      - the post's data payload
     *   $saved  - bool, already on the reading list?
     *   $label  - bool, show a text label beside the icon (post page)
     *
     * The snapshot travels in data attributes: the card already has the title
     * and thumbnail on screen, so saving needs no round trip to Reddit.
     */
    $saved = $saved ?? false;
    $label = $label ?? false;

    $thumb = (! empty($p['thumbnail']) && str_starts_with($p['thumbnail'], 'http')) ? $p['thumbnail'] : null;

    // Which source this item belongs to, and a community-ish label for it.
    $btnSrc = ($p['_source'] ?? 'reddit') === 'rss' ? $p['source_key'] : ($p['_source'] ?? 'reddit');
    $btnSub = $p['subreddit'] ?? (($p['_source'] ?? '') === 'hn' ? 'hackernews' : ($p['source_key'] ?? ''));
@endphp

<button type="button" data-later
        data-post-id="{{ $p['id'] }}"
        data-source="{{ $btnSrc }}"
        data-url="{{ ($p['_source'] ?? '') === 'rss' ? $p['url'] : '' }}"
        data-subreddit="{{ $btnSub }}"
        data-title="{{ \Illuminate\Support\Str::limit($p['title'] ?? '', 290) }}"
        data-slug="{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug($p['title'] ?? ''), 110, '') }}"
        data-thumbnail="{{ $thumb }}"
        data-score="{{ max(0, (int) ($p['score'] ?? 0)) }}"
        data-comments="{{ max(0, (int) ($p['num_comments'] ?? 0)) }}"
        data-inlist="{{ $saved ? '1' : '0' }}"
        aria-pressed="{{ $saved ? 'true' : 'false' }}"
        aria-label="{{ $saved ? 'Remove from reading list' : 'Add to reading list' }}"
        class="tap-target flex items-center gap-1 {{ $saved ? 'text-orange-500' : 'hover:text-orange-500' }}">
    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="{{ $saved ? 'currentColor' : 'none' }}"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-later-icon>
        <path d="M6 3h12a1 1 0 0 1 1 1v16l-7-4-7 4V4a1 1 0 0 1 1-1z"/>
    </svg>
    @if ($label)
        <span data-later-label>{{ $saved ? 'In list' : 'Read later' }}</span>
    @endif
</button>
