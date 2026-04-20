@extends('layouts.app')

@section('content')
<div class="container">
    <h2>{{ $tournament->year }}年 {{ $tournament->name }}：大会成績一覧</h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-secondary">大会詳細へ戻る</a>

        <a href="{{ route('tournament_results.index') }}" class="btn btn-outline-secondary">大会一覧へ戻る</a>

        <a href="{{ route('tournament_results.create', $tournament) }}" class="btn btn-success">新規登録</a>

        <a href="{{ route('tournament_results.batchCreate', ['tournament_id' => $tournament->id]) }}"
           class="btn btn-warning">一括登録</a>

        <a href="{{ route('tournaments.point_distributions.create', $tournament) }}"
           class="btn btn-outline-primary">ポイント配分</a>

        <a href="{{ route('tournaments.prize_distributions.create', $tournament) }}"
           class="btn btn-outline-success">賞金配分</a>

        <a href="{{ route('tournaments.results.pdf', $tournament) }}" class="btn btn-info">PDF出力</a>

        <form method="POST" action="{{ route('tournaments.results.apply_awards_points', $tournament) }}"
              onsubmit="return confirm('この大会の賞金・ポイントを再計算します。よろしいですか？');">
            @csrf
            <button type="submit" class="btn btn-outline-primary">賞金・ポイント再計算</button>
        </form>

        <form method="POST" action="{{ route('tournaments.results.sync', $tournament) }}"
              onsubmit="return confirm('この大会の優勝者をプロフィールのタイトルへ反映します。続行しますか？');">
            @csrf
            <button type="submit" class="btn btn-danger">タイトル反映</button>
        </form>
    </div>

    <div class="alert alert-info py-2">
        基本の流れは、<strong>ポイント配分 / 賞金配分</strong> → <strong>成績登録</strong> → 配分変更時のみ<strong>賞金・ポイント再計算</strong> → <strong>タイトル反映</strong> です。<br>
        成績の<strong>新規登録・一括登録・編集時</strong>に、設定済みの<strong>ポイント配分</strong>と<strong>賞金配分</strong>が自動反映されます。<br>
        配分未設定の順位は 0 のままです。
    </div>

    @if ($results->isEmpty())
        <p>この大会の成績はまだ登録されていません。</p>
    @else
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>年度</th>
                <th>選手名</th>
                <th>順位</th>
                <th>ポイント</th>
                <th>トータルピン</th>
                <th>G</th>
                <th>アベレージ</th>
                <th>賞金</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($results as $result)
            @php
                $name = optional($result->player)->name_kanji
                        ?? optional($result->bowler)->name_kanji
                        ?? '不明な選手';
                $rank = $result->ranking ?? $result->rank ?? $result->position
                        ?? $result->placing ?? $result->result_rank ?? $result->order_no ?? '-';
            @endphp
            <tr>
                <td>{{ $result->ranking_year }}</td>
                <td>{{ $name }}</td>
                <td>{{ $rank }}</td>
                <td>{{ number_format($result->points ?? 0) }}</td>
                <td>{{ number_format($result->total_pin ?? 0) }}</td>
                <td>{{ $result->games ?? '-' }}</td>
                <td>{{ isset($result->average) ? number_format($result->average, 2) : '-' }}</td>
                <td>{{ isset($result->prize_money) ? '¥'.number_format($result->prize_money) : '-' }}</td>
                <td>
                    <a href="{{ route('results.edit', $result) }}" class="btn btn-primary btn-sm">編集</a>

                    @if(auth()->user()?->isAdmin())
                        <form action="{{ route('admin.tournaments.results.destroy', [$result->tournament_id, $result->id]) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('この成績を削除します。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection