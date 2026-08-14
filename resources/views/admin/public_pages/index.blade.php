@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <div class="text-uppercase small text-primary fw-bold">PUBLIC CONTENT</div>
    <h1 class="h3 mb-1">一般公開ページ管理</h1>
    <p class="text-muted mb-0">旧サイトから移した固定情報は、ここを正本として編集・公開します。</p>
  </div>
  <a href="{{ route('admin.public_pages.create') }}" class="btn btn-primary">＋ 新しいページ</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row g-3">
  @forelse($pages as $page)
    <div class="col-12 col-md-6 col-xl-4">
      <article class="card h-100 shadow-sm border-0">
        <div class="card-body d-flex flex-column gap-3">
          <div class="d-flex justify-content-between gap-2 align-items-start">
            <div>
              <div class="small text-muted">{{ match($page->navigation_group){'association'=>'JPBAについて','instructor'=>'インストラクター','protest'=>'プロテスト','footer'=>'フッター',default=>'その他'} }}</div>
              <h2 class="h5 mb-0">{{ $page->title }}</h2>
            </div>
            <span class="badge {{ $page->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $page->is_published ? '公開中' : '非公開' }}</span>
          </div>
          <div class="small text-muted">/pages/{{ $page->slug }}<br>更新 {{ $page->updated_at?->format('Y/m/d H:i') }}</div>
          <div class="d-flex gap-2 mt-auto">
            <a href="{{ route('admin.public_pages.edit', $page) }}" class="btn btn-primary flex-fill">編集</a>
            @if($page->is_published)
              <a href="{{ route('public.managed_pages.show', $page) }}" target="_blank" class="btn btn-outline-secondary">公開確認</a>
            @endif
          </div>
        </div>
      </article>
    </div>
  @empty
    <div class="col-12"><div class="alert alert-info">固定ページはまだありません。</div></div>
  @endforelse
</div>
@endsection
