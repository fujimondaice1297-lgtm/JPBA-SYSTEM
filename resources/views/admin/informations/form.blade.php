@php
  $isEdit = isset($information) && $information->exists;
  $action = $isEdit ? route('admin.informations.update', $information) : route('admin.informations.store');

  $fmt = function ($dt) {
    if (!$dt) return '';
    try { return \Carbon\Carbon::parse($dt)->format('Y-m-d\TH:i'); } catch (\Throwable $e) { return ''; }
  };
@endphp

@if($errors->any())
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1">入力内容に誤りがあります</div>
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ $action }}" class="card">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">タイトル</label>
      <input class="form-control" name="title" value="{{ old('title', $information->title) }}" required>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">カテゴリ</label>
        <select class="form-select" name="category">
          <option value="">（未指定）</option>
          @foreach($categories as $c)
            <option value="{{ $c }}" {{ old('category', $information->category) === $c ? 'selected' : '' }}>{{ $c }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">公開対象</label>
        <select class="form-select" name="audience" required>
          @foreach($audiences as $a)
            <option value="{{ $a }}" {{ old('audience', $information->audience ?: 'public') === $a ? 'selected' : '' }}>{{ $a }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
          @php $checked = old('is_public', $information->exists ? (bool)$information->is_public : true); @endphp
          <input class="form-check-input" type="checkbox" name="is_public" value="1" {{ $checked ? 'checked' : '' }}>
          <label class="form-check-label">公開ON</label>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-4">
        <label class="form-label">公開開始</label>
        <input class="form-control" type="datetime-local" name="starts_at"
               value="{{ old('starts_at', $fmt($information->starts_at)) }}">
      </div>

      <div class="col-md-4">
        <label class="form-label">公開終了</label>
        <input class="form-control" type="datetime-local" name="ends_at"
               value="{{ old('ends_at', $fmt($information->ends_at)) }}">
      </div>

      <div class="col-md-4">
        <label class="form-label">講習ID（required_training_id）</label>
        <input class="form-control" name="required_training_id"
               value="{{ old('required_training_id', $information->required_training_id) }}">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">本文</label>
      <textarea class="form-control" name="body" rows="10" required>{{ old('body', $information->body) }}</textarea>
    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary">{{ $isEdit ? '更新' : '作成' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.informations.index') }}">一覧へ戻る</a>
  </div>
</form>