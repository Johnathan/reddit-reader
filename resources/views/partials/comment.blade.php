@php
    $kind = $comment['kind'] ?? null;
    $data = $comment['data'] ?? [];

    // Load a deeper subtree in-app (never on Reddit) by re-opening the post
    // focused on a given comment id.
    $focusUrl = fn ($commentId) => route('posts.show', array_merge($postArgs, ['comment' => $commentId]));
@endphp

@if ($kind === 'more')
    @php
        // Only a comment-parented "more" can be expanded in-app; skip top-level
        // "more" (parented to the post) rather than link out to Reddit.
        $parent = $data['parent_id'] ?? '';
        $parentComment = str_starts_with($parent, 't1_') ? substr($parent, 3) : null;
    @endphp
    @if (($data['count'] ?? 0) > 0 && $parentComment)
        <div class="py-2 text-xs" style="margin-left: {{ min($depth, 8) * 12 }}px">
            <a href="{{ $focusUrl($parentComment) }}" class="text-orange-400 hover:underline">
                load {{ $data['count'] }} more repl{{ $data['count'] === 1 ? 'y' : 'ies' }} &rarr;
            </a>
        </div>
    @endif
@elseif ($kind === 't1')
    @php
        $tooDeep = $depth >= 8;
        $indent = min($depth, 8) * 12;
        $body = $data['body_html'] ?? '';
        $replies = is_array($data['replies'] ?? null) ? ($data['replies']['data']['children'] ?? []) : [];
        $distinguished = $data['distinguished'] ?? null;
    @endphp
    <div class="border-l border-neutral-800" style="margin-left: {{ $indent }}px; padding-left: 10px;">
        <div data-comment class="py-2">
            <div class="text-xs text-neutral-500 flex items-center gap-1.5 flex-wrap mb-1">
                <button type="button" onclick="toggleComment(this)"
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
