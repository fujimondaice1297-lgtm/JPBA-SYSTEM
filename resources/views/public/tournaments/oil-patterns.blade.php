@extends('public.layout')

@section('title', '公認オイルパターン｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '公認オイルパターン')

@push('styles')
<style>
  .oil-filter{display:grid;grid-template-columns:150px 190px minmax(180px,1fr) minmax(180px,1fr) auto auto;gap:10px;align-items:end}.oil-filter>*{min-width:0}.oil-filter label{display:grid;gap:4px;font-weight:700}.oil-filter select,.oil-filter input{width:100%;max-width:100%;min-width:0;min-height:42px;border:1px solid #b9c4d2;border-radius:4px;padding:7px 9px}
  .oil-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.oil-card{display:grid;min-width:0;gap:7px;padding:14px;border:1px solid var(--jpba-line);border-radius:6px;background:#fff;overflow-wrap:anywhere}.oil-card h3{margin:0;font-size:1rem}.oil-meta{color:#637083;font-size:.84rem}.oil-pattern-title{font-weight:800;color:var(--jpba-blue)}.oil-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  @media(max-width:900px){.oil-filter{grid-template-columns:1fr 1fr}.oil-filter label:nth-child(n+3){grid-column:span 2}}
  @media(max-width:700px){.oil-filter,.oil-list{grid-template-columns:1fr}.oil-filter label:nth-child(n+3){grid-column:auto}}
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">公認オイルパターン</h1>
<p>大会ページに保存されたオイルパターンを、大会・年度・会場から横断検索できます。資料は各大会で公開された当時の内容です。</p>

<section class="jpba-panel">
  <form method="GET" action="{{ route('public.oil_patterns.index') }}" class="oil-filter">
    <label>年度<select name="year"><option value="">すべて</option>@foreach($years as $year)<option value="{{ $year }}" @selected((string)($filters['year'] ?? '')===(string)$year)>{{ $year }}年</option>@endforeach</select></label>
    <label>大会区分<select name="classification"><option value="">すべて</option><option value="official_tournament" @selected(($filters['classification'] ?? '')==='official_tournament')>公認トーナメント</option><option value="approved_event" @selected(($filters['classification'] ?? '')==='approved_event')>承認イベント</option></select></label>
    <label>会場<select name="venue"><option value="">すべて</option>@foreach($venues as $venue)<option value="{{ $venue }}" @selected(($filters['venue'] ?? '')===$venue)>{{ $venue }}</option>@endforeach</select></label>
    <label>大会名・資料名<input name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="大会名・オイルパターン名"></label>
    <button class="btn btn-primary" type="submit">検索</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.oil_patterns.index') }}">解除</a>
  </form>
</section>

<section class="jpba-panel">
  <h2 class="jpba-section-title">保存済みオイルパターン（{{ number_format($patterns->total()) }}件）</h2>
  <div class="oil-list">
    @forelse($patterns as $pattern)
      <article class="oil-card">
        <div class="oil-meta">{{ $pattern['year'] }}年 / {{ $pattern['classification_label'] }}@if($pattern['start_on']) / {{ $pattern['start_on']->format('n月j日') }}@endif</div>
        <h3>{{ $pattern['tournament_title'] }}</h3>
        <div class="oil-meta">会場：{{ $pattern['venue_name'] ?: '記録なし' }}</div>
        <div class="oil-pattern-title">{{ $pattern['pattern_title'] }}</div>
        <div class="oil-actions">
          <a class="jpba-small-button" href="{{ asset($pattern['public_path']) }}" target="_blank" rel="noopener">資料を開く</a>
          @if($pattern['source_type']==='archive')<a href="{{ route('public.tournament_archives.show', $pattern['source_id']) }}">大会情報</a>@else<a href="{{ route('public.tournaments.show', $pattern['source_id']) }}">大会情報</a>@endif
        </div>
      </article>
    @empty
      <p class="text-muted mb-0">該当するオイルパターンはありません。</p>
    @endforelse
  </div>
  <div class="mt-3">{{ $patterns->links() }}</div>
</section>
@endsection
