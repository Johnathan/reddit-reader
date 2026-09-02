{{--
    On/off chips for the home timeline's source groups. Each chip is its own
    tiny form: Turbo intercepts the POST, follows the redirect back to home,
    and the feed re-renders with the new mix — no full reload.
    Props: $disabledSources - array of switched-off source keys.
--}}
<div class="flex items-center gap-1.5 mb-2 text-xs overflow-x-auto pb-0.5">
    <span class="text-neutral-500 shrink-0">Sources</span>
    @foreach (\App\Http\Controllers\SourceController::SOURCES as $key => $label)
        @php $on = ! in_array($key, $disabledSources, true); @endphp
        <form method="POST" action="{{ route('sources.toggle', $key) }}" class="shrink-0">
            @csrf
            <button type="submit" aria-pressed="{{ $on ? 'true' : 'false' }}"
                    title="{{ $on ? 'Hide' : 'Show' }} {{ $label }} in your feed"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-full border whitespace-nowrap {{ $on
                        ? 'border-orange-500/50 bg-orange-500/10 text-orange-400'
                        : 'border-neutral-800 bg-neutral-900 text-neutral-500 hover:text-neutral-400' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $on ? 'bg-orange-500' : 'bg-neutral-700' }}"></span>
                {{ $label }}
            </button>
        </form>
    @endforeach
</div>
