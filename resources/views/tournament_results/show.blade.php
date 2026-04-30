@extends('layouts.app')

@section('content')
@php
    $flowType = (string) ($tournament->result_flow_type ?? '');

    $finalScreen = null;

    if (in_array($flowType, [
        'prelim_to_rr_to_final',
        'prelim_to_quarterfinal_to_rr_to_final',
    ], true)) {
        $finalScreen = [
            'label' => '決勝ステップラダー画面へ',
            'stage' => '決勝',
            'class' => 'btn-dark',
            'note' => 'この大会はラウンドロビン後の決勝ステップラダー方式です。決勝ステップラダー画面から決勝スコア・勝者線・写真枠を確認できます。',
        ];
    } elseif (in_array($flowType, [
        'prelim_to_single_elimination_to_final',
        'prelim_to_quarterfinal_to_single_elimination_to_final',
        'prelim_to_semifinal_to_single_elimination_to_final',
    ], true)) {
        $finalScreen = [
            'label' => 'トーナメント画面へ',
            'stage' => 'トーナメント',
            'class' => 'btn-dark',
            'note' => 'この大会はシングルエリミネーション方式です。トーナメント画面から試合入力・勝ち上がり表示を確認できます。',
        ];
    } elseif (in_array($flowType, [
        'prelim_to_shootout_to_final',
        'prelim_to_quarterfinal_to_shootout_to_final',
        'prelim_to_semifinal_to_shootout_to_final',
    ], true)) {
        $finalScreen = [
            'label' => 'シュートアウト画面へ',
            'stage' => 'シュートアウト',
            'class' => 'btn-dark',
            'note' => 'この大会はシュートアウト方式です。シュートアウト画面からスコア入力・勝ち上がり図式を確認できます。',
        ];
    }

    $finalScreenUrl = $finalScreen
        ? route('scores.result', [
            'tournament_id' => $tournament->id,
            'stage' => $finalScreen['stage'],
            'upto_game' => 1,
            'shifts' => '',
            'gender_filter' => '',
        ])
        : null;
@endphp

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

        @if($finalScreenUrl)
            <a href="{{ $finalScreenUrl }}" class="btn {{ $finalScreen['class'] }}">
                {{ $finalScreen['label'] }}
            </a>
        @endif

        <a href="{{ route('tournaments.results.pdf', $tournament) }}"
           class="btn btn-info"
           target="_blank"
           rel="noopener">
            PDF出力
        </a>

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
        配分未設定の順位は 0 のままです。<br>
        ライセンス番号のないアマチュア選手は、プロフィールには反映せず、この大会成績上の表示名だけを保持します。
        @if($finalScreen)
            <br>
            {{ $finalScreen['note'] }}
        @endif
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
                        ?? $result->amateur_name
                        ?? '不明な選手';

                    $rank = $result->ranking
                        ?? $result->rank
                        ?? $result->position
                        ?? $result->placing
                        ?? $result->result_rank
                        ?? $result->order_no
                        ?? '-';
                @endphp

                <tr>
                    <td>{{ $result->ranking_year }}</td>
                    <td>{{ $name }}</td>
                    <td>{{ $rank }}</td>
                    <td>{{ number_format($result->points ?? 0) }}</td>
                    <td>{{ number_format($result->total_pin ?? 0) }}</td>
                    <td>{{ $result->games ?? '-' }}</td>
                    <td>{{ isset($result->average) ? number_format($result->average, 2) : '-' }}</td>
                    <td>{{ isset($result->prize_money) ? '¥' . number_format($result->prize_money) : '-' }}</td>
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
