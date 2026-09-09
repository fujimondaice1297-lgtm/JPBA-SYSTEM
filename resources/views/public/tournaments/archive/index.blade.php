@extends('public.layout')

@section('title', '公認大会・承認イベント｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '公認大会・承認イベント')

@push('styles')
<style>
  .archive-guide{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.archive-guide>div{padding:12px;border:1px solid var(--jpba-line);border-radius:5px;background:var(--jpba-soft)}
  .archive-filter{display:grid;grid-template-columns:180px 210px minmax(150px,1fr) minmax(180px,1fr) auto auto;gap:10px;align-items:end}
  .archive-filter label{display:grid;gap:4px;font-weight:700}.archive-filter select,.archive-filter input{min-height:42px;border:1px solid #b9c4d2;border-radius:4px;padding:7px 9px}
  .archive-list{display:grid;gap:10px}.archive-row{display:grid;grid-template-columns:125px minmax(0,1fr) auto;gap:16px;align-items:center;padding:13px;border:1px solid var(--jpba-line);border-radius:5px}
  .archive-date{color:#53606f;font-weight:700}.archive-title{margin:0;font-size:1rem;font-weight:800}.archive-meta{margin-top:4px;color:#667085;font-size:.82rem}.archive-badge{display:inline-flex;margin-bottom:4px;padding:2px 8px;border-radius:12px;background:#e8f0fb;color:var(--jpba-blue);font-size:.75rem;font-weight:800}.archive-badge.approved{background:#fff2dc;color:#8a4f00}
  @media(max-width:900px){.archive-filter{grid-template-columns:1fr 1fr}.archive-filter label:nth-child(n+3){grid-column:span 2}}
  @media(max-width:700px){.archive-guide,.archive-filter,.archive-row{grid-template-columns:1fr}.archive-filter label:nth-child(n+3){grid-column:auto}.archive-row .jpba-small-button{justify-self:start}}
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">公認大会・承認イベント</h1>
<p>公認トーナメントと、JPBAが開催を承認したイベントを年度別に掲載しています。現行サイトに残る大会資料・成績・オイルパターンも新サイト内へ保存しています。</p>

<section class="jpba-panel archive-guide" aria-label="大会区分の説明">
  <div><strong>公認トーナメント</strong><br><span class="text-muted">JPBA公式戦として成績・ポイント等の対象になる大会です。</span></div>
  <div><strong>承認イベント</strong><br><span class="text-muted">JPBAが承認したイベントです。公式ポイント・賞金・アベレージ・公認記録には算入されません。</span></div>
</section>

<section class="jpba-panel">
  <form method="GET" action="{{ route('public.tournament_archives.index') }}" class="archive-filter">
    <label>年度<select name="year"><option value="">すべて</option>@foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (int)$year)>{{ $year }}年</option>@endforeach</select></label>
    <label>大会区分<select name="classification"><option value="">すべて</option><option value="official_tournament" @selected($selectedClassification==='official_tournament')>公認トーナメント</option><option value="approved_event" @selected($selectedClassification==='approved_event')>承認イベント</option></select></label>
    <label>大会名・主催者等<input name="keyword" value="{{ $keyword }}" placeholder="大会名・主催者・承認番号"></label>
    <label>会場<input name="venue" value="{{ $venue }}" placeholder="会場名で検索"></label>
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
        <div>
          <span class="archive-badge {{ $archive->classification === 'approved_event' ? 'approved' : '' }}">{{ $archive->classification_label }}</span>
          <h3 class="archive-title"><a href="{{ route('public.tournament_archives.show', $archive) }}">{{ $archive->title }}</a></h3>
          <div class="archive-meta">{{ $archive->display_venue_name ?: '会場記録なし' }} / 保存資料 {{ count($archive->assets ?: []) }}点 @if($archive->status === 'cancelled') / 開催中止 @elseif($archive->status === 'postponed') / 開催延期 @endif</div>
        </div>
        <a class="jpba-small-button" href="{{ route('public.tournament_archives.show', $archive) }}">詳細・資料</a>
      </article>
    @empty
      <p class="text-muted mb-0">該当する大会はありません。</p>
    @endforelse
  </div>
  <div class="mt-3">{{ $archives->links() }}</div>
</section>
@endsection
