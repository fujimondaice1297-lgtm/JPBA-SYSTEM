@extends('layouts.app')

@push('styles')
<style>
  .sponsor-hero{border:0;border-radius:18px;background:linear-gradient(135deg,#7c2d12,#c2410c);color:#fff}
  .sponsor-card{border:0;border-radius:14px;box-shadow:0 5px 18px rgba(16,24,40,.08);overflow:hidden}
  .sponsor-preview{height:110px;display:flex;align-items:center;justify-content:center;padding:14px;background:#f8fafc;border-bottom:1px solid #e5e7eb}
  .sponsor-preview img{max-width:100%;max-height:100%;object-fit:contain}
</style>
@endpush

@section('content')
<section class="card sponsor-hero mb-4"><div class="card-body p-4 p-lg-5">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><div class="small text-white-50 fw-bold">PUBLIC SPONSOR BANNERS</div><h1 class="h2 mb-2">協賛バナー管理</h1><p class="mb-0 text-white-50">画像、リンク、公開期間、表示順を管理し、一般公開トップへ反映します。</p></div>
    <a class="btn btn-light" href="{{ route('admin.sponsors.create') }}">＋ 新規バナー</a>
  </div>
</div></section>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row g-3">
  @forelse($sponsors as $sponsor)
    @php
      $now = now();
      $inPeriod = (!$sponsor->starts_at || $sponsor->starts_at->lte($now)) && (!$sponsor->ends_at || $sponsor->ends_at->gte($now));
      $isVisible = $sponsor->is_published && $inPeriod;
    @endphp
    <div class="col-12 col-md-6 col-xl-4">
      <article class="card sponsor-card h-100">
        <div class="sponsor-preview">
          @if($sponsor->logo_url)<img src="{{ $sponsor->logo_url }}" alt="{{ $sponsor->alt_text ?: $sponsor->name }}">@else<span class="text-muted">画像未登録</span>@endif
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between gap-2">
            <h2 class="h5 mb-1">{{ $sponsor->name }}</h2>
            <span class="badge {{ $isVisible ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $isVisible ? '公開中' : '非表示' }}</span>
          </div>
          <div class="small text-muted mt-2">表示順 {{ $sponsor->sort_order }}</div>
          <div class="small text-muted">
            {{ $sponsor->starts_at?->format('Y/m/d H:i') ?? '開始指定なし' }} ～
            {{ $sponsor->ends_at?->format('Y/m/d H:i') ?? '終了指定なし' }}
          </div>
          @if($sponsor->website)<div class="small text-truncate mt-2">{{ $sponsor->website }}</div>@endif
        </div>
        <div class="card-footer bg-white border-0 pt-0"><a class="btn btn-outline-primary w-100" href="{{ route('admin.sponsors.edit', $sponsor) }}">編集</a></div>
      </article>
    </div>
  @empty
    <div class="col-12"><div class="alert alert-info">協賛バナーはまだ登録されていません。</div></div>
  @endforelse
</div>
@endsection
