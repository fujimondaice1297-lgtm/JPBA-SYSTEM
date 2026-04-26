@extends('layouts.app')

@section('content')
@php
    $data = (array) ($singleElimination ?? []);
    $meta = (array) ($data['meta'] ?? []);
    $bracket = (array) ($data['bracket'] ?? []);
    $summary = (array) ($bracket['summary'] ?? []);
    $rounds = array_values((array) ($bracket['rounds'] ?? []));
    $seedRows = array_values((array) ($data['seed_rows'] ?? []));
    $missingSeedSnapshot = (bool) ($data['missing_seed_snapshot'] ?? false);

    $shiftValue = trim((string) request('shifts', $shifts ?? ''));
    $genderValue = trim((string) request('gender_filter', $gender_filter ?? ''));
    $isPublic = ((int) request('public', 0) === 1);

    $snapshotIndexUrl = request('tournament_id')
        ? route('tournaments.result_snapshots.index', ['tournament' => request('tournament_id')])
        : null;

    $slotName = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'bye') {
            return 'BYE';
        }

        return trim((string) ($slot['display_name'] ?? $slot['label'] ?? '—')) ?: '—';
    };

    $slotSub = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'seed') {
            $parts = [];

            if (!empty($slot['pro_bowler_license_no'])) {
                $parts[] = (string) $slot['pro_bowler_license_no'];
            }

            if (isset($slot['total_pin']) && $slot['total_pin'] !== null && $slot['total_pin'] !== '') {
                $parts[] = '通算 ' . number_format((int) $slot['total_pin']) . ' pin';
            }

            return implode(' / ', $parts);
        }

        if ($type === 'winner') {
            return '勝ち上がり枠';
        }

        return '';
    };
@endphp

@if($isPublic)
<style>
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }

    body {
        padding-top: 0 !important;
    }

    .container, .container-fluid {
        margin-top: 0 !important;
    }
</style>
@endif

