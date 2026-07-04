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

    $seedService = app(\App\Services\ProBowlerSeedService::class);
    $seedMap = [];

    try {
        $seedMap = $seedService->seedMapForTournament((int) $tournament->id);
    } catch (\Throwable $e) {
        $seedMap = [];
    }

    $seedLicenseKey = function ($license): string {
        return strtoupper(preg_replace('/\s+/u', '', trim((string) $license)) ?? trim((string) $license));
    };

    $isSeedResult = function ($result, ?string $licenseNo = null) use ($seedMap, $seedLicenseKey): bool {
        $proBowlerId = $result->pro_bowler_id
            ?? optional($result->player)->id
            ?? optional($result->bowler)->id
            ?? null;

        if ($proBowlerId !== null && isset($seedMap['pro_bowler:' . (int) $proBowlerId])) {
            return true;
        }

        $licenseCandidates = [
            $licenseNo,
            $result->pro_bowler_license_no ?? null,
            optional($result->player)->license_no ?? null,
            optional($result->bowler)->license_no ?? null,
        ];

        foreach ($licenseCandidates as $candidate) {
            $key = $seedLicenseKey($candidate);
            if ($key !== '' && isset($seedMap['license:' . $key])) {
                return true;
            }
        }

        return false;
    };

    $formatSeedLicense = function (?string $licenseNo, bool $isSeed = false) use ($seedService): string {
        $display = $seedService->formatLicenseForPdf($licenseNo, $isSeed);

        return $display === '' ? '-' : $display;
    };
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
        シーズントライアルでは、最終順位1〜8位に固定の<strong>入賞ポイント</strong>を自動加算します。<br>
        配分未設定の順位は 0 のままです。<br>
        ライセンス番号のないアマチュア選手は、プロフィールには反映せず、この大会成績上の表示名だけを保持します。
        @if($finalScreen)
            <br>
            {{ $finalScreen['note'] }}
        @endif
    </div>

    @if(isset($resultIndexSnapshot) && $resultIndexSnapshot)
        <div class="alert alert-warning py-2">
            正式最終成績の登録件数よりスナップショットの人数が多いため、一覧確認用に
            「{{ $resultIndexSnapshot->result_name ?? $resultIndexSnapshot->result_code ?? '成績スナップショット' }}」を表示しています。
        </div>
    @endif

    @if ($results->isEmpty())
        <p>この大会の成績はまだ登録されていません。</p>
    @else
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>順位</th>
                    <th>選手名</th>
                    <th>ライセンスNo</th>
                    <th>合計ポイント</th>
                    <th>入賞ポイント</th>
                    <th>ステップポイント</th>
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
                        ?? $result->display_name
                        ?? $result->amateur_name
                        ?? '不明な選手';

                    $rank = $result->ranking
                        ?? $result->rank
                        ?? $result->position
                        ?? $result->placing
                        ?? $result->result_rank
                        ?? $result->order_no
                        ?? '-';

                    $rawLicense = $result->pro_bowler_license_no
                        ?? optional($result->player)->license_no
                        ?? optional($result->bowler)->license_no
                        ?? null;

                    $licenseDisplay = $formatSeedLicense($rawLicense, $isSeedResult($result, $rawLicense));
                @endphp

                <tr>
                    <td>{{ $rank }}</td>
                    <td>{{ $name }}</td>
                    <td>{{ $licenseDisplay }}</td>
                    <td>{{ number_format($result->points ?? 0) }}</td>
                    <td>{{ number_format($result->award_points ?? 0) }}</td>
                    <td>{{ number_format($result->step_points ?? 0) }}</td>
                    <td>{{ number_format($result->total_pin ?? 0) }}</td>
                    <td>{{ $result->games ?? '-' }}</td>
                    <td>{{ isset($result->average) ? number_format($result->average, 2) : '-' }}</td>
                    <td>{{ isset($result->prize_money) ? '¥' . number_format($result->prize_money) : '-' }}</td>
                    <td>
                        @if(empty($result->is_snapshot_preview) && !empty($result->id))
                            <a href="{{ route('results.edit', $result) }}" class="btn btn-primary btn-sm">編集</a>
                        @else
                            <span class="text-muted small">確認用</span>
                        @endif

                        @if(empty($result->is_snapshot_preview) && auth()->user()?->isAdmin())
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
