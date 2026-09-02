@extends('layouts.app')

@section('subnav-title', 'explore')

@section('title', 'Explore · reader')

@section('content')
    <form method="GET" action="{{ route('subs.index') }}" class="mb-5 flex gap-2">
        <input type="text" name="q" placeholder="Search subreddits&hellip;" data-sub-autocomplete="navigate"
               class="flex-1 bg-neutral-900 border border-neutral-700 rounded-lg px-3 py-2 text-sm placeholder-neutral-500 focus:outline-none focus:border-orange-500">
        <button type="submit" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-400">Search</button>
    </form>

    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm mb-4">{{ $error }}</div>
    @endif

    @if (! empty($related))
        <h2 class="text-sm font-semibold text-neutral-400 mb-2">Related to your subs</h2>
        <div class="space-y-2 mb-6">
            @foreach ($related as $name => $seed)
                @include('partials.sub-row', ['name' => $name, 'subscribed' => false, 'seed' => $seed])
            @endforeach
        </div>
    @endif

    <h2 class="text-sm font-semibold text-neutral-400 mb-2">Popular right now</h2>
    <div class="space-y-2">
        @forelse ($popular as $r)
            @php $rd = $r['data']; @endphp
            @include('partials.sub-row', [
                'name' => $rd['display_name'] ?? '',
                'subscribed' => isset($subscribedSet[strtolower($rd['display_name'] ?? '')]),
                'members' => $rd['subscribers'] ?? null,
                'blurb' => $rd['public_description'] ?? null,
            ])
        @empty
            @unless ($error)
                <p class="text-neutral-500 text-sm">Couldn&rsquo;t load popular subreddits.</p>
            @endunless
        @endforelse
    </div>
@endsection
