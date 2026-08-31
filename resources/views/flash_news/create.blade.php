@extends('layouts.app')

@section('content')
<div class="container py-3">
    <h1 class="h4 mb-1">外部速報リンク 新規登録</h1>
    <p class="text-muted">特設速報サイトなど、新サイト外にあるURLを登録します。</p>

    <form method="POST" action="{{ route('flash_news.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">大会名</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $title ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">リンクURL</label>
            <input type="url" name="url" class="form-control" required>
        </div>
        <button class="btn btn-primary">登録</button>
        <a href="{{ route('flash_news.index') }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
