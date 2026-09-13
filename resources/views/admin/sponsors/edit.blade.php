@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h1 class="h2 mb-1">{{ $sponsor->exists ? '協賛バナー編集' : '協賛バナー新規登録' }}</h1><p class="text-muted mb-0">公開期間外または非公開にすると、一般トップには表示されません。</p></div>
  <a class="btn btn-outline-secondary" href="{{ route('admin.sponsors.index') }}">一覧へ戻る</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form method="POST" enctype="multipart/form-data" action="{{ $sponsor->exists ? route('admin.sponsors.update', $sponsor) : route('admin.sponsors.store') }}">
  @csrf
  @if($sponsor->exists)@method('PUT')@endif
  <div class="row g-4">
    <div class="col-lg-8">
      <section class="card shadow-sm border-0"><div class="card-body p-4">
        <div class="row g-3">
          <div class="col-md-7"><label class="form-label fw-bold">協賛名</label><input name="name" value="{{ old('name', $sponsor->name) }}" class="form-control" required></div>
          <div class="col-md-5"><label class="form-label fw-bold">表示順</label><input type="number" name="sort_order" value="{{ old('sort_order', $sponsor->sort_order ?? 100) }}" min="0" max="65000" class="form-control" required></div>
          <div class="col-12"><label class="form-label fw-bold">リンク先URL</label><input type="url" name="website" value="{{ old('website', $sponsor->website) }}" class="form-control" placeholder="https://"></div>
          <div class="col-12"><label class="form-label fw-bold">画像の説明（alt）</label><input name="alt_text" value="{{ old('alt_text', $sponsor->alt_text) }}" class="form-control" placeholder="未入力の場合は協賛名を使用"></div>
          <div class="col-12"><label class="form-label fw-bold">管理メモ</label><textarea name="description" rows="3" class="form-control">{{ old('description', $sponsor->description) }}</textarea></div>
          <div class="col-md-6"><label class="form-label fw-bold">公開開始</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at', $sponsor->starts_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
          <div class="col-md-6"><label class="form-label fw-bold">公開終了</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at', $sponsor->ends_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
          <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="is_published" value="0"><input id="isPublished" type="checkbox" name="is_published" value="1" class="form-check-input" @checked(old('is_published', $sponsor->is_published ?? true))><label class="form-check-label fw-bold" for="isPublished">一般公開する</label></div></div>
        </div>
      </div></section>
    </div>
    <div class="col-lg-4">
      <section class="card shadow-sm border-0"><div class="card-body p-4">
        <label class="form-label fw-bold">バナー画像</label>
        @if($sponsor->logo_url)<div class="border rounded bg-light p-3 mb-3 text-center"><img src="{{ $sponsor->logo_url }}" alt="" style="max-width:100%;max-height:180px;object-fit:contain"></div>@endif
        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control" @required(!$sponsor->logo_path)>
        <p class="small text-muted mt-2 mb-0">JPEG・PNG・WebP、5MBまで。差し替え前の画像は監査用に自動削除しません。</p>
      </div></section>
    </div>
  </div>
  <div class="mt-4"><button class="btn btn-primary px-4">保存する</button></div>
</form>
@endsection
