@php $url = $item->url(); @endphp

<article data-saved-row data-id="{{ $item->id }}" class="border-b border-neutral-800 py-3">
    <div class="flex items-center gap-1.5 text-xs text-neutral-500 mb-1 flex-wrap">
        <span class="px-1.5 py-0.5 rounded {{ $item->isComment() ? 'bg-neutral-800 text-neutral-300' : 'bg-emerald-400/15 text-emerald-400' }}">
            {{ $item->isComment() ? 'comment' : 'post' }}
        </span>
        <a href="{{ route('subreddit.show', $item->subreddit) }}" class="text-neutral-300 hover:text-neutral-900 font-medium">r/{{ $item->subreddit }}</a>
        <span>&middot;</span>
        <span>u/{{ $item->author ?? '[deleted]' }}</span>
        <span>&middot;</span>
        <span>saved {{ $item->created_at->diffForHumans() }}</span>
        @if ($item->folder)
            <span class="px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-400">{{ $item->folder->name }}</span>
        @endif
    </div>

    @if ($item->isComment())
        @if ($item->post_title)
            <div class="text-xs text-neutral-500 mb-1">on &ldquo;{{ $item->post_title }}&rdquo;</div>
        @endif
    @else
        <a href="{{ $url }}" class="font-medium leading-snug hover:text-orange-300">{{ $item->title }}</a>
    @endif

    @if ($item->body_html)
        {{-- The archived copy. Rendered from our own column, never re-fetched,
             so this still reads after the original is deleted. --}}
        <div class="reddit-html text-[15px] leading-relaxed text-neutral-200 mt-1 max-h-64 overflow-y-auto">
            {!! $item->body_html !!}
        </div>
    @endif

    @if ($item->note)
        <p class="mt-2 text-xs text-neutral-400 border-l-2 border-orange-500/50 pl-2">{{ $item->note }}</p>
    @endif

    @if ($item->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1 mt-2">
            @foreach ($item->tags as $tag)
                <a href="{{ route('saved.index', ['tag' => $tag->slug]) }}"
                   class="px-1.5 py-0.5 rounded-full bg-neutral-800 text-neutral-400 text-[11px] hover:text-orange-400">#{{ $tag->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="flex items-center gap-3 mt-2 text-xs text-neutral-400">
        <span>&#9650; {{ \App\Services\Reddit::formatScore($item->score) }}</span>
        @if ($url)
            <a href="{{ $url }}" class="hover:text-neutral-900">Open in thread</a>
        @endif

        <details class="relative ml-auto">
            <summary class="list-none cursor-pointer hover:text-orange-500 select-none">Organise</summary>
            <div class="absolute right-0 mt-1 z-20 w-60 rounded-lg border border-neutral-700 bg-neutral-900 shadow-xl p-2 space-y-2">
                <select data-organise="folder" data-id="{{ $item->id }}"
                        class="w-full bg-neutral-950 border border-neutral-700 rounded px-2 py-1 text-xs">
                    <option value="">No folder</option>
                    @foreach ($myFolders as $folder)
                        <option value="{{ $folder->id }}" @selected($item->folder_id === $folder->id)>{{ $folder->name }}</option>
                    @endforeach
                    <option value="__new__">+ New folder&hellip;</option>
                </select>
                <input type="text" data-new-folder data-id="{{ $item->id }}" hidden maxlength="50"
                       placeholder="Folder name &mdash; Enter to create"
                       class="w-full bg-neutral-950 border border-orange-500/60 rounded px-2 py-1 text-xs placeholder-neutral-500">

                <input type="text" data-organise="tags" data-id="{{ $item->id }}"
                       value="{{ $item->tags->pluck('name')->join(', ') }}" placeholder="tags, comma separated"
                       class="w-full bg-neutral-950 border border-neutral-700 rounded px-2 py-1 text-xs">
                <textarea data-organise="note" data-id="{{ $item->id }}" rows="2" placeholder="Your note&hellip;"
                          class="w-full bg-neutral-950 border border-neutral-700 rounded px-2 py-1 text-xs">{{ $item->note }}</textarea>
                <p class="text-[10px] text-neutral-600">Saved when you click away.</p>
            </div>
        </details>

        <button type="button" data-archive data-kind="{{ $item->kind }}" data-reddit-id="{{ $item->reddit_id }}"
                data-subreddit="{{ $item->subreddit }}" data-post-id="{{ $item->post_id }}" data-archived="1"
                class="text-neutral-500 hover:text-red-400">Remove</button>
    </div>
</article>
