@extends('layouts.app')

@push('styles')
<style>
  .page-editor-shell{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}
  .page-editor-card{border:1px solid #e2e7ee;border-radius:16px;background:#fff;box-shadow:0 5px 18px rgba(16,24,40,.05)}
  .page-editor-toolbar{position:sticky;top:76px;z-index:10;display:flex;flex-wrap:wrap;gap:6px;padding:10px;border-bottom:1px solid #e2e7ee;background:#f8fafc;border-radius:16px 16px 0 0}
  .page-editor-toolbar button{min-height:36px;border:1px solid #cfd7e3;border-radius:8px;background:#fff;padding:5px 10px;font-weight:700}
  .page-editor-body{min-height:520px;padding:24px;outline:0;line-height:1.75;overflow-wrap:anywhere}
  .page-editor-body:focus{box-shadow:inset 0 0 0 3px rgba(13,110,253,.14)}
  .page-editor-body h2{margin:1.6rem 0 .7rem;padding-bottom:.4rem;border-bottom:2px solid #d8dee8;color:#174a8b;font-size:1.15rem}
  .page-editor-body table{width:100%;border-collapse:collapse}.page-editor-body th,.page-editor-body td{border:1px solid #d8dee8;padding:8px}.page-editor-body th{background:#f5f7fa}
  .page-settings{position:sticky;top:82px}
  @media(max-width:991px){.page-editor-shell{grid-template-columns:1fr}.page-settings{position:static}.page-editor-toolbar{top:68px}}
</style>
@endpush

@section('content')
@php($editing = $page->exists)
<form method="POST" action="{{ $editing ? route('admin.public_pages.update', $page) : route('admin.public_pages.store') }}" id="pageForm">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="text-uppercase small text-primary fw-bold">VISUAL EDITOR</div>
      <h1 class="h3 mb-1">{{ $editing ? $page->title.'を編集' : '一般公開ページを作成' }}</h1>
      <p class="text-muted mb-0">実際の公開ページに近い見た目で本文を直接編集できます。</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.public_pages.index') }}" class="btn btn-outline-secondary">一覧へ戻る</a>
      @if($editing && $page->is_published)<a href="{{ route('public.managed_pages.show', $page) }}" target="_blank" class="btn btn-outline-primary">公開確認</a>@endif
      <button class="btn btn-primary">保存</button>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>入力内容を確認してください。</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="page-editor-shell">
    <section class="page-editor-card">
      <div class="page-editor-toolbar" aria-label="本文編集ツール">
        <button type="button" data-command="formatBlock" data-value="p">本文</button>
        <button type="button" data-command="formatBlock" data-value="h2">見出し</button>
        <button type="button" data-command="bold">太字</button>
        <button type="button" data-command="insertUnorderedList">箇条書き</button>
        <button type="button" data-command="insertOrderedList">番号付き</button>
        <button type="button" id="insertLink">リンク</button>
        <button type="button" data-command="removeFormat">書式解除</button>
      </div>
      <div id="visualEditor" class="page-editor-body" contenteditable="true" role="textbox" aria-multiline="true">{!! old('body_html', $page->body_html) !!}</div>
      <textarea id="bodyHtml" name="body_html" class="d-none">{{ old('body_html', $page->body_html) }}</textarea>
    </section>

    <aside class="page-settings">
      <div class="card shadow-sm border-0 mb-3"><div class="card-body">
        <h2 class="h6 fw-bold mb-3">ページ設定</h2>
        <div class="mb-3"><label class="form-label fw-bold">ページ名</label><input name="title" value="{{ old('title',$page->title) }}" class="form-control" required></div>
        <div class="mb-3"><label class="form-label fw-bold">URL用の名前</label><div class="input-group"><span class="input-group-text">/pages/</span><input name="slug" value="{{ old('slug',$page->slug) }}" class="form-control" pattern="[a-z0-9][a-z0-9-]*" required></div><div class="form-text">半角小文字・数字・ハイフン</div></div>
        <div class="mb-3"><label class="form-label fw-bold">表示グループ</label><select name="navigation_group" class="form-select">@foreach(['association'=>'JPBAについて','instructor'=>'インストラクター','protest'=>'プロテスト','footer'=>'フッター','other'=>'その他'] as $value=>$label)<option value="{{ $value }}" @selected(old('navigation_group',$page->navigation_group)===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label fw-bold">並び順</label><input type="number" name="sort_order" value="{{ old('sort_order',$page->sort_order) }}" class="form-control" min="0" max="65000"></div>
        <div class="form-check form-switch"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" role="switch" name="is_published" value="1" id="published" @checked(old('is_published',$page->is_published))><label class="form-check-label fw-bold" for="published">一般公開する</label></div>
      </div></div>
      <div class="card shadow-sm border-0"><div class="card-body">
        <h2 class="h6 fw-bold mb-3">移行元の記録</h2>
        <label class="form-label">現行サイトURL</label><input type="url" name="source_url" value="{{ old('source_url',$page->source_url) }}" class="form-control" placeholder="https://..."><div class="form-text">一般ページには表示しません。内容確認用の管理記録です。</div>
      </div></div>
    </aside>
  </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
  const editor = document.getElementById('visualEditor');
  const hidden = document.getElementById('bodyHtml');
  const form = document.getElementById('pageForm');
  document.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
    editor.focus();
    document.execCommand(button.dataset.command, false, button.dataset.value || null);
  }));
  document.getElementById('insertLink')?.addEventListener('click', () => {
    const url = window.prompt('リンク先URLを入力してください');
    if (url) { editor.focus(); document.execCommand('createLink', false, url); }
  });
  form.addEventListener('submit', () => { hidden.value = editor.innerHTML; });
})();
</script>
@endpush
