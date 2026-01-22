@extends('layouts.app')

@section('content')
<h1>組織の編集</h1>

<form method="POST" action="{{ route('organizations.update',$org->id) }}">
  @csrf @method('PUT')
  <div class="mb-3">
    <label class="form-label">名称 <span class="text-danger">*</span></label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name',$org->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="mb-3">
    <label class="form-label">URL</label>
    <input name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url',$org->url) }}" placeholder="https://example.com">
    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary">更新</button>
    <a href="{{ route('organizations.index') }}" class="btn btn-secondary">一覧へ戻る</a>
  </div>
</form>
@endsection
