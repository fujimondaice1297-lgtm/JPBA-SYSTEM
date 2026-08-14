@extends('layouts.app')

@push('styles')
<style>
  .tp-hero{border:0;border-radius:18px;background:linear-gradient(135deg,#132c51,#1b7280);color:#fff;box-shadow:0 12px 30px rgba(18,44,81,.18)}
  .tp-stat{height:100%;border:0;border-radius:14px;box-shadow:0 4px 14px rgba(16,24,40,.06)}
  .tp-session{border:2px solid transparent;border-radius:14px;background:#fff;text-decoration:none;color:inherit;transition:.15s}
  .tp-session:hover,.tp-session.active{border-color:#1b7280;color:inherit;box-shadow:0 8px 20px rgba(27,114,128,.12)}
  .tp-status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}
  .tp-table select{min-width:130px}.tp-table input{min-width:180px}
  @media(max-width:767px){.tp-table th,.tp-table td{white-space:normal;min-width:130px}}
</style>
@endpush

@section('content')
<section class="card tp-hero mb-4"><div class="card-body p-4 p-lg-5">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <div class="small text-white-50 fw-bold">TOURNAMENT PLAYER TRAINING</div>
      <h1 class="h2 mt-1 mb-2">トーナメントプレイヤー講習会</h1>
      <p class="mb-0 text-white-50">受講予定者の登録 → 当日の出欠確定 → 3年間の期限更新 → 大会出場資格判定までを一括管理します。</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('admin.compliance.index') }}" class="btn btn-light">受講状況・通知管理</a>
      <a href="{{ route('trainings.reports') }}" class="btn btn-outline-light">従来レポート</a>
    </div>
  </div>
</div></section>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-4">
  @foreach([
    ['status'=>'valid','label'=>'受講済み（有効）','tone'=>'success'],
    ['status'=>'expiring_next_year','label'=>'次年度期限・通知対象','tone'=>'warning'],
    ['status'=>'expired','label'=>'期限切れ','tone'=>'danger'],
    ['status'=>'missing','label'=>'未受講','tone'=>'secondary'],
    ['status'=>'unconfirmed','label'=>'移行確認待ち','tone'=>'info'],
  ] as $card)
    <div class="col-6 col-xl"><div class="card tp-stat"><div class="card-body">
      <div class="small text-muted">{{ $card['label'] }}</div>
      <div class="h3 mb-0 text-{{ $card['tone'] }}">{{ number_format((int)($statusCounts[$card['status']] ?? 0)) }}</div>
    </div></div></div>
  @endforeach
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div class="d-flex gap-2 flex-wrap">
    @foreach($availableYears as $candidateYear)
      <a href="{{ route('tp_registration.index',['year'=>$candidateYear]) }}" class="btn {{ (int)$candidateYear===$year ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $candidateYear }}年度</a>
    @endforeach
  </div>
  <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#newSession">＋ 講習会を作成</button>
</div>

<div class="collapse mb-4" id="newSession"><div class="card shadow-sm border-0"><div class="card-body">
  <h2 class="h5">講習会を作成</h2>
  <form method="POST" action="{{ route('tp_registration.sessions.store') }}" class="row g-3">@csrf
    <div class="col-md-5"><label class="form-label">講習会名</label><input name="name" class="form-control" placeholder="例：2026年度 TP講習会 東京会場" required></div>
    <div class="col-md-3"><label class="form-label">開催日</label><input type="date" name="held_on" value="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">会場</label><input name="venue" class="form-control"></div>
    <div class="col-12"><label class="form-label">備考</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    <div class="col-12"><button class="btn btn-success">作成する</button></div>
  </form>
</div></div></div>

<div class="row g-3 mb-4">
  @forelse($sessions as $session)
    <div class="col-12 col-md-6 col-xl-4"><a class="card tp-session h-100 {{ $selectedSession?->id===$session->id ? 'active' : '' }}" href="{{ route('tp_registration.index',['year'=>$year,'session'=>$session->id]) }}"><div class="card-body">
      <div class="d-flex justify-content-between gap-2"><div class="fw-bold">{{ $session->name }}</div><span class="badge {{ $session->status==='completed'?'bg-success':'bg-primary' }}">{{ $session->status_label }}</span></div>
      <div class="text-muted mt-2">{{ $session->held_on?->format('Y/m/d') }}{{ $session->venue ? '／'.$session->venue : '' }}</div>
      <div class="small mt-2">登録 {{ $session->participants_count }}名　受講済 {{ $session->attended_count }}名　未受講 {{ $session->absent_count }}名</div>
    </div></a></div>
  @empty
    <div class="col-12"><div class="alert alert-info mb-0">{{ $year }}年度の講習会はまだ作成されていません。</div></div>
  @endforelse
</div>

@if($selectedSession)
<section class="card shadow-sm border-0 mb-4"><div class="card-body p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div><div class="small text-muted">選択中の講習会</div><h2 class="h4 mb-1">{{ $selectedSession->name }}</h2><div>{{ $selectedSession->held_on?->format('Y年n月j日') }}{{ $selectedSession->venue ? '／'.$selectedSession->venue : '' }}</div></div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('tp_registration.sessions.export',$selectedSession) }}" class="btn btn-outline-success">受講者CSV</a>
      @if($selectedSession->status==='completed')
        <form method="POST" action="{{ route('tp_registration.sessions.reopen',$selectedSession) }}">@csrf<button class="btn btn-outline-warning" onclick="return confirm('確定を解除して修正可能にしますか？')">確定を解除</button></form>
      @endif
    </div>
  </div>

  @if($selectedSession->status!=='completed')
    <div class="rounded-3 bg-light p-3 mb-4">
      <form method="POST" action="{{ route('tp_registration.sessions.participants.add',$selectedSession) }}">@csrf
        <label class="form-label fw-bold">受講予定者を追加</label>
        <div class="row g-2"><div class="col-lg-9"><textarea name="license_nos" class="form-control" rows="3" placeholder="ライセンスNoを改行・空白・カンマ区切りで貼り付け（最大500名）" required></textarea></div><div class="col-lg-3 d-grid"><button class="btn btn-primary">一覧へ追加</button></div></div>
      </form>
    </div>
  @endif

  @if($selectedSession->participants->count())
    <form method="POST" action="{{ route('tp_registration.sessions.participants.update',$selectedSession) }}">@csrf @method('PUT')
      <div class="table-responsive"><table class="table align-middle tp-table">
        <thead class="table-light"><tr><th>No.</th><th>選手</th><th>現在の講習状態</th><th>今回の受講結果</th><th>今回の有効期限</th><th>備考</th></tr></thead>
        <tbody>@foreach($selectedSession->participants as $participant)<tr>
          <td>{{ $participant->bowler?->license_no }}</td>
          <td><a href="{{ route('pro_bowlers.edit',$participant->pro_bowler_id) }}">{{ $participant->bowler?->name_kanji }}</a></td>
          <td><span class="badge bg-light text-dark">{{ $participant->bowler?->training_compliance_status ?: 'unconfirmed' }}</span></td>
          <td><select name="participants[{{ $participant->id }}][attendance_status]" class="form-select" @disabled($selectedSession->status==='completed')>@foreach(['registered'=>'受講予定','attended'=>'受講済み','absent'=>'未受講','exempt'=>'免除'] as $value=>$label)<option value="{{ $value }}" @selected($participant->attendance_status===$value)>{{ $label }}</option>@endforeach</select></td>
          <td>{{ $participant->trainingRecord?->expires_at?->format('Y/m/d') ?? '確定後に計算' }}</td>
          <td><input name="participants[{{ $participant->id }}][notes]" value="{{ $participant->notes }}" class="form-control" @disabled($selectedSession->status==='completed')></td>
        </tr>@endforeach</tbody>
      </table></div>
      @if($selectedSession->status!=='completed')<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">受講結果を保存</button></div>@endif
    </form>
    @if($selectedSession->status!=='completed')
      <form method="POST" action="{{ route('tp_registration.sessions.finalize',$selectedSession) }}" class="mt-3">@csrf
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3 mb-0"><div><strong>結果確定</strong><br><span class="small">受講済みの方は受講日から3年間（3年後の前日まで）有効になります。未受講の方は、他に有効な履歴がなければ出場資格なしへ切り替わります。</span></div><button class="btn btn-warning" onclick="return confirm('全員の受講結果を確定し、出場資格へ反映しますか？')">結果を確定・資格へ反映</button></div>
      </form>
    @endif
  @else
    <div class="alert alert-secondary mb-0">受講予定者はまだ登録されていません。</div>
  @endif
</div></section>
@endif
@endsection
