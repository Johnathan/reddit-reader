@php
    $kind = $comment['kind'] ?? null;
    $data = $comment['data'] ?? [];

    // Load a deeper subtree in-app (never on Reddit/HN) by re-opening the
    // thread focused on a given comment id. On HN any comment id is itself a
    // thread root, so the focus URL is just that item's page.
    $threadSource = $postArgs['source'] ?? 'reddit';
    $focusUrl = $threadSource === 'hn'
        ? fn ($commentId) => route('hn.item', $commentId)
        : fn ($commentId) => route('posts.show', array_merge(array_diff_key($postArgs, ['source' => 1]), ['comment' => $commentId]));

    // Replies render INSIDE their parent, so each level's indent is already
    // offset by every ancestor — it must be a flat step, not depth * step, or
    // the gap compounds and deep threads march off the right of the screen.
    $indent = $depth === 0 ? 0 : 12;
@endphp

@if ($kind === 'more')
    @php
        // Only a comment-parented "more" can be expanded in-app; skip top-level
        // "more" (parented to the post) rather than link out to Reddit.
        $parent = $data['parent_id'] ?? '';
        $parentComment = str_starts_with($parent, 't1_') ? substr($parent, 3) : null;
    @endphp
    @if (($data['count'] ?? 0) > 0 && $parentComment)
        <div class="py-2 text-xs" style="margin-left: {{ $indent }}px; padding-left: 10px;">
            <a href="{{ $focusUrl($parentComment) }}" class="text-orange-400 hover:underline">
                load {{ $data['count'] }} more repl{{ $data['count'] === 1 ? 'y' : 'ies' }} &rarr;
            </a>
        </div>
    @endif
@elseif ($kind === 't1')
    @php
        $tooDeep = $depth >= 8;
        $body = $data['body_html'] ?? '';
        $replies = is_array($data['replies'] ?? null) ? ($data['replies']['data']['children'] ?? []) : [];
        $distinguished = $data['distinguished'] ?? null;
    @endphp
    <div data-comment-wrap class="relative border-l border-neutral-800" style="margin-left: {{ $indent }}px; padding-left: 10px;">
        {{-- The thread line itself collapses the comment, like Reddit. It sits
             in the 10px gutter so it never overlaps the comment's own text. --}}
        <button type="button" data-collapse tabindex="-1" aria-label="Collapse thread"
                class="group absolute top-0 bottom-0 w-[11px] cursor-pointer" style="left: -1px;">
            <span class="block w-px h-full group-hover:bg-orange-500 transition-colors"></span>
        </button>

        <div data-comment class="py-2">
            <div data-collapse class="comment-head cursor-pointer text-xs text-neutral-500 flex items-center gap-1.5 flex-wrap mb-1">
                <button type="button" data-collapse-btn
                        class="text-neutral-600 hover:text-neutral-900 w-4">[-]</button>
                <span class="{{ $distinguished === 'moderator' ? 'text-emerald-400 font-medium' : 'font-medium text-neutral-300' }}">
                    u/{{ $data['author'] ?? '[deleted]' }}
                </span>
                @if ($distinguished)
                    <span class="text-blue-400 text-[10px] uppercase font-semibold">{{ $distinguished }}</span>
                @endif
                <span>&middot;</span>
                <span>&#9650; {{ \App\Services\Reddit::formatScore($data['score'] ?? 0) }}</span>
                <span>&middot;</span>
                <span>{{ isset($data['created_utc']) ? \Carbon\Carbon::createFromTimestamp($data['created_utc'])->diffForHumans() : '' }}</span>
                @if ($data['stickied'] ?? false)
                    <span class="text-emerald-400 font-medium">pinned</span>
                @endif

                {{-- Archive this comment on its own. Sits outside the header's
                     collapse trigger, or tapping it would fold the thread. --}}
                @unless ($publicView ?? false)
                    <span class="ml-auto" onclick="event.stopPropagation()">
                        @include('partials.save-button', [
                            'kind' => 'comment',
                            'redditId' => $data['id'],
                            'subreddit' => $data['subreddit'] ?? ($postArgs['subreddit'] ?? ''),
                            'source' => $threadSource,
                            'postId' => $postArgs['id'] ?? null,
                            'saved' => isset($myArchivedIds[$data['id']]),
                        ])
                    </span>
                @endunless
            </div>

            <div class="comment-body-wrap">
                <div class="comment-collapsible">
                    <div class="reddit-html text-[15px] leading-relaxed text-neutral-200">
                        {!! \App\Services\Reddit::sanitizeHtml($body) !!}
                    </div>

                    @if ($tooDeep && !empty($replies))
                        <a href="{{ $focusUrl($data['id']) }}" class="text-xs text-orange-400 hover:underline">
                            continue this thread &rarr;
                        </a>
                    @elseif (!empty($replies))
                        <div class="mt-1">
                            @foreach ($replies as $reply)
                                @include('partials.comment', ['comment' => $reply, 'depth' => $depth + 1, 'postArgs' => $postArgs])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
