@php
    /**
     * A subscribe/unsubscribe toggle that flips in place via fetch — no page
     * reload, no scroll jump. Props:
     *   $subreddit         - name
     *   $subscribed        - bool (current state)
     *   $subscribedLabel   - label to show when subscribed ("Subscribed" or "Unsubscribe")
     */
    $subreddit = strtolower($subreddit);
    $subscribed = $subscribed ?? false;
    $subscribedLabel = $subscribedLabel ?? 'Subscribed';

    $base = 'text-xs px-3 py-1.5 rounded-full whitespace-nowrap transition-colors disabled:opacity-60';
    $subClass = 'bg-orange-500 text-white hover:bg-orange-400';
    $unsubClass = 'bg-neutral-800 text-neutral-300 hover:bg-red-900 hover:text-red-200';
@endphp

<button type="button"
        data-sub-toggle
        data-subreddit="{{ $subreddit }}"
        data-subscribed="{{ $subscribed ? '1' : '0' }}"
        data-label-sub="Subscribe"
        data-label-unsub="{{ $subscribedLabel }}"
        data-class-base="{{ $base }}"
        data-class-sub="{{ $subClass }}"
        data-class-unsub="{{ $unsubClass }}"
        class="{{ $base }} {{ $subscribed ? $unsubClass : $subClass }}">
    {{ $subscribed ? $subscribedLabel : 'Subscribe' }}
</button>
