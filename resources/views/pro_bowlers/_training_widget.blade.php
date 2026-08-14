{{-- プロフィール画面用：講習ウィジェット（$bowler を前提） --}}
@php
    // 念のため保険：古い呼び名で来ても拾う
    $bowler = $bowler ?? ($proBowler ?? null);
    $trainingDecision = $trainingDecision ?? (($bowler && $bowler->id)
        ? app(\App\Services\TrainingComplianceService::class)->entryDecision($bowler)
        : null);
    $entryEligibility = $entryEligibility ?? (($bowler && $bowler->id)
        ? app(\App\Services\TournamentEntryEligibilityService::class)->evaluate($bowler)
        : null);
    $trainingStatus = data_get($trainingDecision, 'status', 'unconfirmed');
    $trainingBadge = in_array($trainingStatus, ['valid', 'official_list_valid', 'exempt'], true)
        ? 'bg-success'
        : (in_array($trainingStatus, ['expiring_this_year', 'expiring_next_year'], true)
            ? 'bg-warning text-dark'
            : 'bg-danger');
@endphp

@if($bowler && $bowler->id)
<div class="card p-3 mb-3">
  <div class="fw-bold mb-2">TP講習・大会出場資格</div>
  <div class="row g-2 small align-items-center">
    <div class="col-md-4">
      <strong>TP講習状態:</strong>
      <span class="badge {{ $trainingBadge }}">{{ data_get($trainingDecision, 'label', '未判定') }}</span>
    </div>
    <div class="col-md-4">
      <strong>TP講習有効期限:</strong>
      {{ data_get($trainingDecision, 'expires_at')?->format('Y-m-d') ?? '-' }}
    </div>
    <div class="col-md-4">
      <strong>現在の大会出場資格:</strong>
      <span class="badge {{ data_get($entryEligibility, 'allowed') ? 'bg-success' : 'bg-danger' }}">
        {{ data_get($entryEligibility, 'allowed') ? '出場可' : '出場不可' }}
      </span>
    </div>
  </div>

  @if(data_get($trainingDecision, 'official_evidence'))
    <div class="alert alert-success py-2 mt-3 mb-0">
      第{{ data_get($trainingDecision, 'official_evidence.officialList.edition_number') }}回 JPBA公式修了者一覧で確認済みです。
      <span class="small d-block">正確な受講日は公開資料にないため未入力です。個別受講記録を登録するとそちらが優先されます。</span>
    </div>
  @endif

  <form method="POST"
      action="{{ route('pro_bowler_trainings.store', ['pro_bowler' => $bowler->id]) }}"
      class="mt-3">

    @csrf
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">受講日</label>
        <input type="date" name="completed_at" class="form-control" required>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary w-100">受講を登録</button>
      </div>
    </div>
    <input type="hidden" name="training_code" value="mandatory">
  </form>

  @if(($bowler->mandatoryTrainings?->count() ?? 0) > 0)
    <div class="table-responsive mt-3">
      <table class="table table-sm mb-0"><thead class="table-light"><tr><th>受講日</th><th>有効期限</th><th>状態</th></tr></thead><tbody>
      @foreach($bowler->mandatoryTrainings as $history)
        <tr><td>{{ $history->completed_at?->format('Y-m-d') }}</td><td>{{ $history->expires_at?->format('Y-m-d') }}</td><td>{{ $history->record_status ?? 'valid' }}</td></tr>
      @endforeach
      </tbody></table>
    </div>
  @endif
</div>
@endif
