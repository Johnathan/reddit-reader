@php
    $sorts = ['hot' => 'Hot', 'new' => 'New', 'top' => 'Top', 'rising' => 'Rising'];
    $currentT = request('t', 'day');
@endphp

<div class="flex items-center gap-1.5 text-sm mb-3 overflow-x-auto pb-1">
    @foreach ($sorts as $key => $label)
        <a href="{{ route($routeName, array_merge($routeParams, ['sort' => $key])) }}"
           class="px-3 py-1.5 rounded-full whitespace-nowrap {{ $sort === $key ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-neutral-300 hover:bg-neutral-700' }}">
            {{ $label }}
        </a>
    @endforeach

    @if ($sort === 'top')
        <span class="text-neutral-700">&middot;</span>
        @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'all' => 'All'] as $tKey => $tLabel)
            <a href="{{ route($routeName, array_merge($routeParams, ['sort' => 'top', 't' => $tKey])) }}"
               class="px-2 py-1 rounded-full whitespace-nowrap text-xs {{ $currentT === $tKey ? 'bg-neutral-700 text-white' : 'bg-neutral-900 text-neutral-500 hover:bg-neutral-800' }}">
                {{ $tLabel }}
            </a>
        @endforeach
    @endif
</div>
