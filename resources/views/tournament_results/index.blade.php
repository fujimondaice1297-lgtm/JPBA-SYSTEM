@extends('layouts.app') {{-- たぶんこのへんで共通レイアウト読み込んでるはず --}}

@section('content')
<div class="container mt-4">
    <h2>大会検索</h2>

    {{-- 検索フォーム --}}
    <form method="GET" action="{{ route('tournament_results.index') }}" class="mb-4">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="year" class="form-control" placeholder="年度"
                    value="{{ request('year') }}">
            </div>
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="大会名"
                    value="{{ request('name') }}">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="{{ route('tournament_results.index') }}" class="btn btn-warning">リセット</a>
                <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
            </div>
        </div>
    </form>

    {{-- 年間ランキングボタン（中央寄せ） --}}
    <div class="text-center mb-4">
        <a href="{{ route('tournament_results.rankings') }}" class="btn btn-outline-primary btn-lg">
            年間ランキングを見る
        </a>
    </div>

    <div class="alert alert-info">
        「成績一覧」は最終成績の確認ページです。予選通算・準決勝通算などの途中成績や、正式成績への反映操作は「正式成績反映」から行ってください。
    </div>

    {{-- ※ここに「タイトル反映」ボタンは置かない（大会ごとの成績ページに置く） --}}

    {{-- 結果テーブル --}}
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>年度</th>
                <th>大会名</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($tournaments as $tournament)
            <tr>
                <td>{{ $tournament->year }}</td>
                <td>{{ $tournament->name }}</td>
                <td>
                    {{-- 詳細ページ（そのままでOKなら残す） --}}
                    <a href="{{ route('tournaments.show', $tournament) }}"
                       class="btn btn-sm btn-outline-primary">詳細を見る</a>

                    {{-- 成績一覧：最終成績の確認用 --}}
                    <a href="{{ route('tournaments.results.index', $tournament) }}" 
                        class="btn btn-sm btn-primary">成績一覧</a>

                    {{-- 途中成績 / 反映管理ページ --}}
                    <a href="{{ route('tournaments.result_snapshots.index', $tournament) }}"
                        class="btn btn-sm btn-outline-success">正式成績反映</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="3">該当する大会はありません。</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
