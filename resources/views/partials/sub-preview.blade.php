{{--
    The unfolded peek at a subreddit, returned as a bare fragment by
    SubredditController@preview and injected into the Explore row. Props:
      $subreddit - lowercased name
      $posts     - up to 3 listing children
      $error     - string|null
--}}
@if ($error)
    <p class="text-xs text-red-300 py-1">{{ $error }}</p>
@elseif (empty($posts))
    <p class="text-xs text-neutral-500 py-1">No posts to show.</p>
@else
    <div class="space-y-2">
        @foreach ($posts as $post)
            @php
                $p = $post['data'];
                $thumb = null;
                if (! empty($p['thumbnail']) && str_starts_with($p['thumbnail'], 'http')) {
                    $thumb = $p['thumbnail'];
                }
                $url = route('posts.show', [
                    'subreddit' => $p['subreddit'] ?? $subreddit,
                    'id' => $p['id'],
                    'slug' => \Illuminate\Support\Str::slug($p['title'] ?? ''),
                ]);
            @endphp
            <a href="{{ $url }}" class="flex gap-2 items-start group">
                @if ($thumb)
                    <img src="{{ $thumb }}" loading="lazy" alt=""
                         class="w-10 h-10 rounded object-cover shrink-0 bg-neutral-800 {{ ($p['over_18'] ?? false) ? 'blur-sm' : '' }}">
                @else
                    <span class="w-10 h-10 rounded shrink-0 bg-neutral-800"></span>
                @endif
                <span class="min-w-0 flex-1">
                    <span class="block text-xs leading-snug text-neutral-200 group-hover:text-orange-300 line-clamp-2">{{ $p['title'] ?? '' }}</span>
                    <span class="block text-[11px] text-neutral-500 mt-0.5">
                        &#9650; {{ \App\Services\Reddit::formatScore($p['score'] ?? 0) }}
                        &middot; {{ \App\Services\Reddit::formatScore($p['num_comments'] ?? 0) }} comments
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <a href="{{ route('subreddit.show', $subreddit) }}" class="inline-block mt-2 text-xs text-orange-400 hover:underline">
        Visit r/{{ $subreddit }} &rarr;
    </a>
@endif