<style>
    .se-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fff;
    }

    .se-title {
        font-size: 1.5rem;
        font-weight: 800;
    }

    .se-sub {
        color: #6b7280;
        margin-top: .25rem;
        margin-bottom: 1rem;
    }

    .se-toolbar {
        display: flex;
        gap: .6rem;
        align-items: center;
        flex-wrap: wrap;
        margin: 0 0 1rem;
    }

    .se-nav {
        display: flex;
        gap: .6rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .se-nav a {
        text-decoration: none;
        padding: .35rem .6rem;
        border: 1px solid #d1d5db;
        border-radius: .5rem;
        background: #f9fafb;
    }

    .se-summary {
        display: grid;
        grid-template-columns: repeat(6, minmax(110px, 1fr));
        gap: .6rem;
        margin-bottom: 1rem;
    }

    .se-summary-box {
        border: 1px solid #d1d5db;
        border-radius: .6rem;
        padding: .65rem .75rem;
        background: #f9fafb;
    }

    .se-summary-label {
        font-size: .82rem;
        color: #6b7280;
    }

    .se-summary-value {
        font-size: 1.1rem;
        font-weight: 800;
        margin-top: .15rem;
    }

    .se-table-wrap {
        overflow-x: auto;
    }

    .se-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 880px;
    }

    .se-table th,
    .se-table td {
        border: 1px solid #d1d5db;
        padding: .45rem .5rem;
        text-align: center;
        vertical-align: middle;
    }

    .se-table th {
        background: #f3f4f6;
        font-weight: 700;
        white-space: nowrap;
    }

    .se-left {
        text-align: left !important;
        white-space: nowrap;
    }

    .se-round-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(260px, 1fr));
        gap: .9rem;
        align-items: start;
    }

    .se-round {
        border: 1px solid #d1d5db;
        border-radius: .75rem;
        background: #f9fafb;
        padding: .75rem;
    }

    .se-round-title {
        font-weight: 800;
        margin-bottom: .55rem;
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        align-items: baseline;
    }

    .se-loser-rank {
        font-size: .82rem;
        color: #6b7280;
        font-weight: 600;
    }

    .se-match {
        border: 1px solid #cbd5e1;
        border-radius: .65rem;
        background: #fff;
        padding: .65rem;
        margin-bottom: .65rem;
    }

    .se-match-head {
        font-weight: 700;
        margin-bottom: .5rem;
        color: #374151;
    }

    .se-slot {
        border: 1px solid #e5e7eb;
        border-radius: .5rem;
        padding: .5rem;
        background: #fff;
    }

    .se-slot + .se-slot {
        margin-top: .45rem;
    }

    .se-slot-bye {
        background: #f3f4f6;
        color: #6b7280;
    }

    .se-seed {
        display: inline-block;
        min-width: 3rem;
        font-weight: 800;
        color: #111827;
    }

    .se-name {
        font-weight: 700;
    }

    .se-mini {
        margin-top: .15rem;
        font-size: .82rem;
        color: #6b7280;
    }

    .se-vs {
        text-align: center;
        font-size: .82rem;
        color: #6b7280;
        margin: .25rem 0;
    }

    .se-note {
        color: #374151;
        font-size: .92rem;
        margin-top: .5rem;
    }

    @media (max-width: 1199.98px) {
        .se-round-grid {
            grid-template-columns: repeat(2, minmax(260px, 1fr));
        }

        .se-summary {
            grid-template-columns: repeat(3, minmax(110px, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .se-round-grid {
            grid-template-columns: 1fr;
        }

        .se-summary {
            grid-template-columns: repeat(2, minmax(110px, 1fr));
        }
    }
</style>

<div class="se-card">
    <div class="se-title">{{ $tournament_name }} / トーナメント</div>
    <div class="se-sub">
        進出元：{{ $meta['seed_source_name'] ?? '—' }}
        ／ 進出人数：{{ (int) ($meta['qualifier_count'] ?? 0) }}名
        ／ 方式：{{ $meta['seed_policy_name'] ?? '—' }}
    </div>

    @unless($isPublic)
    <div class="se-toolbar">
        <a class="btn btn-outline-secondary" href="{{ url('/scores/input') }}">速報入力ページへ</a>

        @if($snapshotIndexUrl)
            <a class="btn btn-outline-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
        @endif

        <a class="btn btn-outline-secondary" href="{{ route('scores.result', array_filter([
            'tournament_id' => request('tournament_id'),
            'stage' => 'トーナメント',
            'upto_game' => $upto_game ?? request('upto_game', 1),
            'shifts' => $shiftValue,
            'gender_filter' => $genderValue,
            'public' => 1,
        ])) }}">共有URL(公開)</a>
    </div>
    @endunless

    <div class="se-summary">
        <div class="se-summary-box">
            <div class="se-summary-label">進出人数</div>
            <div class="se-summary-value">{{ number_format((int) ($summary['qualifier_count'] ?? 0)) }}</div>
        </div>
        <div class="se-summary-box">
            <div class="se-summary-label">ブラケット枠</div>
            <div class="se-summary-value">{{ number_format((int) ($summary['bracket_size'] ?? 0)) }}</div>
        </div>
        <div class="se-summary-box">
            <div class="se-summary-label">ラウンド数</div>
            <div class="se-summary-value">{{ number_format((int) ($summary['round_count'] ?? 0)) }}</div>
        </div>
        <div class="se-summary-box">
            <div class="se-summary-label">BYE</div>
            <div class="se-summary-value">{{ number_format((int) ($summary['bye_count'] ?? 0)) }}</div>
        </div>
        <div class="se-summary-box">
            <div class="se-summary-label">実試合数</div>
            <div class="se-summary-value">{{ number_format((int) ($summary['actual_match_count'] ?? 0)) }}</div>
        </div>
        <div class="se-summary-box">
            <div class="se-summary-label">seed行数</div>
            <div class="se-summary-value">{{ number_format((int) ($meta['seed_row_count'] ?? 0)) }}</div>
        </div>
    </div>

    @if($missingSeedSnapshot)
        <div class="alert alert-warning">
            トーナメント進出元 snapshot（{{ $meta['seed_source_result_code'] ?? '—' }} / {{ $meta['seed_source_name'] ?? '—' }}）が見つかりません。<br>
            先に進出元ステージの正式成績反映を実行してください。

            @unless($isPublic)
                @if($snapshotIndexUrl)
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
                    </div>
                @endif
            @endunless
        </div>
    @else
        @if((int) ($meta['seed_row_count'] ?? 0) < (int) ($meta['qualifier_count'] ?? 0))
            <div class="alert alert-warning">
                進出者数 {{ (int) ($meta['qualifier_count'] ?? 0) }} 名に対して、進出元snapshotから取得できたseed行が {{ (int) ($meta['seed_row_count'] ?? 0) }} 名です。<br>
                不足分は仮seedとして表示しています。
            </div>
        @endif

        <div class="se-nav">
            <a href="#se-seeds">進出者seed一覧</a>
            <a href="#se-bracket">トーナメント表</a>
        </div>

        <div id="se-seeds" class="se-table-wrap">
            <h4>進出者seed一覧</h4>
            <table class="se-table">
                <thead>
                    <tr>
                        <th>Seed</th>
                        <th>元順位</th>
                        <th class="se-left">選手名</th>
                        <th>ライセンスNo</th>
                        <th>通算ピン</th>
                        <th>G数</th>
                        <th>AVG</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($seedRows as $row)
                        <tr>
                            <td>{{ $row['seed'] ?? '—' }}</td>
                            <td>{{ $row['source_ranking'] ?? '—' }}</td>
                            <td class="se-left">{{ $row['display_name'] ?? '—' }}</td>
                            <td>{{ $row['pro_bowler_license_no'] ?? '—' }}</td>
                            <td>{{ isset($row['total_pin']) && $row['total_pin'] !== null ? number_format((int) $row['total_pin']) : '—' }}</td>
                            <td>{{ $row['games'] ?? '—' }}</td>
                            <td>{{ isset($row['average']) && $row['average'] !== null ? number_format((float) $row['average'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="se-bracket" class="mt-4">
            <h4>トーナメント表</h4>

            <div class="se-round-grid">
                @foreach($rounds as $round)
                    @php
                        $round = (array) $round;
                        $matches = array_values((array) ($round['matches'] ?? []));
                    @endphp

                    <div class="se-round">
                        <div class="se-round-title">
                            <span>{{ $round['round_name'] ?? (($round['round_no'] ?? '') . '回戦') }}</span>
                            <span class="se-loser-rank">敗者: {{ $round['loser_rank'] ?? '—' }}位タイ</span>
                        </div>

                        @foreach($matches as $match)
                            @php
                                $match = (array) $match;
                                $slotA = (array) ($match['slot_a'] ?? []);
                                $slotB = (array) ($match['slot_b'] ?? []);
                            @endphp

                            <div class="se-match">
                                <div class="se-match-head">{{ $match['label'] ?? '—' }}</div>

                                <div class="se-slot {{ ($slotA['type'] ?? '') === 'bye' ? 'se-slot-bye' : '' }}">
                                    @if(($slotA['type'] ?? '') === 'seed')
                                        <span class="se-seed">#{{ $slotA['seed'] ?? '—' }}</span>
                                    @endif
                                    <span class="se-name">{{ $slotName($slotA) }}</span>
                                    @if($slotSub($slotA) !== '')
                                        <div class="se-mini">{{ $slotSub($slotA) }}</div>
                                    @endif
                                </div>

                                <div class="se-vs">vs</div>

                                <div class="se-slot {{ ($slotB['type'] ?? '') === 'bye' ? 'se-slot-bye' : '' }}">
                                    @if(($slotB['type'] ?? '') === 'seed')
                                        <span class="se-seed">#{{ $slotB['seed'] ?? '—' }}</span>
                                    @endif
                                    <span class="se-name">{{ $slotName($slotB) }}</span>
                                    @if($slotSub($slotB) !== '')
                                        <div class="se-mini">{{ $slotSub($slotB) }}</div>
                                    @endif
                                </div>

                                <div class="se-mini mt-2">
                                    勝者 → {{ $match['winner_to'] ?? '優勝' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="se-note">
                この画面はまずラウンド別カード形式で表示しています。スコア連携・勝者確定・正式成績反映は次フェーズで接続します。
            </div>
        </div>
    @endif
</div>
@endsection