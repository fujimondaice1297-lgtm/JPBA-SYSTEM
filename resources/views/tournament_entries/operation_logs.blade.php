@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h2 class="mb-1">大会運用ログ</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">大会一覧へ戻る</a>
      <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-dark">エントリー一覧</a>
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-outline-primary">抽選一覧</a>
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

  @php
    $automationSummary = $automationSummary ?? [];
    $readiness = $automationSummary['readiness'] ?? [];
    $entries = $automationSummary['entries'] ?? [];
    $scores = $automationSummary['scores'] ?? [];
    $snapshots = $automationSummary['snapshots'] ?? [];
    $results = $automationSummary['results'] ?? [];
    $awards = $automationSummary['awards'] ?? [];
    $titles = $automationSummary['titles'] ?? [];
    $seeds = $automationSummary['seeds'] ?? [];
    $diagnostics = $automationSummary['diagnostics'] ?? [];
    $diagnosticIssues = $diagnostics['issues'] ?? [];
    $statusBadges = [
      'done' => ['class' => 'bg-success', 'label' => '完了'],
      'ready' => ['class' => 'bg-primary', 'label' => '実行可'],
      'warning' => ['class' => 'bg-warning text-dark', 'label' => '要確認'],
      'waiting' => ['class' => 'bg-secondary', 'label' => '待ち'],
    ];
    $badgeFor = fn ($key) => $statusBadges[$key] ?? $statusBadges['waiting'];
    $fullFinalSnapshot = $snapshots['full_final_snapshot'] ?? null;
  @endphp

  <div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>大会終了処理チェックリスト</span>
      <span class="text-muted small">DB正本から公開・PDF・タイトル・シードへつなぐ制御盤</span>
    </div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="text-muted small">エントリー</div>
            <div class="fs-4 fw-bold">{{ $entries['entry_count'] ?? 0 }}</div>
            <div class="small text-muted">チェックイン: {{ $entries['checked_in_count'] ?? 0 }}</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="text-muted small">スコア行</div>
            <div class="fs-4 fw-bold">{{ $scores['score_count'] ?? 0 }}</div>
            <div class="small text-muted">入力済み選手目安: {{ $scores['scored_player_count'] ?? 0 }}</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="text-muted small">最終成績</div>
            <div class="fs-4 fw-bold">{{ $results['final_results_count'] ?? 0 }}</div>
            <div class="small text-muted">優勝者行: {{ $results['winner_count'] ?? 0 }}</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="text-muted small">シード候補</div>
            <div class="fs-4 fw-bold">{{ $seeds['active_seed_source_count'] ?? 0 }}</div>
            <div class="small text-muted">
              年度別 {{ $seeds['annual_seed_player_count'] ?? 0 }} / 大会別 {{ $seeds['tournament_seed_player_count'] ?? 0 }}
            </div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div class="fw-bold">要確認リスト</div>
          <span class="badge text-bg-light border">{{ $diagnostics['issue_count'] ?? 0 }} 件</span>
        </div>
        @if (!empty($diagnosticIssues))
          <div class="list-group">
            @foreach ($diagnosticIssues as $issue)
              @php
                $issueClass = ($issue['severity'] ?? '') === 'warning'
                  ? 'text-bg-warning'
                  : 'text-bg-info';
              @endphp
              <div class="list-group-item d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <span class="badge {{ $issueClass }} me-2">{{ $issue['label'] ?? '確認' }}</span>
                  <span>{{ $issue['message'] ?? '' }}</span>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="alert alert-success mb-0">現時点で大きな不足は検出されていません。</div>
        @endif
      </div>

      @if (!empty($scores['stage_rows']))
        <div class="mb-3">
          <div class="small text-muted mb-2">スコア入力ステージ</div>
          <div class="d-flex flex-wrap gap-2">
            @foreach ($scores['stage_rows'] as $stageRow)
              <span class="badge text-bg-light border">
                {{ $stageRow['stage'] }}: {{ $stageRow['games_count'] }}G / {{ $stageRow['rows_count'] }}行
              </span>
            @endforeach
          </div>
        </div>
      @endif

      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th style="width: 180px;">処理</th>
              <th style="width: 90px;">状態</th>
              <th>確認内容</th>
              <th style="width: 260px;">操作</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-bold">1. エントリー確認</td>
              @php($badge = $badgeFor($readiness['entries'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>出場者・シフト・レーン・使用ボールの前提を確認します。</td>
              <td><a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-sm btn-outline-dark">エントリー一覧</a></td>
            </tr>
            <tr>
              <td class="fw-bold">2. スコア入力</td>
              @php($badge = $badgeFor($readiness['scores'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>
                `game_scores` を速報・順位計算の正本として入力します。
                @if (($diagnostics['score_entry_gap'] ?? 0) > 0)
                  <div class="small text-warning">エントリー人数との差分: {{ $diagnostics['score_entry_gap'] }} 名</div>
                @endif
                @if (!empty($diagnostics['incomplete_stage_rows']))
                  <div class="small text-warning">ステージ設定に対して未完了の入力があります。</div>
                @endif
              </td>
              <td>
                <div class="d-flex flex-wrap gap-2">
                  <a href="{{ route('scores.input', ['tournament_id' => $tournament->id]) }}" class="btn btn-sm btn-outline-primary">入力</a>
                  <a href="{{ route('scores.result', ['tournament_id' => $tournament->id]) }}" class="btn btn-sm btn-outline-secondary">速報</a>
                </div>
              </td>
            </tr>
            <tr>
              <td class="fw-bold">3. 正式成績反映</td>
              @php($badge = $badgeFor($readiness['snapshots'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>
                @if ($fullFinalSnapshot)
                  全体条件の最終snapshotあり: {{ $fullFinalSnapshot->result_name ?? 'final' }}
                  / 行数 {{ $snapshots['full_final_row_count'] ?? 0 }}
                @else
                  全体条件の `final_total` snapshot を作ると、最終成績へ同期できます。
                @endif
                @if (($diagnostics['final_sync_gap'] ?? 0) !== 0)
                  <div class="small text-warning">snapshot行数と最終成績件数に差分があります。</div>
                @endif
              </td>
              <td><a href="{{ route('tournaments.result_snapshots.index', $tournament->id) }}" class="btn btn-sm btn-outline-success">正式成績反映</a></td>
            </tr>
            <tr>
              <td class="fw-bold">4. 賞金・ポイント</td>
              @php($badge = $badgeFor($readiness['awards'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>
                配分: ポイント {{ $awards['point_distribution_count'] ?? 0 }} / 賞金 {{ $awards['prize_distribution_count'] ?? 0 }}
                ・対象行: ポイント {{ $awards['point_target_rows'] ?? 0 }} / 賞金 {{ $awards['prize_target_rows'] ?? 0 }}
                ・反映済み行: ポイント {{ $awards['point_applied_rows'] ?? 0 }} / 賞金 {{ $awards['prize_applied_rows'] ?? 0 }}
                @if (($diagnostics['award_pending'] ?? false) === true)
                  <div class="small text-warning">未反映の賞金・ポイントが残っている可能性があります。</div>
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('tournaments.results.apply_awards_points', $tournament->id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger">賞金・ポイント反映</button>
                </form>
              </td>
            </tr>
            <tr>
              <td class="fw-bold">5. タイトル同期</td>
              @php($badge = $badgeFor($readiness['titles'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>
                優勝者を `pro_bowler_titles` へ同期します。同期済み: {{ $titles['title_count'] ?? 0 }} 件
                @if (($diagnostics['title_pending'] ?? false) === true)
                  <div class="small text-warning">優勝者行に対してタイトル履歴が不足しています。</div>
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('tournaments.results.sync', $tournament->id) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="year" value="{{ $automationSummary['tournament_year'] ?? $tournament->year }}">
                  <button type="submit" class="btn btn-sm btn-outline-primary">タイトル同期</button>
                </form>
              </td>
            </tr>
            <tr>
              <td class="fw-bold">6. シード確認</td>
              @php($badge = $badgeFor($readiness['seeds'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>
                年度別シードと大会別追加シードを確認し、PDFの `S` 表示へつなげます。
                @if (($diagnostics['seed_missing'] ?? false) === true)
                  <div class="small text-warning">この大会年度・性別で有効なシード元が見つかっていません。</div>
                @endif
              </td>
              <td><a href="{{ route('tournaments.seed_players.index', $tournament->id) }}" class="btn btn-sm btn-outline-info">シード設定</a></td>
            </tr>
            <tr>
              <td class="fw-bold">7. PDF確認</td>
              @php($badge = $badgeFor($readiness['pdf'] ?? 'waiting'))
              <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
              <td>最終成績PDF・優先出場PDFを確認します。</td>
              <td>
                <div class="d-flex flex-wrap gap-2">
                  <a href="{{ route('tournaments.results.pdf', $tournament->id) }}" class="btn btn-sm btn-outline-secondary">成績PDF</a>
                  <a href="{{ route('tournaments.seed_players.pdf', $tournament->id) }}" class="btn btn-sm btn-outline-secondary">シードPDF</a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="small text-muted">
        ここでは自動実行前の確認を優先します。写真OCRやCSV取込は、まず確認用の一時データへ入れてから `game_scores` に確定反映する流れにします。
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>スコアCSV一時取込</span>
      <span class="text-muted small">確認後に `game_scores` へ反映するための下書き保存</span>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('tournaments.score_imports.csv.store', $tournament->id) }}" enctype="multipart/form-data" class="row g-3 align-items-end mb-4">
        @csrf
        <div class="col-md-4">
          <label for="score_csv" class="form-label">CSVファイル</label>
          <input type="file" name="csv" id="score_csv" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="col-md-2">
          <label for="default_stage" class="form-label">既定ステージ</label>
          <input type="text" name="default_stage" id="default_stage" class="form-control" value="{{ old('default_stage') }}" placeholder="予選">
        </div>
        <div class="col-md-2">
          <label for="default_game_number" class="form-label">既定G</label>
          <input type="number" name="default_game_number" id="default_game_number" class="form-control" min="1" max="99" value="{{ old('default_game_number') }}">
        </div>
        <div class="col-md-2">
          <label for="default_shift" class="form-label">既定シフト</label>
          <input type="text" name="default_shift" id="default_shift" class="form-control" value="{{ old('default_shift') }}">
        </div>
        <div class="col-md-2">
          <label for="default_gender" class="form-label">既定性別</label>
          <input type="text" name="default_gender" id="default_gender" class="form-control" value="{{ old('default_gender') }}" placeholder="M / F">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-outline-primary">CSVを一時取込</button>
          <span class="text-muted small ms-2">対応列: license_number / name / entry_number / stage / game_number / score、または 1G / 2G / 3G などの横持ちスコア列</span>
        </div>
      </form>

      @if (($scoreImportBatches ?? collect())->isNotEmpty())
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 90px;">ID</th>
                <th>ファイル</th>
                <th style="width: 120px;">状態</th>
                <th style="width: 120px;">取込行</th>
                <th style="width: 120px;">要確認</th>
                <th style="width: 180px;">取込日時</th>
                <th style="width: 100px;">操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($scoreImportBatches as $batch)
                <tr>
                  <td>#{{ $batch->id }}</td>
                  <td>
                    <div class="fw-semibold">{{ $batch->source_filename ?? '-' }}</div>
                    @if ($batch->error_message)
                      <div class="small text-danger">{{ $batch->error_message }}</div>
                    @endif
                  </td>
                  <td>
                    @php
                      $statusClass = match ($batch->status) {
                        'parsed' => 'text-bg-success',
                        'reviewing' => 'text-bg-warning',
                        'failed' => 'text-bg-danger',
                        default => 'text-bg-secondary',
                      };
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $batch->status }}</span>
                  </td>
                  <td>{{ number_format((int) ($batch->rows_count ?? 0)) }}</td>
                  <td>{{ number_format((int) ($batch->needs_review_rows_count ?? 0)) }}</td>
                  <td>{{ optional($batch->created_at)->format('Y-m-d H:i') }}</td>
                  <td>
                    <a href="{{ route('tournaments.score_imports.show', [$tournament->id, $batch->id]) }}" class="btn btn-sm btn-outline-primary">詳細</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-muted small">この大会のスコアCSV一時取込はまだありません。</div>
      @endif
    </div>
  </div>

  <form method="GET" action="{{ route('tournaments.operation_logs.index', $tournament->id) }}" class="card mb-4">
    <div class="card-header fw-bold">絞り込み</div>
    <div class="card-body row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">DM種別</label>
        <select name="reminder_kind" class="form-select">
          <option value="">すべて</option>
          <option value="manual" {{ $reminderKind === 'manual' ? 'selected' : '' }}>手動DM</option>
          <option value="auto" {{ $reminderKind === 'auto' ? 'selected' : '' }}>自動DM</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">DM送信結果</label>
        <select name="reminder_status" class="form-select">
          <option value="">すべて</option>
          <option value="sent" {{ $reminderStatus === 'sent' ? 'selected' : '' }}>送信成功</option>
          <option value="failed" {{ $reminderStatus === 'failed' ? 'selected' : '' }}>送信失敗</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">自動抽選対象</label>
        <select name="auto_target" class="form-select">
          <option value="">すべて</option>
          <option value="shift" {{ $autoTarget === 'shift' ? 'selected' : '' }}>シフト</option>
          <option value="lane" {{ $autoTarget === 'lane' ? 'selected' : '' }}>レーン</option>
        </select>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">絞り込む</button>
        <a href="{{ route('tournaments.operation_logs.index', $tournament->id) }}" class="btn btn-secondary">リセット</a>
      </div>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">DM総件数</div>
        <div class="fs-4 fw-bold">{{ $reminderSummary['total'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">手動DM</div>
        <div class="fs-4 fw-bold">{{ $reminderSummary['manual_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">自動DM</div>
        <div class="fs-4 fw-bold">{{ $reminderSummary['auto_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">送信成功</div>
        <div class="fs-4 fw-bold">{{ $reminderSummary['sent_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">送信失敗</div>
        <div class="fs-4 fw-bold">{{ $reminderSummary['failed_count'] }}</div>
      </div></div>
    </div>
    <div class="col-md-2">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">自動抽選実行回数</div>
        <div class="fs-4 fw-bold">{{ $autoDrawSummary['total_runs'] }}</div>
      </div></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">自動抽選対象件数累計</div>
        <div class="fs-4 fw-bold">{{ $autoDrawSummary['pending_total'] }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">自動抽選成功累計</div>
        <div class="fs-4 fw-bold">{{ $autoDrawSummary['success_total'] }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">自動抽選失敗累計</div>
        <div class="fs-4 fw-bold">{{ $autoDrawSummary['failed_total'] }}</div>
      </div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-bold">未抽選DM 送信履歴</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>送信日時</th>
              <th>種別</th>
              <th>対象</th>
              <th>送信日</th>
              <th>送信先</th>
              <th>件名</th>
              <th>結果</th>
              <th>エラー</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($reminderLogs as $log)
              <tr>
                <td>{{ $log->id }}</td>
                <td>
                  @if ($log->sent_at)
                    {{ \Illuminate\Support\Carbon::parse($log->sent_at)->format('Y-m-d H:i') }}
                  @else
                    -
                  @endif
                </td>
                <td>
                  @if ($log->reminder_kind === 'manual')
                    <span class="badge bg-secondary">手動</span>
                  @else
                    <span class="badge bg-primary">自動</span>
                  @endif
                </td>
                <td>
                  @if ($log->pending_type === 'shift')
                    シフト
                  @elseif ($log->pending_type === 'lane')
                    レーン
                  @else
                    両方
                  @endif
                </td>
                <td>{{ $log->scheduled_for_date ?? '-' }}</td>
                <td>{{ $log->recipient_email }}</td>
                <td style="min-width: 260px;">{{ $log->subject }}</td>
                <td>
                  @if ($log->status === 'sent')
                    <span class="badge bg-success">成功</span>
                  @else
                    <span class="badge bg-danger">失敗</span>
                  @endif
                </td>
                <td style="min-width: 280px;">
                  {{ $log->error_message ?: '-' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center text-muted">送信履歴はありません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($reminderLogs->hasPages())
      <div class="card-footer">
        {{ $reminderLogs->links() }}
      </div>
    @endif
  </div>

  <div class="card">
    <div class="card-header fw-bold">締切到来後の自動一括抽選ログ</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>実行日時</th>
              <th>対象</th>
              <th>締切日時</th>
              <th>未抽選件数</th>
              <th>成功</th>
              <th>失敗</th>
              <th>詳細</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($autoDrawLogs as $log)
              @php
                $detail = $log->details_json ? json_decode($log->details_json, true) : [];
                $errors = $detail['errors'] ?? [];
              @endphp
              <tr>
                <td>{{ $log->id }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($log->executed_at)->format('Y-m-d H:i') }}</td>
                <td>
                  @if ($log->target_type === 'shift')
                    <span class="badge bg-success">シフト</span>
                  @else
                    <span class="badge bg-dark">レーン</span>
                  @endif
                </td>
                <td>
                  @if ($log->deadline_at)
                    {{ \Illuminate\Support\Carbon::parse($log->deadline_at)->format('Y-m-d H:i') }}
                  @else
                    -
                  @endif
                </td>
                <td>{{ $log->total_pending }}</td>
                <td>{{ $log->success_count }}</td>
                <td>
                  @if ((int) $log->failed_count > 0)
                    <span class="badge bg-danger">{{ $log->failed_count }}</span>
                  @else
                    <span class="badge bg-success">0</span>
                  @endif
                </td>
                <td style="min-width: 320px;">
                  @if (!empty($errors))
                    <details>
                      <summary>失敗明細を見る</summary>
                      <ul class="mb-0 mt-2">
                        @foreach ($errors as $error)
                          <li>
                            {{ $error['license_no'] ?? '-' }}
                            {{ $error['name_kanji'] ?? '-' }}
                            / {{ $error['message'] ?? '-' }}
                          </li>
                        @endforeach
                      </ul>
                    </details>
                  @else
                    -
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted">自動一括抽選ログはありません。</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($autoDrawLogs->hasPages())
      <div class="card-footer">
        {{ $autoDrawLogs->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
