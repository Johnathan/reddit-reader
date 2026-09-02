@extends('layouts.app')

@section('subnav-title', 'r/'.$subreddit)

@section('title', Str::limit(data_get($post, 'title', 'Post'), 70).' · r/'.$subreddit.' · reader')

@section('content')
    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm">
            {{ $error }}
        </div>
    @else
        @include('partials.post-detail', ['p' => $post, 'comments' => $comments, 'focusComment' => $focusComment])
    @endif
@endsection
