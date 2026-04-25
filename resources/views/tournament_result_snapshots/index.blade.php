@extends('layouts.app')

@section('content')
@php
    $presetGroups = [
        '予選' => [],
        '準々決勝' => [],
        '準決勝' => [],
        '決勝' => [],
        'その他' => [],
    ];

    foreach ($presets as $preset) {
        $code = (string) ($preset['preset_key'] ?? '');

        if (str_starts_with($code, 'prelim_')) {
            $presetGroups['予選'][] = $preset;
        } elseif (str_starts_with($code, 'quarterfinal_')) {
            $presetGroups['準々決勝'][] = $preset;
        } elseif (str_starts_with($code, 'semifinal_')) {
            $presetGroups['準決勝'][] = $preset;
        } elseif (str_starts_with($code, 'final_') || str_starts_with($code, 'step_ladder_')) {
            $presetGroups['決勝'][] = $preset;
        } else {
            $presetGroups['その他'][] = $preset;
        }
    }
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">正式成績反映</h1>
            <div class="text-muted">大会: {{ $tournament->name }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('scores.result', ['tournament_id' => $tournament->id]) }}" class="btn btn-outline-secondary">速報表示へ</a>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">大会成績一覧へ</a>
            @if($currentFinalSnapshot && $finalResultsCount > 0)
                <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-success">最終成績を見る</a>
            @elseif($currentFinalSnapshot)
                <a href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $currentFinalSnapshot->id]) }}" class="btn btn-primary">snapshotを見る</a>
            @endif
            <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧へ</a>
        </div>
    </div>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>{{ session('success') }}</div>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-sm btn-success">最終成績を見る</a>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($currentFinalSnapshot)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <div class="fw-bold">最新の最終成績スナップショットがあります</div>
                <div class="small">
                    {{ $currentFinalSnapshot->result_name }}
                    / 反映日時: {{ optional($currentFinalSnapshot->reflected_at)->format('Y-m-d H:i') }}
                    / 行数: {{ $currentFinalSnapshot->rows()->count() }}
                </div>
                <div class="small">大会成績一覧への同期件数: {{ $finalResultsCount }} 件</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $currentFinalSnapshot->id]) }}" class="btn btn-primary">snapshotを見る</a>
                @if($finalResultsCount > 0)
                    <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-success">最終成績を見る</a>
                @endif
            </div>
        </div>
    @endif

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
                                    $currentSnapshot = $currentSnapshotsByCode->get($preset['preset_key']);
                                @endphp

                                @if($currentSnapshot)
                                    <a href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $currentSnapshot->id]) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        {{ $preset['result_name'] }}
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
        <div class="card-header">反映条件</div>
        <div class="card-body">
            <form method="GET" action="{{ route('tournaments.result_snapshots.index', $tournament) }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">性別</label>
                    <select name="gender" class="form-select">
                        <option value="">全体</option>
                        @foreach($availableGenders as $g)
                            <option value="{{ $g }}" @selected($gender === $g)>
                                {{ $g === 'M' ? '男子' : '女子' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">シフト</label>
                    <select name="shift" class="form-select">
                        <option value="">全体</option>
                        @foreach($availableShifts as $shiftValue)
                            <option value="{{ $shiftValue }}" @selected($shift === $shiftValue)>{{ $shiftValue }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">条件を反映</button>
                </div>
            </form>

            <hr>

            <div class="small text-muted mb-2">現在の集計条件</div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light">性別: {{ $gender === null ? '全体' : ($gender === 'M' ? '男子' : '女子') }}</span>
                <span class="badge text-bg-light">シフト: {{ $shift ?? '全体' }}</span>
            </div>

            <div class="small text-muted mb-2">検出したステージゲーム数</div>
            <div class="d-flex flex-wrap gap-2">
                @forelse($stageCounts as $stage => $games)
                    <span class="badge text-bg-secondary">{{ $stage }} {{ $games }}G</span>
                @empty
                    <span class="text-muted">まだ game_scores がありません。</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">反映ボタン</div>
        <div class="card-body">
            @if(empty($presets))
                <div class="text-muted">この条件では反映対象をまだ作れません。先に速報入力を進めてください。</div>
            @else
                <div class="row g-3">
                    @foreach($presets as $preset)
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                    <div>
                                        <div class="fw-bold">{{ $preset['result_name'] }}</div>
                                        <div class="small text-muted">{{ $preset['preset_key'] }}</div>
                                    </div>
                                    @if(($preset['definition']['is_final'] ?? false) === true)
                                        <span class="badge text-bg-danger">最終</span>
                                    @endif
                                </div>

                                <ul class="small mb-3 ps-3">
                                    @foreach($preset['description_lines'] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>

                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('tournaments.result_snapshots.reflect', $tournament) }}">
                                        @csrf
                                        <input type="hidden" name="preset_key" value="{{ $preset['preset_key'] }}">
                                        <input type="hidden" name="gender" value="{{ $gender ?? '' }}">
                                        <input type="hidden" name="shift" value="{{ $shift ?? '' }}">
                                        <button type="submit" class="btn btn-success">この単位で反映する</button>
                                    </form>

                                    @if($currentSnapshotsByCode->has($preset['preset_key']))
                                        <a href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $currentSnapshotsByCode->get($preset['preset_key'])->id]) }}"
                                           class="btn btn-outline-primary">
                                            現在の成績を見る
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">反映履歴</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>成績名</th>
                            <th>種別</th>
                            <th>G数</th>
                            <th>持込G数</th>
                            <th>行数</th>
                            <th>current</th>
                            <th>公開</th>
                            <th>反映日時</th>
                            <th>反映者</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($snapshots as $snapshot)
                            <tr>
                                <td>{{ $snapshot->id }}</td>
                                <td>
                                    <div>{{ $snapshot->result_name }}</div>
                                    <div class="small text-muted">{{ $snapshot->result_code }}</div>
                                </td>
                                <td>{{ $snapshot->result_type }}</td>
                                <td>{{ $snapshot->games_count }}</td>
                                <td>{{ $snapshot->carry_game_count }}</td>
                                <td>{{ $snapshot->rows_count }}</td>
                                <td>
                                    @if($snapshot->is_current)
                                        <span class="badge text-bg-success">current</span>
                                    @else
                                        <span class="badge text-bg-secondary">old</span>
                                    @endif
                                </td>
                                <td>
                                    @if($snapshot->is_published)
                                        <span class="badge text-bg-primary">published</span>
                                    @else
                                        <span class="badge text-bg-light">draft</span>
                                    @endif
                                </td>
                                <td>{{ optional($snapshot->reflected_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $snapshot->reflectedBy->name ?? $snapshot->reflectedBy->email ?? 'system' }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('tournaments.result_snapshots.show', ['tournament' => $tournament->id, 'snapshot' => $snapshot->id]) }}" class="btn btn-sm btn-outline-primary">表示</a>
                                        @if($snapshot->is_final && $snapshot->is_current && $finalResultsCount > 0 && is_null($snapshot->gender) && is_null($snapshot->shift))
                                            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-sm btn-outline-success">最終成績</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">まだ反映履歴がありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection