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

<div id="post-list">
    @forelse ($posts as $post)
        @include('partials.post-card', ['postData' => $post['data'], 'hideSubreddit' => $hideSubreddit ?? false])
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
