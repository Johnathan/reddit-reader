@extends('layouts.public')

@section('title', Str::limit(data_get($post, 'title', 'Post'), 70).' · r/'.$subreddit.' · reader')

@section('head')
    @if ($post)
        @php
            // Unfurl previews for the chat apps a share link lands in. The
            // description comes from the raw selftext, not the HTML render;
            // media stays out of the card for NSFW posts.
            $ogDescription = trim(Str::limit(preg_replace('/\s+/', ' ', $post['selftext'] ?? ''), 200))
                ?: 'A post on r/'.$subreddit;
            $ogImage = ($post['over_18'] ?? false)
                ? null
                : (isset($post['preview']['images'][0]['source']['url'])
                    ? html_entity_decode($post['preview']['images'][0]['source']['url'])
                    : null);
        @endphp
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="reader">
        <meta property="og:title" content="{{ Str::limit($post['title'] ?? 'Post', 120) }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if ($ogImage)
            <meta property="og:image" content="{{ $ogImage }}">
            <meta name="twitter:card" content="summary_large_image">
        @else
            <meta name="twitter:card" content="summary">
        @endif
    @endif
@endsection

@section('content')
    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm">
            {{ $error }}
        </div>
    @else
        @include('partials.post-detail', [
            'p' => $post,
            'comments' => $comments,
            'focusComment' => $focusComment,
            'publicView' => true,
            'commentSort' => null,
        ])
    @endif
@endsection
