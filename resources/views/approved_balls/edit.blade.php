@extends('layouts.app')

@section('content')
<div class="container" style="max-width:900px">
    <div class="d-flex align-items-center gap-3 mb-4">
        <img src="{{ $ball->image_url }}" alt="{{ $ball->name }}"
             width="96" height="96"
             style="object-fit:contain;background:#f8f9fa;border-radius:.5rem">
        <div>
            <h2 class="mb-1">ボール情報の編集</h2>
            <div class="text-muted">{{ $ball->manufacturer }} / {{ $ball->brand ?: 'ブランド未設定' }}</div>
        </div>
    </div>

    <form action="{{ route('approved_balls.update', $ball) }}" method="POST" class="card card-body">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-4">
                <label for="release_date" class="form-label">発売日</label>
                <input id="release_date" type="date" name="release_date" class="form-control"
                       value="{{ old('release_date', optional($ball->release_date)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label for="manufacturer" class="form-label">メーカー</label>
                <input id="manufacturer" type="text" name="manufacturer" class="form-control" required
                       value="{{ old('manufacturer', $ball->manufacturer) }}">
            </div>
            <div class="col-md-4">
                <label for="brand" class="form-label">ブランド</label>
                <input id="brand" type="text" name="brand" class="form-control"
                       value="{{ old('brand', $ball->brand) }}">
            </div>
            <div class="col-md-6">
                <label for="name" class="form-label">ボール名</label>
                <input id="name" type="text" name="name" class="form-control" required
                       value="{{ old('name', $ball->name) }}">
            </div>
            <div class="col-md-6">
                <label for="name_kana" class="form-label">カナ名</label>
                <input id="name_kana" type="text" name="name_kana" class="form-control"
                       value="{{ old('name_kana', $ball->name_kana) }}">
            </div>
            <div class="col-md-6">
                <label for="catalog_status" class="form-label">掲載状態</label>
                <select id="catalog_status" name="catalog_status" class="form-select" required>
                    <option value="listed" @selected(old('catalog_status', $ball->catalog_status) === 'listed')>掲載中</option>
                    <option value="archive" @selected(old('catalog_status', $ball->catalog_status) === 'archive')>アーカイブ</option>
                    <option value="manual" @selected(old('catalog_status', $ball->catalog_status) === 'manual')>手動登録</option>
                    <option value="hidden" @selected(old('catalog_status', $ball->catalog_status) === 'hidden')>非表示</option>
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input id="approved" type="checkbox" name="approved" value="1"
                           class="form-check-input"
                           @checked(old('approved', $ball->approved))>
                    <label for="approved" class="form-check-label">
                        JPBA大会で選択可能
                    </label>
                    <div class="form-text">アブプールリスト反映後に設定します。</div>
                </div>
            </div>
        </div>

        @if($ball->source_url)
            <div class="alert alert-light border mt-3 mb-0">
                取得元：
                <a href="{{ $ball->source_url }}" target="_blank" rel="noopener">{{ $ball->source_url }}</a>
            </div>
        @endif

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">保存</button>
            <a href="{{ route('approved_balls.index') }}" class="btn btn-secondary">一覧へ戻る</a>
        </div>
    </form>
</div>
@endsection
