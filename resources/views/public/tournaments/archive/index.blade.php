@extends('public.layout')

@section('title', '過去の公式トーナメント｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '過去の公式トーナメント')

@push('styles')
<style>
  .archive-filter{display:grid;grid-template-columns:180px minmax(0,1fr) auto auto;gap:10px;align-items:end}
  .archive-filter label{display:grid;gap:4px;font-weight:700}.archive-filter select,.archive-filter input{min-height:42px;border:1px solid #b9c4d2;border-radius:4px;padding:7px 9px}
  .archive-list{display:grid;gap:10px}.archive-row{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:16px;align-items:center;padding:13px;border:1px solid var(--jpba-line);border-radius:5px}
  .archive-date{color:#53606f;font-weight:700}.archive-title{margin:0;font-size:1rem;font-weight:800}.archive-meta{margin-top:4px;color:#667085;font-size:.82rem}
  @media(max-width:700px){.archive-filter,.archive-row{grid-template-columns:1fr}.archive-row .jpba-small-button{justify-self:start}}
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">過去の公式トーナメント</h1>
<p>2016年以降に公式サイトで公開された大会ページ・大会資料・成績を、新サイト内に保存して掲載しています。</p>

<section class="jpba-panel">
  <form method="GET" action="{{ route('public.tournament_archives.index') }}" class="archive-filter">
    <label>年度<select name="year"><option value="">すべて</option>@foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (int)$year)>{{ $year }}年</option>@endforeach</select></label>
    <label>大会名<input name="keyword" value="{{ $keyword }}" placeholder="大会名で検索"></label>
    <button class="btn btn-primary" type="submit">検索</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.tournament_archives.index') }}">解除</a>
  </form>
</section>

<section class="jpba-panel">
  <h2 class="jpba-section-title">大会アーカイブ（{{ number_format($archives->total()) }}件）</h2>
  <div class="archive-list">
    @forelse($archives as $archive)
      <article class="archive-row">
        <div class="archive-date">{{ $archive->year }}年<br>{{ $archive->start_on?->format('n月j日') ?: '日程記録なし' }}</div>
        <div><h3 class="archive-title"><a href="{{ route('public.tournament_archives.show', $archive) }}">{{ $archive->title }}</a></h3><div class="archive-meta">保存資料 {{ count($archive->assets ?: []) }}点 @if($archive->status === 'cancelled') / 開催中止 @elseif($archive->status === 'postponed') / 開催延期 @endif</div></div>
        <a class="jpba-small-button" href="{{ route('public.tournament_archives.show', $archive) }}">詳細・資料</a>
      </article>
    @empty
      <p class="text-muted mb-0">該当する大会はありません。</p>
    @endforelse
  </div>
  <div class="mt-3">{{ $archives->links() }}</div>
</section>
@endsection
