@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">承認ボールリスト</h2>

    {{-- ボタンたち --}}
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center">
        <div class="mb-2">
            {{-- ① 新規登録ボタン --}}
            <a href="{{ route('approved_balls.create') }}" class="btn btn-success me-2">+ 新規登録（最大10件）</a>

            {{-- ④ CSVインポートボタン --}}
            <a href="{{ route('approved_balls.import') }}" class="btn btn-outline-secondary me-2">CSVインポート</a>

            {{-- ⑤ インデックスへ戻るボタン --}}
            <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
        </div>
    </div>

    {{-- 検索フォーム --}}
    <form method="GET" action="{{ route('approved_balls.index') }}" class="row mb-4 g-2">
        <div class="col-md-3">
            <label for="manufacturer">メーカー名</label>
            <select name="manufacturer" class="form-control">
                <option value="">選んでください</option>
                @foreach($manufacturers as $b)
                    <option value="{{ $b }}" {{ request('manufacturer') == $b ? 'selected' : '' }}>{{ $b }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label for="name">ボール名（英語 or カナ）</label>
            <input type="text" name="name" class="form-control" value="{{ request('name') }}">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">検索</button>
            <a href="{{ route('approved_balls.index') }}" class="btn btn-warning">リセット</a>
        </div>
    </form>

    {{-- 結果テーブル --}}
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>発売年度</th>
                <th>メーカー名</th>
                <th>ボール名</th>
                <th>和名</th>
                <th>承認</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($balls as $ball)
            <tr>
                <td>{{ $ball->id }}</td>
                <td>{{ $ball->release_year }}</td>
                <td>{{ $ball->manufacturer }}</td>
                <td>{{ $ball->name }}</td>
                <td>{{ $ball->name_kana }}</td>
                <td>{{ $ball->approved ? '〇' : '' }}</td>
                <td class="d-flex gap-2">
                    {{-- ② 編集ボタン --}}
                    <a href="{{ route('approved_balls.edit', $ball->id) }}" class="btn btn-sm btn-outline-primary">編集</a>

                    {{-- ③ 削除ボタン --}}
                    <form action="{{ route('approved_balls.destroy', $ball->id) }}" method="POST" onsubmit="return confirm('本当に削除しますか？')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">削除</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">データがありません。</td></tr>
        @endforelse
        </tbody>
    </table>

    {{-- ページネーション --}}
    <div class="d-flex justify-content-center">
        {{ $balls->appends(request()->query())->links() }}
    </div>
</div>
@endsection
