{{-- resources/views/tournament_round_lane_assignments/index.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    $stageValue = old('stage', $stage ?? '準決勝');
    $roundLabelValue = old('round_label', $roundLabel ?? '準決勝4G');
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">ラウンド別レーン移動表</h1>
            <div class="text-muted">{{ $tournament->name }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-outline-secondary">大会詳細へ戻る</a>
            <a href="{{ route('tournaments.round_lane_assignments.pdf', ['tournament' => $tournament->id, 'stage' => $stageValue, 'round_label' => $roundLabelValue]) }}" class="btn btn-danger">
                PDF出力
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">入力内容を確認してください。</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold">表示条件</div>
        <div class="card-body">
            <form method="GET" action="{{ route('tournaments.round_lane_assignments.index', $tournament) }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">ステージ</label>
                    <input type="text" name="stage" value="{{ $stageValue }}" class="form-control" placeholder="例: 準決勝">
                </div>
                <div class="col-md-3">
                    <label class="form-label">ラウンド表示名</label>
                    <input type="text" name="round_label" value="{{ $roundLabelValue }}" class="form-control" placeholder="例: 準決勝4G">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">表示</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">成績スナップショットから初期行を作成</div>
        <div class="card-body">
            <form method="POST" action="{{ route('tournaments.round_lane_assignments.generate', $tournament) }}" class="row g-3 align-items-end">
                @csrf
                <input type="hidden" name="stage" value="{{ $stageValue }}">
                <input type="hidden" name="round_label" value="{{ $roundLabelValue }}">

                <div class="col-md-4">
                    <label class="form-label">元成績</label>
                    <select name="source_result_snapshot_id" class="form-select" required>
                        @foreach($snapshots as $snapshot)
                            <option value="{{ $snapshot->id }}" @selected(($latestPrelimTotal?->id ?? null) === $snapshot->id)>
                                #{{ $snapshot->id }} {{ $snapshot->result_name }} / {{ $snapshot->games_count }}G @if($snapshot->is_current) / 現行 @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">準決勝なら予選16Gの「予選通算成績 / 現行」を選びます。</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">対象人数</label>
                    <input type="number" name="limit" value="30" min="1" max="200" class="form-control" required>
                </div>

                <div class="col-md-1">
                    <label class="form-label">開始G</label>
                    <input type="number" name="game_from" value="17" min="1" class="form-control">
                </div>

                <div class="col-md-1">
                    <label class="form-label">終了G</label>
                    <input type="number" name="game_to" value="20" min="1" class="form-control">
                </div>

                <div class="col-md-2">
                    <label class="form-label">開始時刻</label>
                    <input type="time" name="game_start_time" value="09:30" class="form-control">
                </div>

                <div class="col-md-2">
                    <label class="form-label">間隔（分）</label>
                    <input type="number" name="game_interval_minutes" value="27" min="1" class="form-control">
                </div>

                <div class="col-md-2">
                    <label class="form-label">移動方向</label>
                    <select name="movement_direction" class="form-select">
                        <option value="left" selected>左隣BOX</option>
                        <option value="right">右隣BOX</option>
                        <option value="custom">個別指定</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">移動BOX数</label>
                    <input type="number" name="movement_box_step" value="1" min="1" class="form-control">
                </div>

                <div class="col-md-2">
                    <label class="form-label">TVレーンFrom</label>
                    <input type="number" name="tv_lane_from" value="9" min="1" class="form-control">
                </div>

                <div class="col-md-2">
                    <label class="form-label">TVレーンTo</label>
                    <input type="number" name="tv_lane_to" value="10" min="1" class="form-control">
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary">
                        初期行を作成 / 更新
                    </button>
                    <div class="form-text">
                        スタートレーンはこの下の一覧で編集します。予選のレーン情報は上書きしません。
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('tournaments.round_lane_assignments.bulk_update', $tournament) }}">
        @csrf
        <input type="hidden" name="stage" value="{{ $stageValue }}">
        <input type="hidden" name="round_label" value="{{ $roundLabelValue }}">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <span class="fw-bold">レーン割当一覧</span>
                <button type="submit" class="btn btn-primary btn-sm">保存</button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">表示順</th>
                            <th style="width:70px;">予選順位</th>
                            <th style="width:90px;">ライセンス</th>
                            <th style="width:150px;">氏名</th>
                            <th style="width:60px;">期</th>
                            <th style="width:60px;">投</th>
                            <th style="width:220px;">所属 / 用品契約</th>
                            <th style="width:90px;">予選T/PIN</th>
                            <th style="width:80px;">AVG</th>
                            <th style="width:80px;">開始L</th>
                            <th style="width:70px;">枠</th>
                            <th style="width:90px;">開始表示</th>
                            <th style="width:260px;">ゲーム別BOX</th>
                            <th style="width:120px;">開始時刻</th>
                            <th style="width:120px;">TVレーン</th>
                            <th style="width:120px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                            <tr>
                                <td>
                                    <input type="hidden" name="assignments[{{ $loop->index }}][id]" value="{{ $assignment->id }}">
                                    <input type="number" name="assignments[{{ $loop->index }}][sort_order]" value="{{ old("assignments.{$loop->index}.sort_order", $assignment->sort_order) }}" class="form-control form-control-sm">
                                </td>
                                <td class="text-center">{{ $assignment->seed_rank }}</td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][display_license_no]" value="{{ old("assignments.{$loop->index}.display_license_no", $assignment->display_license_no) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][display_name]" value="{{ old("assignments.{$loop->index}.display_name", $assignment->display_name) }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][period_label]" value="{{ old("assignments.{$loop->index}.period_label", $assignment->period_label) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][dominant_arm]" value="{{ old("assignments.{$loop->index}.dominant_arm", $assignment->dominant_arm) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][affiliation_display]" value="{{ old("assignments.{$loop->index}.affiliation_display", $assignment->affiliation_display) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="assignments[{{ $loop->index }}][source_total_pin]" value="{{ old("assignments.{$loop->index}.source_total_pin", $assignment->source_total_pin) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][source_average]" value="{{ old("assignments.{$loop->index}.source_average", $assignment->source_average !== null ? number_format((float)$assignment->source_average, 3, '.', '') : '') }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="assignments[{{ $loop->index }}][start_lane]" value="{{ old("assignments.{$loop->index}.start_lane", $assignment->start_lane) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="assignments[{{ $loop->index }}][lane_slot]" value="{{ old("assignments.{$loop->index}.lane_slot", $assignment->lane_slot) }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][start_lane_label]" value="{{ old("assignments.{$loop->index}.start_lane_label", $assignment->start_lane_label) }}" class="form-control form-control-sm" placeholder="19L-1">
                                </td>
                                <td>
                                    <input type="text" name="assignments[{{ $loop->index }}][movement_boxes_text]" value="{{ old("assignments.{$loop->index}.movement_boxes_text", $assignment->movement_boxes_text) }}" class="form-control form-control-sm" placeholder="19L･20L, 17L･18L, 15L･16L, 9L･10L">
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <input type="time" name="assignments[{{ $loop->index }}][game_start_time]" value="{{ old("assignments.{$loop->index}.game_start_time", $assignment->game_start_time ? substr($assignment->game_start_time, 0, 5) : '') }}" class="form-control form-control-sm">
                                        <input type="number" name="assignments[{{ $loop->index }}][game_interval_minutes]" value="{{ old("assignments.{$loop->index}.game_interval_minutes", $assignment->game_interval_minutes) }}" class="form-control form-control-sm" title="間隔分">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <input type="number" name="assignments[{{ $loop->index }}][tv_lane_from]" value="{{ old("assignments.{$loop->index}.tv_lane_from", $assignment->tv_lane_from) }}" class="form-control form-control-sm">
                                        <input type="number" name="assignments[{{ $loop->index }}][tv_lane_to]" value="{{ old("assignments.{$loop->index}.tv_lane_to", $assignment->tv_lane_to) }}" class="form-control form-control-sm">
                                    </div>
                                </td>
                                <td>
                                    <input type="hidden" name="assignments[{{ $loop->index }}][box_no]" value="{{ $assignment->box_no }}">
                                    <input type="hidden" name="assignments[{{ $loop->index }}][note]" value="{{ $assignment->note }}">
                                    <button type="submit" class="btn btn-primary btn-sm">保存</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="text-center text-muted py-4">
                                    まだラウンド別レーン割当がありません。上のフォームから成績スナップショットを選んで初期行を作成してください。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($assignments->isNotEmpty())
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="{{ route('tournaments.round_lane_assignments.pdf', ['tournament' => $tournament->id, 'stage' => $stageValue, 'round_label' => $roundLabelValue]) }}" class="btn btn-danger">
                        PDF出力
                    </a>
                </div>
            @endif
        </div>
    </form>
</div>
@endsection
