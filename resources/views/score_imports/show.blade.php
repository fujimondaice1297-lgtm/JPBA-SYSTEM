@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">スコア取込詳細</h2>
      <div class="text-muted">{{ $tournament->name }} / 取込ID #{{ $scoreImport->id }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.operation_logs.index', $tournament->id) }}" class="btn btn-outline-secondary">運用ログへ戻る</a>
      <form method="POST" action="{{ route('tournaments.score_imports.commit', [$tournament->id, $scoreImport->id]) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary" {{ (($summary['parsed'] ?? 0) + ($summary['accepted'] ?? 0) - ($summary['confirmed'] ?? 0)) <= 0 ? 'disabled' : '' }}>
          確認済み行を反映
        </button>
      </form>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-3 mb-4">
    @foreach ([
      'total' => '全行',
      'parsed' => '自動確認済み',
      'accepted' => '手動確認済み',
      'needs_review' => '要確認',
      'rejected' => '除外',
      'confirmed' => '反映済み',
    ] as $key => $label)
      <div class="col-6 col-md-2">
        <div class="border rounded p-3 h-100">
          <div class="text-muted small">{{ $label }}</div>
          <div class="fs-4 fw-bold">{{ number_format((int) ($summary[$key] ?? 0)) }}</div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>取込情報</span>
      @php
        $batchStatusClass = match ($scoreImport->status) {
          'confirmed' => 'text-bg-success',
          'parsed' => 'text-bg-primary',
          'reviewing' => 'text-bg-warning',
          'draft' => 'text-bg-info',
          'failed' => 'text-bg-danger',
          default => 'text-bg-secondary',
        };
        $importTypeLabel = match ($scoreImport->import_type) {
          'score_sheet_image' => '写真/PDF',
          'csv' => 'CSV',
          default => $scoreImport->import_type ?: '-',
        };
      @endphp
      <span class="badge {{ $batchStatusClass }}">{{ $scoreImport->status }}</span>
    </div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="text-muted small">種別</div>
        <div class="fw-semibold">{{ $importTypeLabel }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">ファイル</div>
        <div class="fw-semibold">{{ $scoreImport->source_filename ?? '-' }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">取込日時</div>
        <div>{{ optional($scoreImport->created_at)->format('Y-m-d H:i') }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">メモ</div>
        <div>{{ $scoreImport->notes ?: '-' }}</div>
      </div>
      @if ($scoreImport->import_type === 'score_sheet_image' && ($summary['total'] ?? 0) === 0)
        <div class="col-12">
          <div class="alert alert-info mb-0">OCR解析待ちの原本です。解析結果行はまだ作成されていません。</div>
        </div>
      @endif
      @if ($scoreImport->error_message)
        <div class="col-12">
          <div class="alert alert-danger mb-0">{{ $scoreImport->error_message }}</div>
        </div>
      @endif
    </div>
  </div>

  @if ($scoreImport->import_type === 'score_sheet_image')
    <div class="card mb-4">
      <div class="card-header fw-bold">OCR解析結果JSON取込</div>
      <div class="card-body">
        <form method="POST" action="{{ route('tournaments.score_imports.ocr_json.store', [$tournament->id, $scoreImport->id]) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
          @csrf
          <div class="col-md-4">
            <label for="ocr_json" class="form-label">解析結果JSON</label>
            <input type="file" name="ocr_json" id="ocr_json" class="form-control" accept=".json,.txt,application/json,text/plain" required>
          </div>
          <div class="col-md-2">
            <label for="ocr_default_stage" class="form-label">既定ステージ</label>
            <input type="text" name="ocr_default_stage" id="ocr_default_stage" class="form-control" value="{{ old('ocr_default_stage') }}" placeholder="予選">
          </div>
          <div class="col-md-2">
            <label for="ocr_default_shift" class="form-label">既定シフト</label>
            <input type="text" name="ocr_default_shift" id="ocr_default_shift" class="form-control" value="{{ old('ocr_default_shift') }}">
          </div>
          <div class="col-md-2">
            <label for="ocr_default_gender" class="form-label">既定性別</label>
            <input type="text" name="ocr_default_gender" id="ocr_default_gender" class="form-control" value="{{ old('ocr_default_gender') }}" placeholder="M / F">
          </div>
          <div class="col-md-2">
            <div class="form-check mb-2">
              <input type="checkbox" name="replace_existing" value="1" id="replace_existing" class="form-check-input">
              <label for="replace_existing" class="form-check-label">既存解析行を差し替える</label>
            </div>
            <button type="submit" class="btn btn-outline-primary w-100">JSONを取込</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <form method="GET" action="{{ route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id]) }}" class="card mb-4">
    <div class="card-body row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">状態</label>
        <select name="status" class="form-select">
          <option value="">すべて</option>
          @foreach (['parsed' => '自動確認済み', 'accepted' => '手動確認済み', 'needs_review' => '要確認', 'rejected' => '除外'] as $key => $label)
            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-outline-primary">絞り込み</button>
      </div>
    </div>
  </form>

  <div class="card mb-4">
    <div class="card-header fw-bold">選択行の一括修正</div>
    <div class="card-body">
      <form id="bulk-update-rows" method="POST" action="{{ route('tournaments.score_imports.rows.bulk_update', [$tournament->id, $scoreImport->id]) }}" class="row g-3 align-items-end">
        @csrf
        @method('PATCH')
        <div class="col-md-2">
          <label class="form-label">ステージ</label>
          <input type="text" name="bulk_stage" class="form-control" placeholder="予選">
        </div>
        <div class="col-md-2">
          <label class="form-label">G</label>
          <input type="number" name="bulk_game_number" class="form-control" min="1" max="99">
        </div>
        <div class="col-md-2">
          <label class="form-label">シフト</label>
          <input type="text" name="bulk_shift" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">性別</label>
          <input type="text" name="bulk_gender" class="form-control" placeholder="M / F">
        </div>
        <div class="col-md-2">
          <label class="form-label">状態</label>
          <select name="bulk_parse_status" class="form-select">
            <option value="">自動判定</option>
            <option value="accepted">確認済み</option>
            <option value="needs_review">要確認</option>
            <option value="rejected">除外</option>
          </select>
        </div>
        <div class="col-md-2">
          <div class="form-check mb-2">
            <input type="checkbox" name="apply_empty_only" value="1" id="apply_empty_only" class="form-check-input" checked>
            <label for="apply_empty_only" class="form-check-label">空欄だけ</label>
          </div>
          <button type="submit" class="btn btn-outline-primary w-100">選択行に適用</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-bold">操作ログ</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 160px;">日時</th>
              <th style="width: 140px;">操作</th>
              <th style="width: 110px;">状態</th>
              <th style="width: 160px;">実行者</th>
              <th>件数</th>
              <th>メッセージ</th>
            </tr>
          </thead>
          <tbody>
            @forelse (($operationLogs ?? collect()) as $log)
              @php
                $logStatusClass = $log->status === 'failed' ? 'text-bg-danger' : 'text-bg-success';
              @endphp
              <tr>
                <td>{{ optional($log->occurred_at)->format('Y-m-d H:i') }}</td>
                <td><span class="badge text-bg-light border">{{ $log->action }}</span></td>
                <td><span class="badge {{ $logStatusClass }}">{{ $log->status }}</span></td>
                <td>{{ $log->actor?->name ?? '-' }}</td>
                <td>
                  対象 {{ number_format((int) $log->target_row_count) }}
                  / 新規 {{ number_format((int) $log->created_count) }}
                  / 更新 {{ number_format((int) $log->updated_count) }}
                  / 除外 {{ number_format((int) $log->skipped_count) }}
                </td>
                <td>
                  {{ $log->message ?: '-' }}
                  @if (!empty($log->payload))
                    <details class="small mt-1">
                      <summary>詳細</summary>
                      <pre class="small bg-light border rounded p-2 mt-2 mb-0">{{ json_encode($log->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </details>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-muted p-3">この取込の操作ログはまだありません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header fw-bold">取込行</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 150px;">
                <div class="form-check">
                  <input type="checkbox" id="score-import-check-all" class="form-check-input">
                  <label for="score-import-check-all" class="form-check-label">行</label>
                </div>
              </th>
              <th style="min-width: 220px;">候補</th>
              <th style="min-width: 240px;">選手</th>
              <th style="min-width: 260px;">スコア</th>
              <th style="width: 150px;">操作</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $row)
              @php
                $rowFormId = 'score-import-row-' . $row->id;
                $rowStatusClass = match ($row->parse_status) {
                  'accepted', 'parsed' => 'text-bg-success',
                  'needs_review' => 'text-bg-warning',
                  'rejected' => 'text-bg-secondary',
                  default => 'text-bg-light',
                };
              @endphp
              <tr>
                <td>
                  @if (! $row->confirmed_game_score_id)
                    <div class="form-check mb-2">
                      <input form="bulk-update-rows" type="checkbox" name="row_ids[]" value="{{ $row->id }}" class="form-check-input js-score-import-row-check" id="score-import-row-check-{{ $row->id }}">
                      <label for="score-import-row-check-{{ $row->id }}" class="form-check-label">選択</label>
                    </div>
                  @endif
                  <div class="fw-semibold">#{{ $row->row_number }}</div>
                  <div class="small text-muted">ID {{ $row->id }}</div>
                  <span class="badge {{ $rowStatusClass }}">{{ $row->parse_status }}</span>
                  @if ($row->confirmed_game_score_id)
                    <div class="small text-success mt-1">反映済み: game_scores #{{ $row->confirmed_game_score_id }}</div>
                  @endif
                  @if ($row->error_message)
                    <div class="small text-danger mt-1">{{ $row->error_message }}</div>
                  @endif
                </td>
                <td>
                  <select form="{{ $rowFormId }}" name="selected_candidate_id" class="form-select form-select-sm mb-2" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    <option value="">候補を使わない</option>
                    @foreach ($row->candidates as $candidate)
                      <option value="{{ $candidate->id }}" {{ $candidate->is_selected ? 'selected' : '' }}>
                        {{ $candidate->candidate_type }}:
                        {{ $candidate->candidate_value ?: '-' }}
                        @if ($candidate->confidence !== null)
                          / {{ $candidate->confidence }}%
                        @endif
                      </option>
                    @endforeach
                  </select>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small mb-1">参加者ID</label>
                      <input form="{{ $rowFormId }}" type="number" name="tournament_participant_id" class="form-control form-control-sm" value="{{ $row->tournament_participant_id }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-6">
                      <label class="form-label small mb-1">プロID</label>
                      <input form="{{ $rowFormId }}" type="number" name="pro_bowler_id" class="form-control form-control-sm" value="{{ $row->pro_bowler_id }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small mb-1">ライセンス</label>
                      <input form="{{ $rowFormId }}" type="text" name="license_number" class="form-control form-control-sm" value="{{ $row->license_number }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-6">
                      <label class="form-label small mb-1">エントリー</label>
                      <input form="{{ $rowFormId }}" type="text" name="entry_number" class="form-control form-control-sm" value="{{ $row->entry_number }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-12">
                      <label class="form-label small mb-1">氏名</label>
                      <input form="{{ $rowFormId }}" type="text" name="name" class="form-control form-control-sm" value="{{ $row->name }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small mb-1">ステージ</label>
                      <input form="{{ $rowFormId }}" type="text" name="stage" class="form-control form-control-sm" value="{{ $row->stage }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-3">
                      <label class="form-label small mb-1">G</label>
                      <input form="{{ $rowFormId }}" type="number" name="game_number" class="form-control form-control-sm" value="{{ $row->game_number }}" min="1" max="99" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-3">
                      <label class="form-label small mb-1">Score</label>
                      <input form="{{ $rowFormId }}" type="number" name="score" class="form-control form-control-sm" value="{{ $row->score }}" min="0" max="300" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-4">
                      <label class="form-label small mb-1">シフト</label>
                      <input form="{{ $rowFormId }}" type="text" name="shift" class="form-control form-control-sm" value="{{ $row->shift }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-4">
                      <label class="form-label small mb-1">性別</label>
                      <input form="{{ $rowFormId }}" type="text" name="gender" class="form-control form-control-sm" value="{{ $row->gender }}" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                    </div>
                    <div class="col-4">
                      <label class="form-label small mb-1">状態</label>
                      <select form="{{ $rowFormId }}" name="parse_status" class="form-select form-select-sm" {{ $row->confirmed_game_score_id ? 'disabled' : '' }}>
                        <option value="accepted" {{ in_array($row->parse_status, ['parsed', 'accepted'], true) ? 'selected' : '' }}>確認済み</option>
                        <option value="needs_review" {{ $row->parse_status === 'needs_review' ? 'selected' : '' }}>要確認</option>
                        <option value="rejected" {{ $row->parse_status === 'rejected' ? 'selected' : '' }}>除外</option>
                      </select>
                    </div>
                  </div>
                </td>
                <td>
                  @if (! $row->confirmed_game_score_id)
                    <form id="{{ $rowFormId }}" method="POST" action="{{ route('tournaments.score_imports.rows.update', [$tournament->id, $scoreImport->id, $row->id]) }}">
                      @csrf
                      @method('PATCH')
                    </form>
                    <button form="{{ $rowFormId }}" type="submit" class="btn btn-sm btn-outline-primary w-100 mb-2">保存</button>
                  @endif
                  @if ($row->raw_payload)
                    <details class="small">
                      <summary>元データ</summary>
                      <pre class="small bg-light border rounded p-2 mt-2 mb-0">{{ json_encode($row->raw_payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </details>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-muted p-4">表示できる取込行がありません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($rows->hasPages())
      <div class="card-footer">
        {{ $rows->links() }}
      </div>
    @endif
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('score-import-check-all');
    if (!checkAll) {
      return;
    }

    checkAll.addEventListener('change', function () {
      document.querySelectorAll('.js-score-import-row-check:not(:disabled)').forEach(function (checkbox) {
        checkbox.checked = checkAll.checked;
      });
    });
  });
</script>
@endsection
