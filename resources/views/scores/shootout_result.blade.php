@extends('layouts.app')

@section('content')
@php
    $data = is_array($shootout ?? null) ? $shootout : [];
    $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
    $seedRows = array_values((array) ($data['seed_rows'] ?? []));
    $shootoutBody = is_array($data['shootout'] ?? null) ? $data['shootout'] : [];
    $summary = is_array($shootoutBody['summary'] ?? null) ? $shootoutBody['summary'] : [];
    $matches = array_values((array) ($shootoutBody['matches'] ?? []));
    $missingSeedSnapshot = (bool) ($data['missing_seed_snapshot'] ?? false);

    $isPublic = ((int) request('public', 0) === 1);
    $shiftValue = (string) ($shifts ?? request('shifts', ''));
    $genderValue = (string) ($gender_filter ?? request('gender_filter', ''));

    $snapshotIndexUrl = null;
    if (!empty($meta['tournament_id'])) {
        $snapshotIndexUrl = route('tournaments.result_snapshots.index', ['tournament' => $meta['tournament_id']]);
    }

    $slotName = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'pending') {
            return (string) ($slot['display_name'] ?? $slot['label'] ?? '勝者未確定');
        }

        if ($type === 'empty') {
            return (string) ($slot['display_name'] ?? $slot['label'] ?? '未確定');
        }

        return (string) ($slot['display_name'] ?? $slot['label'] ?? '—');
    };

    $slotSub = function (array $slot): string {
        $parts = [];

        if (isset($slot['seed']) && $slot['seed'] !== null) {
            $parts[] = 'seed #' . $slot['seed'];
        }

        if (!empty($slot['source_ranking'])) {
            $parts[] = '通過順位 ' . $slot['source_ranking'] . '位';
        }

        if (!empty($slot['pro_bowler_license_no'])) {
            $parts[] = (string) $slot['pro_bowler_license_no'];
        }

        if (!empty($slot['total_pin'])) {
            $parts[] = number_format((int) $slot['total_pin']) . ' pin';
        }

        return implode(' / ', $parts);
    };

    $slotClass = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'pending') {
            return 'so-slot-pending';
        }

        if ($type === 'empty') {
            return 'so-slot-empty';
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
    body { padding-top: 0 !important; }
    .container, .container-fluid { margin-top: 0 !important; }
</style>
@endif

<style>
    .so-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        padding: 1rem;
        margin: 1rem auto;
        max-width: 1180px;
    }

    .so-title {
        font-weight: 800;
        font-size: 1.35rem;
        margin-bottom: .25rem;
    }

    .so-sub {
        color: #6b7280;
        font-size: .92rem;
        margin-bottom: .75rem;
    }

    .so-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin: .85rem 0 1rem;
    }

    .so-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: .6rem;
        margin: 1rem 0;
    }

    .so-summary-box {
        border: 1px solid #e5e7eb;
        border-radius: .85rem;
        padding: .75rem;
        background: #f9fafb;
    }

    .so-summary-label {
        font-size: .8rem;
        color: #6b7280;
    }

    .so-summary-value {
        font-size: 1.15rem;
        font-weight: 800;
    }

    .so-table-wrap {
        overflow-x: auto;
        margin-top: 1rem;
    }

    .so-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    .so-table th,
    .so-table td {
        border: 1px solid #d1d5db;
        padding: .5rem .55rem;
        text-align: center;
        vertical-align: middle;
        font-size: .9rem;
    }

    .so-table th {
        background: #f3f4f6;
        font-weight: 700;
    }

    .so-left {
        text-align: left !important;
    }

    .so-match-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(280px, 1fr));
        gap: .85rem;
        margin-top: 1rem;
    }

    .so-match {
        border: 1px solid #d1d5db;
        border-radius: .9rem;
        padding: .85rem;
        background: #ffffff;
    }

    .so-match-head {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        align-items: flex-start;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: .55rem;
        margin-bottom: .65rem;
    }

    .so-match-title {
        font-weight: 800;
    }

    .so-match-rank {
        color: #6b7280;
        font-size: .8rem;
        white-space: nowrap;
    }

    .so-description {
        color: #6b7280;
        font-size: .83rem;
        margin-bottom: .65rem;
    }

    .so-slot {
        border: 1px solid #e5e7eb;
        border-radius: .7rem;
        padding: .55rem;
        margin-bottom: .45rem;
        background: #f9fafb;
    }

    .so-slot-pending {
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .so-slot-empty {
        background: #f3f4f6;
        color: #6b7280;
    }

    .so-seed {
        display: inline-block;
        font-weight: 800;
        margin-right: .35rem;
        color: #1f2937;
    }

    .so-name {
        font-weight: 700;
    }

    .so-mini {
        font-size: .78rem;
        color: #6b7280;
        margin-top: .15rem;
    }

    .so-score-form {
        margin-top: .65rem;
        padding-top: .65rem;
        border-top: 1px dashed #d1d5db;
    }

    .so-score-row {
        display: grid;
        grid-template-columns: 1fr 88px;
        gap: .5rem;
        align-items: center;
        margin-bottom: .45rem;
    }

    .so-score-row label {
        margin: 0;
        font-size: .85rem;
        color: #374151;
        font-weight: 700;
    }

    .so-score-row input[type="number"] {
        width: 88px;
        text-align: right;
    }

    .so-winner {
        margin-top: .5rem;
        padding: .45rem .55rem;
        border-radius: .55rem;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
        font-weight: 800;
    }

    .so-tie-warning {
        margin-top: .5rem;
        padding: .45rem .55rem;
        border-radius: .55rem;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        font-weight: 800;
    }

    .so-note {
        margin-top: 1rem;
        padding: .85rem;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        border-radius: .85rem;
        color: #1e3a8a;
        font-size: .9rem;
    }

    @media (max-width: 1199.98px) {
        .so-match-grid {
            grid-template-columns: 1fr;
        }

        .so-summary {
            grid-template-columns: repeat(2, minmax(120px, 1fr));
        }
    }
</style>

<div class="so-card">
    <div class="so-title">{{ $tournament_name }} / シュートアウト</div>
    <div class="so-sub">
        進出元：{{ $meta['seed_source_name'] ?? '—' }}
        ／ 進出人数：{{ (int) ($meta['qualifier_count'] ?? 0) }}名
        ／ 方式：{{ $meta['shootout_format_name'] ?? '標準8名方式' }}
        @if(!empty($meta['seed_snapshot_id']))
            ／ seed snapshot: #{{ $meta['seed_snapshot_id'] }}
        @endif
    </div>

    @unless($isPublic)
    <div class="so-toolbar">
        <a class="btn btn-outline-secondary" href="{{ url('/scores/input') }}">速報入力ページへ</a>

        @if($snapshotIndexUrl)
            <a class="btn btn-outline-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
        @endif

        <a class="btn btn-outline-secondary" href="{{ route('scores.result', array_filter([
            'tournament_id' => request('tournament_id'),
            'stage' => 'シュートアウト',
            'upto_game' => $upto_game ?? request('upto_game', 1),
            'shifts' => $shiftValue,
            'gender_filter' => $genderValue,
            'public' => 1,
        ])) }}">共有URL(公開)</a>
    </div>
    @endunless

    <div class="so-summary">
        <div class="so-summary-box">
            <div class="so-summary-label">進出人数</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['qualifier_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">マッチ数</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['match_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">完了マッチ</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['completed_match_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">現在の勝者</div>
            <div class="so-summary-value">{{ $summary['winner_name'] ?? '—' }}</div>
        </div>
    </div>

    @if($missingSeedSnapshot)
        <div class="alert alert-warning">
            シュートアウト進出元 snapshot（{{ $meta['seed_source_result_code'] ?? '—' }} / {{ $meta['seed_source_name'] ?? '—' }}）が見つかりません。<br>
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
        @if((int) ($meta['seed_row_count'] ?? 0) < 8)
            <div class="alert alert-warning">
                標準8名シュートアウトに対して、進出元snapshotから取得できたseed行が {{ (int) ($meta['seed_row_count'] ?? 0) }} 名です。<br>
                先に準決勝通算などの進出元成績を8名以上で反映してください。
            </div>
        @endif

        <div class="so-table-wrap">
            <h4>進出者seed一覧</h4>
            <table class="so-table">
                <thead>
                    <tr>
                        <th>Seed</th>
                        <th>元順位</th>
                        <th class="so-left">選手名</th>
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
                            <td class="so-left">{{ $row['display_name'] ?? '—' }}</td>
                            <td>{{ $row['pro_bowler_license_no'] ?? '—' }}</td>
                            <td>{{ isset($row['total_pin']) && $row['total_pin'] !== null ? number_format((int) $row['total_pin']) : '—' }}</td>
                            <td>{{ $row['games'] ?? '—' }}</td>
                            <td>{{ isset($row['average']) && $row['average'] !== null ? number_format((float) $row['average'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="so-match-grid">
            @foreach($matches as $match)
                @php
                    $match = (array) $match;
                    $slotCodes = array_values((array) ($match['slot_codes'] ?? []));
                    $slots = (array) ($match['slots'] ?? []);
                    $scores = (array) ($match['scores'] ?? []);
                    $canInput = !$isPublic && !empty($match['can_input']);
                    $winnerNode = (array) ($match['winner_node'] ?? []);
                @endphp

                <div class="so-match">
                    <div class="so-match-head">
                        <div class="so-match-title">{{ $match['label'] ?? '—' }}</div>
                        <div class="so-match-rank">敗退者: {{ $match['loser_rank_range'] ?? '—' }}</div>
                    </div>

                    <div class="so-description">{{ $match['description'] ?? '' }}</div>

                    @foreach($slotCodes as $slotCode)
                        @php
                            $slot = (array) ($slots[$slotCode] ?? []);
                            $scoreValue = $scores[$slotCode] ?? null;
                        @endphp

                        <div class="so-slot {{ $slotClass($slot) }}">
                            @if(in_array(($slot['type'] ?? ''), ['seed', 'advanced'], true) && isset($slot['seed']))
                                <span class="so-seed">#{{ $slot['seed'] }}</span>
                            @endif
                            <span class="so-name">{{ $slotName($slot) }}</span>
                            @if($slotSub($slot) !== '')
                                <div class="so-mini">{{ $slotSub($slot) }}</div>
                            @endif
                            @if($scoreValue !== null)
                                <div class="so-mini">スコア: {{ $scoreValue }}</div>
                            @endif
                        </div>
                    @endforeach

                    @if($canInput)
                        <form method="POST" action="{{ route('scores.shootout.store') }}" class="so-score-form">
                            @csrf
                            <input type="hidden" name="tournament_id" value="{{ $meta['tournament_id'] ?? request('tournament_id') }}">
                            <input type="hidden" name="match_no" value="{{ $match['match_no'] ?? 1 }}">
                            <input type="hidden" name="match_key" value="{{ $match['match_key'] ?? '' }}">
                            <input type="hidden" name="upto_game" value="{{ $upto_game ?? request('upto_game', 1) }}">
                            <input type="hidden" name="shifts" value="{{ $shiftValue }}">
                            <input type="hidden" name="gender_filter" value="{{ $genderValue }}">

                            @foreach($slotCodes as $slotCode)
                                @php $slot = (array) ($slots[$slotCode] ?? []); @endphp
                                @foreach([
                                    'type',
                                    'seed',
                                    'display_name',
                                    'label',
                                    'pro_bowler_id',
                                    'pro_bowler_license_no',
                                    'amateur_name',
                                    'participant_key',
                                    'source_row_id',
                                    'source_ranking',
                                    'total_pin',
                                    'games',
                                    'average',
                                    'min_seed',
                                    'max_seed',
                                ] as $field)
                                    @if(array_key_exists($field, $slot) && !is_array($slot[$field]))
                                        <input type="hidden" name="slots[{{ $slotCode }}][{{ $field }}]" value="{{ $slot[$field] }}">
                                    @endif
                                @endforeach
                            @endforeach

                            @foreach($slotCodes as $slotCode)
                                @php
                                    $slot = (array) ($slots[$slotCode] ?? []);
                                    $scoreValue = $scores[$slotCode] ?? null;
                                @endphp
                                @if(in_array(($slot['type'] ?? ''), ['seed', 'advanced'], true))
                                    <div class="so-score-row">
                                        <label>{{ $slotCode }}：{{ $slotName($slot) }}</label>
                                        <input type="number" name="scores[{{ $slotCode }}]" class="form-control form-control-sm" min="0" max="300" value="{{ $scoreValue !== null ? $scoreValue : '' }}">
                                    </div>
                                @endif
                            @endforeach

                            <button type="submit" class="btn btn-sm btn-primary">このマッチを保存</button>
                        </form>
                    @else
                        <div class="so-mini mt-2">
                            @if(!empty($match['is_complete']))
                                マッチ完了済みです。
                            @else
                                前マッチの勝者確定後に入力できます。
                            @endif
                        </div>
                    @endif

                    @if(!empty($match['is_tied']))
                        <div class="so-tie-warning">
                            最高点が同点です。勝者を確定できません。タイブレーク後のスコアに修正してください。
                        </div>
                    @elseif(!empty($match['winner_node']))
                        <div class="so-winner">
                            勝者：{{ $winnerNode['display_name'] ?? $winnerNode['label'] ?? '—' }}
                            @if(!empty($match['winner_to']))
                                → {{ $match['winner_to'] }}
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="so-note">
            シュートアウトでは、各マッチのスコアは勝ち上がり者を決めるために使います。<br>
            敗退者の最終順位は、そのマッチのスコア順ではなく、進出元snapshotの通過順位を引き継ぎます。
        </div>
    @endif
</div>
@endsection
