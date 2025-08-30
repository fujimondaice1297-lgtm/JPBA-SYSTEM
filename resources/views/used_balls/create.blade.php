@extends('layouts.app')

@section('content')
<div class="container">
    <h2>使用ボール 登録フォーム</h2>

    {{-- ✅ フラッシュメッセージ --}}
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif

    {{-- ✅ ナビゲーション --}}
    <div class="mt-4 mb-3 d-flex justify-content-between align-items-center">
        <a href="{{ route('approved_balls.index') }}" class="btn btn-secondary">← 承認ボール一覧に戻る</a>
        <div>
            <a href="{{ route('approved_balls.index') }}" class="btn btn-outline-secondary me-2">キャンセル</a>
            <a href="{{ route('used_balls.index') }}" class="btn btn-info text-white">使用ボール一覧へ</a>
        </div>
    </div>

    {{-- ✅ 登録フォーム --}}
    <form method="POST" action="{{ route('used_balls.store') }}">
        @csrf

        {{-- ✅ ライセンス番号 --}}
        <div class="mb-3">
            <label for="license_no">プロボウラー ライセンス番号（例：M00001297）</label>
            <input type="text" name="license_no" class="form-control" placeholder="例：M00001297"
                value="{{ old('license_no', request('license_no')) }}" required>
        </div>

        {{-- ✅ メーカー絞り込み --}}
        <div class="mb-3">
            <label for="manufacturer">メーカーで絞り込み</label>
            <select name="manufacturer" class="form-control" onchange="location.href='{{ route('used_balls.create') }}?manufacturer=' + this.value;">
                <option value="">選択してください</option>
                @foreach($manufacturers as $manufacturer)
                    <option value="{{ $manufacturer }}" {{ request('manufacturer') == $manufacturer ? 'selected' : '' }}>
                        {{ $manufacturer }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- ✅ 使用ボール --}}
        <div class="mb-3">
            <label>使用ボール名</label>
            <select name="approved_ball_id" class="form-control" required>
                <option value="">選択してください</option>
                @foreach($balls as $ball)
                    <option value="{{ $ball->id }}" {{ old('approved_ball_id') == $ball->id ? 'selected' : '' }}>
                        {{ $ball->manufacturer }} - {{ $ball->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- ✅ シリアルナンバー --}}
        <div class="mb-3">
            <label>シリアルナンバー（例：BK00074）</label>
            <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number') }}" required>
        </div>

        {{-- ✅ 検量証番号 --}}
        <div class="mb-3">
            <label>検量証番号（例：123456789）</label>
            <input type="text" name="inspection_number" class="form-control" value="{{ old('inspection_number') }}">
        </div>

        {{-- ✅ 登録日（隠し） --}}
        <input type="hidden" name="registered_at" value="{{ now()->toDateString() }}">

        <button type="submit" class="btn btn-primary">登録する</button>
    </form>
</div>
@endsection
