@extends('layouts.app')

@section('content')
<div class="container py-3">
    <h1 class="h4 mb-3">外部速報リンク 編集</h1>

    <form method="POST" action="{{ route('flash_news.update', $item->id) }}">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label">大会名</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $item->title) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">リンクURL</label>
            <input type="url" name="url" class="form-control" value="{{ $item->url }}" required>
        </div>
        <button class="btn btn-primary">更新</button>
        <a href="{{ route('flash_news.index') }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
