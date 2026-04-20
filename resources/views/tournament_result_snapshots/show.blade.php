@extends('layouts.app')

@section('content')
@php
    $backUrl = route('tournaments.result_snapshots.index', array_merge(['tournament' => $tournament->id], $backQuery));

    $resultTypeLabel = match ((string) $snapshot->result_type) {
        'total_pin' => 'トータルピン方式',
        default => (string) $snapshot->result_type,
    };

    $presetGroups = [
        '予選' => [],
        '準々決勝' => [],
        '準決勝' => [],
        '決勝' => [],
        'その他' => [],
    ];

    foreach ($showPresets as $preset) {
        $code = (string) ($preset['preset_key'] ?? '');

        if (str_starts_with($code, 'prelim_')) {
            $presetGroups['予選'][] = $preset;
        } elseif (str_starts_with($code, 'quarterfinal_')) {
            $presetGroups['準々決勝'][] = $preset;
        } elseif (str_starts_with($code, 'semifinal_')) {
            $presetGroups['準決勝'][] = $preset;
        } elseif (str_starts_with($code, 'final_')) {
            $presetGroups['決勝'][] = $preset;
        } else {
            $presetGroups['その他'][] = $preset;
        }
    }
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $snapshot->result_name }}</h1>
            <div class="text-muted">大会: {{ $tournament->name }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">反映ページへ戻る</a>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">大会成績一覧へ</a>
            @if($snapshot->is_final && $finalResultsCount > 0 && is_null($snapshot->gender) && is_null($snapshot->shift))
                <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-success">最終成績を見る</a>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-danger">{{ $snapshot->result_name }}</span>
                <span class="badge text-bg-secondary">{{ $resultTypeLabel }}</span>
                @if($snapshot->is_current)
                    <span class="badge text-bg-success">現行</span>
                @endif
                @if($snapshot->is_final)
                    <span class="badge text-bg-danger">最終成績</span>
                @endif
                <span class="badge text-bg-light">性別: {{ $snapshot->gender === null ? '全体' : ($snapshot->gender === 'M' ? '男子' : '女子') }}</span>
                <span class="badge text-bg-light">シフト: {{ $snapshot->shift ?? '全体' }}</span>
                <span class="badge text-bg-light">主ステージ: {{ $snapshot->stage_name ?? '-' }}</span>
            </div>

            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted">反映日時</div>
                    <div>{{ optional($snapshot->reflected_at)->format('Y-m-d H:i') }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted">ゲーム数</div>
                    <div>{{ $snapshot->games_count }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted">持込G数</div>
                    <div>{{ $snapshot->carry_game_count }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted">行数</div>
                    <div>{{ $rows->total() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">反映者</div>
                    <div>{{ $snapshot->reflectedBy->name ?? $snapshot->reflectedBy->email ?? 'system' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">現在の成績を見る</div>
        <div class="card-body">
            @foreach(['予選', '準々決勝', '準決勝', '決勝', 'その他'] as $groupLabel)
                @if(!empty($presetGroups[$groupLabel]))
                    <div class="mb-3">
                        <div class="fw-bold mb-2">{{ $groupLabel }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($presetGroups[$groupLabel] as $preset)
                                @php
                                    $item = $currentSnapshotsByCode->get($preset['preset_key']);
                                @endphp

                                @if($item)
                                    <a
                                        href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $item->id]) }}"
                                        class="btn btn-sm {{ (int) $item->id === (int) $snapshot->id ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    >
                                        {{ $preset['result_name'] }}
                                        @if($item->is_current)
                                            ／現行
                                        @endif
                                    </a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled">
                                        {{ $preset['result_name'] }} ／未反映
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">この成績の反映履歴</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($sameResultSnapshots as $item)
                    <a
                        href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $item->id]) }}"
                        class="btn btn-sm {{ (int) $item->id === (int) $snapshot->id ? 'btn-primary' : 'btn-outline-secondary' }}"
                    >
                        {{ $item->result_name }}
                        @if($item->is_current)
                            ／現行
                        @endif
                        （{{ optional($item->reflected_at)->format('m/d H:i') }}）
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">順位表</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>順位</th>
                            <th>選手名</th>
                            <th>ライセンスNo</th>
                            @foreach($stageColumns as $stageColumn)
                                <th>{{ $stageColumn['label'] }}</th>
                            @endforeach
                            <th>トータルピン</th>
                            <th>ゲーム数</th>
                            <th>AVG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->ranking }}</td>
                                <td>{{ $row->display_name }}</td>
                                <td>{{ $row->pro_bowler_license_no ?: '-' }}</td>

                                @foreach($stageColumns as $stageColumn)
                                    @php
                                        $stageValue = $stagePinMap[$row->id][$stageColumn['stage']] ?? null;
                                        $displayStageValue = $stageValue === null
                                            ? '-'
                                            : number_format((int) $stageValue);
                                    @endphp
                                    <td>{{ $displayStageValue }}</td>
                                @endforeach

                                <td class="fw-bold">{{ number_format((int) $row->total_pin) }}</td>
                                <td>{{ (int) $row->games }}</td>
                                <td>{{ number_format((float) $row->average, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 6 + count($stageColumns) }}" class="text-center text-muted py-4">この snapshot に順位データはありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="p-3">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection