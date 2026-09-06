@extends('layouts.app')
@section('title', '大会アーカイブ編集')
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3"><div><h1 class="h3 fw-bold mb-1">大会アーカイブ編集</h1><div class="text-muted">手修正した内容は次回の公式同期で上書きされません。</div></div><a href="{{ route('admin.tournament_archives.index') }}" class="btn btn-outline-secondary">一覧へ戻る</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form method="POST" action="{{ route('admin.tournament_archives.update', $archive) }}" class="card"><div class="card-body">@csrf @method('PUT')
  <div class="mb-3"><label class="form-label fw-bold">大会名</label><input class="form-control" name="title" value="{{ old('title',$archive->title) }}" required></div>
  <div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label fw-bold">開始日</label><input type="date" class="form-control" name="start_on" value="{{ old('start_on',$archive->start_on?->format('Y-m-d')) }}"></div><div class="col-md-3"><label class="form-label fw-bold">終了日</label><input type="date" class="form-control" name="end_on" value="{{ old('end_on',$archive->end_on?->format('Y-m-d')) }}"></div><div class="col-md-3"><label class="form-label fw-bold">状態</label><select class="form-select" name="status">@foreach(['completed'=>'開催済み','scheduled'=>'開催予定','cancelled'=>'開催中止','postponed'=>'開催延期'] as $key=>$label)<option value="{{ $key }}" @selected(old('status',$archive->status)===$key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3 d-flex align-items-end"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_public" value="1" @checked(old('is_public',$archive->is_public))><span class="form-check-label">一般公開する</span></label></div></div>
  <div class="mb-3"><label class="form-label fw-bold">本文（HTML可）</label><textarea class="form-control font-monospace" name="body_html" rows="18" required>{{ old('body_html',$archive->body_html) }}</textarea></div>
  <button class="btn btn-primary" type="submit">保存</button>
</div></form>
@endsection
