<span data-removable class="inline-flex items-center gap-1 bg-neutral-800 rounded-full pl-3 pr-1.5 py-1 text-xs">
    <a href="{{ route('subreddit.show', $fs->subreddit) }}" class="hover:text-orange-500">r/{{ $fs->subreddit }}</a>
    <form method="POST" action="{{ route('feeds.subs.destroy', [$feed, $fs->subreddit]) }}" data-ajax data-ajax-remove data-turbo="false">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-neutral-500 hover:text-red-300 w-4 h-4 leading-none" title="Remove">&times;</button>
    </form>
</span>
