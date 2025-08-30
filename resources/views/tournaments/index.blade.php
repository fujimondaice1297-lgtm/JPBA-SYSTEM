@extends('layouts.app')

@section('content')
<h1>大会一覧</h1>

{{-- 検索フォーム --}}
<form method="GET" action="{{ route('tournaments.index') }}" class="mb-4">
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" name="name" value="{{ request('name') }}" placeholder="大会名" class="form-control" style="width: 200px;">
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" style="width: 180px;">
        <input type="text" name="venue_name" value="{{ request('venue_name') }}" placeholder="会場名" class="form-control" style="width: 200px;">
        <button type="submit" class="btn btn-primary">検索</button>
        <a href="{{ route('tournaments.index') }}" class="btn btn-warning">リセット</a>
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-primary btn-sm">
            大会使用ボール登録へ
        </a>
        <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
            使用ボール一覧（管理）
        </a>
        <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
    </div>
</form>

{{-- 新規登録ボタン --}}
<a href="{{ route('tournaments.create') }}" class="btn btn-success mb-3">新規登録</a>

{{-- 一覧テーブル --}}
<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>大会名</th>
            <th>開催期間</th>
            <th>申込期間</th>
            <th>会場名</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        @forelse($tournaments as $tournament)
            <tr>
                <td>{{ $tournament->id }}</td>
                <td>{{ $tournament->name }}</td>
                <td>{{ optional($tournament->start_date)->format('Y-m-d') }}～
                    {{ optional($tournament->end_date)->format('Y-m-d') }}</td>
                <td>
                    {{ optional($tournament->entry_start)->format('Y-m-d') }}～
                    {{ optional($tournament->entry_end)->format('Y-m-d') }}</td>
                <td>{{ $tournament->venue_name }}</td>
                <td>
                    <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-info btn-sm">詳細</a>
                    <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-primary btn-sm">編集</a>
                    <a href="{{ route('tournament_results.create', $tournament->id) }}" class="btn btn-info btn-sm">成績入力</a>
                    <a href="{{ route('tournaments.prize_distributions.index', $tournament->id) }}" class="btn btn-warning btn-sm">賞金配分</a>
                    <a href="{{ route('tournaments.point_distributions.index', $tournament->id) }}" class="btn btn-danger btn-sm">ポイント配分</a>

                </td>
            </tr>
        @empty
            <tr><td colspan="5">該当する大会はありません。</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
