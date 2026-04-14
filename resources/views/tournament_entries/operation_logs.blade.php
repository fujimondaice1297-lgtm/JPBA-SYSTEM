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