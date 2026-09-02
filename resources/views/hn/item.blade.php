@extends('layouts.app')

@section('subnav-title', 'hacker news')

@section('title', Str::limit(data_get($item, 'title', 'Thread'), 70).' · HN · reader')

@section('content')
    @if ($error)
        <div class="rounded-lg bg-red-900/30 border border-red-700 text-red-200 px-3 py-3 text-sm">{{ $error }}</div>
    @else
        @include('partials.hn-thread', ['p' => $item, 'comments' => $comments])
    @endif
@endsection
