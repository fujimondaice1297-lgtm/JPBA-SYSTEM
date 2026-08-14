@extends('layouts.app')

@push('styles')
<style>
  .compliance-hero{border:0;border-radius:18px;background:linear-gradient(135deg,#312e81,#7c3aed);color:#fff}
  .compliance-stat{border:0;border-radius:14px;box-shadow:0 4px 14px rgba(16,24,40,.06)}
  .status-pill{display:inline-flex;align-items:center;gap:6px;padding:.35rem .65rem;border-radius:999px;font-size:.78rem;font-weight:800}
  .status-valid{background:#dcfce7;color:#166534}.status-warning{background:#fef3c7;color:#92400e}.status-danger{background:#fee2e2;color:#991b1b}.status-muted{background:#e5e7eb;color:#374151}.status-info{background:#dbeafe;color:#1e40af}
</style>
@endpush

@section('content')
@php
  $labels=['valid'=>'受講済み（有効）','official_list_valid'=>'受講済み（公式一覧）','expiring_this_year'=>'今年度期限','expiring_next_year'=>'次年度期限・通知対象','expired'=>'過去受講歴あり・期限切れ／大会出場権利なし','missing'=>'未受講／大会出場権利なし','unconfirmed'=>'未判定','exempt'=>'免除'];
  $classes=['valid'=>'status-valid','official_list_valid'=>'status-valid','expiring_this_year'=>'status-warning','expiring_next_year'=>'status-warning','expired'=>'status-danger','missing'=>'status-danger','unconfirmed'=>'status-info','exempt'=>'status-valid'];
@endphp

<section class="card compliance-hero shadow-sm mb-4"><div class="card-body p-4 p-lg-5">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><div class="small text-white-50 fw-bold">TRAINING COMPLIANCE</div><h1 class="h2 mt-1 mb-2">講習受講状況・更新通知</h1><p class="mb-0 text-white-50">有効期限は受講日から3年間。期限が切れる前年度に更新案内を1回送信し、大会出場資格へ反映します。</p></div>
    <div class="d-flex gap-2 flex-wrap"><a href="{{ route('tp_registration.index') }}" class="btn btn-light">講習会・受講者管理</a><a href="{{ route('admin.compliance.export',['status'=>$status==='action_required'?'all':$status]) }}" class="btn btn-outline-light">CSV出力</a></div>
  </div>
</div></section>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-4">
  @foreach(['valid','official_list_valid','expiring_next_year','expired','missing','unconfirmed'] as $key)
    <div class="col-6 col-xl"><a href="{{ route('admin.compliance.index',['status'=>$key]) }}" class="card compliance-stat text-decoration-none h-100"><div class="card-body"><div class="small text-muted">{{ $labels[$key] }}</div><div class="h3 mb-0">{{ number_format((int)($statusCounts[$key] ?? 0)) }}</div></div></a></div>
  @endforeach
</div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="small fw-bold text-primary mb-1">JPBA公式修了者一覧</div>
      @if($latestOfficialList)
        <h2 class="h5 mb-1">{{ $latestOfficialList->title }}</h2>
        <div class="text-muted small">
          公式掲載 {{ $latestOfficialList->source_published_at?->format('Y/m/d H:i') }}　／　
          有効期間 {{ $latestOfficialList->valid_from?->format('Y/m/d') }}～{{ $latestOfficialList->valid_through?->format('Y/m/d') }}
        </div>
        <div class="mt-2 d-flex flex-wrap gap-2">
          <span class="badge text-bg-primary">掲載 {{ number_format($latestOfficialList->total_count) }}名</span>
          <span class="badge text-bg-success">照合 {{ number_format($latestOfficialList->matched_count) }}名</span>
          <span class="badge text-bg-secondary">非アクティブ {{ number_format($latestOfficialList->inactive_count) }}名</span>
          <span class="badge {{ $latestOfficialList->unmatched_count ? 'text-bg-danger' : 'text-bg-light' }}">未照合 {{ number_format($latestOfficialList->unmatched_count) }}名</span>
        </div>
      @else
        <h2 class="h5 mb-1">未取り込み</h2>
        <p class="text-muted mb-0">公開PDFと選手マスタを照合し、確認できた人だけを受講済みにします。</p>
      @endif
      <p class="small text-muted mb-0 mt-2">公開PDFに正確な受講日がないため、日付は推測せず「公式一覧で確認済み」として保存します。</p>
    </div>
    <form method="POST" action="{{ route('admin.compliance.sync_official_list') }}">
      @csrf
      <button class="btn btn-primary" onclick="return confirm('JPBA公式修了者一覧を照合・取り込みしますか？')">公式修了者一覧を同期</button>
    </form>
  </div>
</div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body">
  <div class="row g-3 align-items-end">
    <div class="col-lg-7">
      <h2 class="h5">初回移行・期限の再判定</h2>
      <p class="text-muted mb-0">旧データの受講履歴を登録し終えた後に実行してください。「移行確認待ち」を含む全トーナメントプレイヤーを、有効・期限切れ・未受講へ再分類します。</p>
    </div>
    <div class="col-lg-5 d-grid">
      <form method="POST" action="{{ route('admin.compliance.reconcile') }}">@csrf<button class="btn btn-warning w-100" onclick="return confirm('全トーナメントプレイヤーを現在の講習履歴で判定します。履歴未登録者は「未受講／出場資格なし」になります。実行しますか？')">全会員を現在の履歴で再判定</button></form>
    </div>
  </div>
</div></div>

<div class="card shadow-sm border-0"><div class="card-body p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <form method="GET" action="{{ route('admin.compliance.index') }}" class="row g-2 flex-grow-1">
      <div class="col-md-4"><label class="form-label">状態</label><select name="status" class="form-select"><option value="action_required" @selected($status==='action_required')>要対応のみ</option><option value="all" @selected($status==='all')>すべて</option>@foreach($labels as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-md-5"><label class="form-label">選手検索</label><input name="q" value="{{ $keyword }}" class="form-control" placeholder="氏名・ライセンスNo"></div>
      <div class="col-md-3 d-flex gap-2 align-items-end"><button class="btn btn-primary flex-fill">検索</button><a href="{{ route('admin.compliance.index') }}" class="btn btn-outline-secondary">解除</a></div>
    </form>
  </div>

  <form method="POST" action="{{ route('admin.compliance.notify') }}">@csrf
    <div class="alert alert-primary d-flex flex-wrap align-items-end justify-content-between gap-3">
      <div><strong>更新案内メール</strong><div class="small">チェックなしの場合は、指定年に期限が切れる全会員が対象です。送信済みの方へは重複送信しません。</div></div>
      <div class="d-flex gap-2 align-items-end"><div><label class="form-label small mb-1">期限年</label><input type="number" name="expiry_year" value="{{ now()->addYear()->year }}" min="2000" max="2100" class="form-control" style="width:110px"></div><button class="btn btn-primary" onclick="return confirm('対象者へ更新案内メールを送信しますか？')">更新案内を送る</button></div>
    </div>

    <div class="table-responsive"><table class="table align-middle">
      <thead class="table-light"><tr><th><input type="checkbox" id="checkAll"></th><th>ライセンスNo</th><th>氏名</th><th>受講状態</th><th>最終受講日</th><th>有効期限</th><th>確認根拠</th><th>メール</th><th>操作</th></tr></thead>
      <tbody>@forelse($bowlers as $bowler)@php($key=$bowler->training_compliance_status ?: 'unconfirmed')@php($evidence=$evidenceByBowler->get($bowler->id, []))<tr>
        <td><input type="checkbox" name="bowler_ids[]" value="{{ $bowler->id }}" class="row-check"></td>
        <td>{{ $bowler->license_no }}</td><td class="fw-bold">{{ $bowler->name_kanji }}</td>
        <td><span class="status-pill {{ $classes[$key] ?? 'status-muted' }}">{{ $labels[$key] ?? $key }}</span></td>
        <td>{{ data_get($evidence, 'completed_at')?->format('Y/m/d') ?? '―' }}</td>
        <td>{{ data_get($evidence, 'expires_at')?->format('Y/m/d') ?? '―' }}</td>
        <td class="small">@if(data_get($evidence, 'official_evidence')){{ data_get($evidence, 'official_evidence.officialList.title') }} @elseif(data_get($evidence, 'record'))個別受講記録 @else― @endif</td>
        <td>{{ $bowler->userAccount?->email ?: ($bowler->email ?: '未登録') }}</td>
        <td><a href="{{ route('pro_bowlers.edit',$bowler) }}" class="btn btn-sm btn-outline-primary">選手編集</a></td>
      </tr>@empty<tr><td colspan="9" class="text-center text-muted py-4">条件に該当する選手はいません。</td></tr>@endforelse</tbody>
    </table></div>
  </form>
  <div class="mt-3">{{ $bowlers->links() }}</div>
</div></div>
@endsection

@push('scripts')
<script>document.getElementById('checkAll')?.addEventListener('change',e=>document.querySelectorAll('.row-check').forEach(c=>c.checked=e.target.checked));</script>
@endpush
