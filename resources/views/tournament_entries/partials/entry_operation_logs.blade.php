@php
  $entryOperationLogs = $entryOperationLogs ?? collect();
@endphp

<div class="card mb-4">
  <div class="card-header fw-bold">エントリー操作履歴</div>
  <div class="card-body">
    @if ($entryOperationLogs->isEmpty())
      <p class="text-muted small mb-0">取消、繰り上げ、チェックインなどの操作履歴はまだありません。</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>日時</th>
              <th>操作</th>
              <th>選手</th>
              <th>状態</th>
              <th>理由・メモ</th>
              <th>一括キー</th>
              <th>操作者</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($entryOperationLogs as $log)
              @php
                $payload = is_array($log->payload_array ?? null) ? $log->payload_array : [];
                $reason = $log->reason ?: ($payload['eligibility_short'] ?? null);
              @endphp
              <tr>
                <td class="text-nowrap small">
                  {{ $log->occurred_at ? \Illuminate\Support\Carbon::parse($log->occurred_at)->format('Y-m-d H:i') : '-' }}
                </td>
                <td class="text-nowrap">
                  <span class="badge bg-secondary">{{ $log->action_label ?? $log->action }}</span>
                </td>
                <td>
                  <div>{{ $log->bowler_name ?? '-' }}</div>
                  <div class="small text-muted">{{ $log->bowler_license_no ?? '-' }}</div>
                </td>
                <td class="small text-nowrap">
                  {{ $log->from_status ?? '-' }} → {{ $log->to_status ?? '-' }}
                </td>
                <td class="small">
                  {{ $reason ? \Illuminate\Support\Str::limit($reason, 80) : '-' }}
                </td>
                <td class="small text-muted">
                  {{ $log->batch_label ?? '-' }}
                </td>
                <td class="small text-nowrap">
                  {{ $log->actor_label ?? '-' }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
