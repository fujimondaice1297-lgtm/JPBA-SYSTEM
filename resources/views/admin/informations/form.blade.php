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

@if($isEdit && $information->source_type)
  <div class="alert alert-info">
    旧サイトから移行した記事です。本文はHTML形式で編集できます。手修正した内容は、次回の自動取込でも上書きされません。
  </div>
@endif

<form method="POST" action="{{ $action }}" class="card" enctype="multipart/form-data">
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
      <div class="col-md-3">
        <label class="form-label">記事の公開日</label>
        <input class="form-control" type="datetime-local" name="published_at"
               value="{{ old('published_at', $fmt($information->published_at)) }}">
        <div class="form-text">一覧の並び順と記事の日付に使用します。</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">公開開始</label>
        <input class="form-control" type="datetime-local" name="starts_at"
               value="{{ old('starts_at', $fmt($information->starts_at)) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">公開終了</label>
        <input class="form-control" type="datetime-local" name="ends_at"
               value="{{ old('ends_at', $fmt($information->ends_at)) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">講習ID（required_training_id）</label>
        <input class="form-control" name="required_training_id"
               value="{{ old('required_training_id', $information->required_training_id) }}">
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">本文</label>
      <textarea class="form-control" name="body" rows="10" required>{{ old('body', $information->body) }}</textarea>
      @if($isEdit && ($information->body_format ?? 'plain') === 'html')
        <div class="form-text">移行記事はHTML形式です。保存時に危険なタグや属性は自動除去されます。</div>
        <details class="border rounded p-3 mt-2 bg-light">
          <summary class="fw-semibold" style="cursor:pointer">現在の表示イメージを確認</summary>
          <div class="bg-white border rounded p-3 mt-3 jpba-information-body">{!! $information->body !!}</div>
        </details>
      @endif
    </div>

    @if($isEdit && $information->files->isNotEmpty())
      <div class="mt-4">
        <h5>現在の画像・添付資料</h5>
        <div class="vstack gap-2">
          @foreach($information->files as $file)
            <div class="border rounded p-3">
              <div class="row g-3 align-items-center">
                @if(str_contains(strtolower((string) $file->type), 'image') && $file->publicUrl())
                  <div class="col-auto">
                    <img src="{{ $file->publicUrl() }}" alt="" style="width:96px;height:72px;object-fit:cover" class="rounded border">
                  </div>
                @endif
                <div class="col-md">
                  <label class="form-label small">表示名</label>
                  <input class="form-control" name="existing_attachment_titles[{{ $file->id }}]" value="{{ old('existing_attachment_titles.'.$file->id, $file->title) }}">
                </div>
                <div class="col-md-2">
                  <label class="form-label small">公開範囲</label>
                  <select class="form-select" name="existing_attachment_visibility[{{ $file->id }}]">
                    <option value="public" {{ old('existing_attachment_visibility.'.$file->id, $file->visibility) === 'public' ? 'selected' : '' }}>一般公開</option>
                    <option value="members" {{ old('existing_attachment_visibility.'.$file->id, $file->visibility) === 'members' ? 'selected' : '' }}>会員のみ</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <div class="form-check mt-md-4">
                    <input class="form-check-input" type="checkbox" name="remove_attachment_ids[]" value="{{ $file->id }}" id="remove-file-{{ $file->id }}">
                    <label class="form-check-label text-danger" for="remove-file-{{ $file->id }}">記事から外す</label>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="mt-4">
      <label class="form-label fw-semibold">画像・添付資料を追加</label>
      <input class="form-control" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.zip">
      <div class="form-text">1回20点まで、1点20MBまで追加できます。画像は記事内で一覧表示され、PDF等はダウンロード資料として表示されます。</div>
    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary">{{ $isEdit ? '更新' : '作成' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.informations.index') }}">一覧へ戻る</a>
  </div>
</form>
