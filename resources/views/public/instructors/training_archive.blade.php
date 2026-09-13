@extends('public.layout')

@section('title', 'インストラクター講習資料アーカイブ｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'インストラクター講習資料アーカイブ')

@push('styles')
<style>
  .instructor-archive-filter{display:grid;grid-template-columns:180px minmax(0,1fr) auto auto;gap:10px;align-items:end}
  .instructor-archive-list{display:grid;gap:12px}
  .instructor-archive-item{padding:16px;border:1px solid var(--jpba-line);border-left:5px solid var(--jpba-blue);border-radius:6px;background:#fff}
  .instructor-archive-date{color:#667085;font-size:.86rem;font-weight:700}
  .instructor-archive-title{margin:.25rem 0 .7rem;color:var(--jpba-blue);font-size:1.02rem;font-weight:800}
  .instructor-archive-files{display:flex;flex-wrap:wrap;gap:7px}
  @media(max-width:700px){.instructor-archive-filter{grid-template-columns:1fr}.instructor-archive-filter .btn{width:100%}}
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">インストラクター講習資料アーカイブ</h1>
<p>資格取得講習会、専門講習会、研修会の過年度案内と保存済み資料を年度別に確認できます。リンク先はすべて新サイト内に保存した記事・添付資料です。</p>

<section class="jpba-panel">
  <form method="GET" action="{{ route('public.instructors.training_archive') }}" class="instructor-archive-filter">
    <div>
      <label class="form-label fw-bold" for="archiveYear">年度</label>
      <select id="archiveYear" name="year" class="form-select">
        <option value="">すべて</option>
        @foreach($availableYears as $year)
          <option value="{{ $year }}" @selected((int)$selectedYear === (int)$year)>{{ $year }}年</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="form-label fw-bold" for="archiveKeyword">資料名</label>
      <input id="archiveKeyword" name="q" value="{{ $keyword }}" class="form-control" placeholder="例：資格取得講習会">
    </div>
    <button class="btn btn-primary" type="submit">表示</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.instructors.training_archive') }}">解除</a>
  </form>
</section>

<section class="jpba-panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="jpba-section-title mb-0">保存資料</h2>
    <span class="text-muted">{{ number_format($informations->total()) }}件</span>
  </div>
  <div class="instructor-archive-list">
    @forelse($informations as $information)
      <article class="instructor-archive-item">
        <div class="instructor-archive-date">{{ $information->published_at?->format('Y年n月j日') ?? '日付未設定' }}</div>
        <h3 class="instructor-archive-title">
          <a href="{{ route('informations.show', $information) }}">{{ $information->title }}</a>
        </h3>
        @if($information->files->isNotEmpty())
          <div class="instructor-archive-files" aria-label="添付資料">
            @foreach($information->files as $file)
              <a class="jpba-small-button" href="{{ route('information_files.download', $file) }}">
                {{ $file->title ?: '添付資料' }}
              </a>
            @endforeach
          </div>
        @else
          <span class="text-muted small">本文のみ</span>
        @endif
      </article>
    @empty
      <div class="jpba-empty">条件に一致する講習資料はありません。</div>
    @endforelse
  </div>
  @if($informations->hasPages())
    <div class="mt-3">{{ $informations->links() }}</div>
  @endif
</section>

<div class="mt-3">
  <a class="jpba-outline-button" href="{{ route('public.instructors.index') }}">インストラクター情報へ戻る</a>
</div>
@endsection
