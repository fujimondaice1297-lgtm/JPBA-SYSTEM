@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">承認ボール編集</h2>

    {{-- エラーメッセージ --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 編集フォーム --}}
    <form action="{{ route('approved_balls.update', $ball->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="release_year" class="form-label">発売年度</label>
            <input type="text" name="release_year" class="form-control" value="{{ old('release_year', $ball->release_year) }}">
        </div>

        <div class="mb-3">
            <label for="manufacturer" class="form-label">メーカー名</label>
            <input type="text" name="manufacturer" class="form-control" value="{{ old('manufacturer', $ball->manufacturer) }}">
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">ボール名（英語）</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $ball->name) }}">
        </div>

        <div class="mb-3">
            <label for="name_kana" class="form-label">ボール名（カナ）</label>
            <input type="text" name="name_kana" class="form-control" value="{{ old('name_kana', $ball->name_kana) }}">
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="approved" class="form-check-input" id="approved" value="1" {{ old('approved', $ball->approved) ? 'checked' : '' }}>
            <label class="form-check-label" for="approved">承認済み</label>
        </div>

        <button type="submit" class="btn btn-primary">更新する</button>
        <a href="{{ route('approved_balls.index') }}" class="btn btn-secondary">ボール一覧へ戻る</a>
    </form>
</div>
@endsection
