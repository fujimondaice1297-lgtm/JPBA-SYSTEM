{{-- プロフィール画面用：講習ウィジェット（$bowler を前提） --}}
@php
    // 念のため保険：古い呼び名で来ても拾う
    $bowler = $bowler ?? ($proBowler ?? null);
@endphp

@if($bowler && $bowler->id)
<div class="card p-3 mb-3">
  <div class="d-flex align-items-center gap-2">
    <span>講習ステータス:</span>
    @php $st = $bowler->compliance_status ?? null; @endphp
    <span class="badge
      {{ $st==='missing' ? 'bg-secondary' :
         ($st==='expired' ? 'bg-danger' :
         ($st==='expiring_soon' ? 'bg-warning' : 'bg-success')) }}">
      {{ match($st){ 'missing'=>'未受講','expired'=>'期限切れ','expiring_soon'=>'まもなく期限','compliant'=>'適合', default=>'不明' } }}
    </span>

    @if(optional($bowler->latestMandatoryTraining)->expires_at)
      <span class="text-muted ms-2">
        有効期限: {{ optional($bowler->latestMandatoryTraining->expires_at)->format('Y-m-d') }}
      </span>
    @endif
  </div>

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
</div>
@endif
