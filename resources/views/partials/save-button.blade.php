@php
    /**
     * Archive a post or comment permanently. Distinct from the reading list's
     * "Read later" — this one keeps a copy of the content. Props:
     *   $kind      - 'post' | 'comment'
     *   $redditId  - the t3/t1 id (without the prefix)
     *   $subreddit
     *   $postId    - required for comments; the post they live in
     *   $saved     - bool
     *   $label     - bool, show a text label beside the icon
     */
    $label = $label ?? false;
    $saved = $saved ?? false;
    $source = $source ?? 'reddit';
    $itemUrl = $itemUrl ?? null;
@endphp

<button type="button" data-archive
        data-kind="{{ $kind }}"
        data-source="{{ $source }}"
        data-url="{{ $itemUrl }}"
        data-reddit-id="{{ $redditId }}"
        data-subreddit="{{ $subreddit }}"
        data-post-id="{{ $postId ?? '' }}"
        data-archived="{{ $saved ? '1' : '0' }}"
        aria-pressed="{{ $saved ? 'true' : 'false' }}"
        aria-label="{{ $saved ? 'Remove from archive' : 'Save to archive' }}"
        class="tap-target flex items-center gap-1 {{ $saved ? 'text-emerald-400' : 'hover:text-emerald-400' }}">
    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="{{ $saved ? 'currentColor' : 'none' }}"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-archive-icon>
        <path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/>
    </svg>
    @if ($label)
        <span data-archive-label>{{ $saved ? 'Saved' : 'Save' }}</span>
    @endif
</button>
