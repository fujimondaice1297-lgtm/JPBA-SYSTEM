@extends('layouts.app')

@section('content')
<div class="container py-3">
    <h1 class="h4 mb-3">速報 新規作成</h1>

    <form method="POST" action="{{ route('flash_news.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">大会名</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">リンクURL</label>
            <input type="url" name="url" class="form-control" required>
        </div>
        <button class="btn btn-primary">登録</button>
    </form>
</div>
@endsection
