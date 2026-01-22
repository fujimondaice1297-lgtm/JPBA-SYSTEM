@extends('layouts.app')

@section('content')
<div class="container py-3">
    <h1 class="h4 fw-bold mb-3">大会速報リンク一覧</h1>

    <a href="{{ route('flash_news.create') }}" class="btn btn-primary btn-sm mb-3">＋ 速報新規作成</a>

    @foreach($list as $item)
        <div class="border p-2 rounded mb-2 d-flex justify-content-between align-items-center">
            <div>
                <strong>{{ $item->title }}</strong>
                <a href="{{ route('flash_news.public', $item->id) }}" target="_blank" class="ms-3">リンクへ</a>
            </div>
            <a href="{{ route('flash_news.edit', $item->id) }}" class="btn btn-sm btn-outline-secondary">速報追加編集</a>
        </div>
    @endforeach
</div>
@endsection
