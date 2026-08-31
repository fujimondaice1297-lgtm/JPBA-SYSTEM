@extends('public.layout')

@section('title', '速報・成績｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '速報・成績')

@push('styles')
<style>
  .jpba-result-search {
    display: grid;
    grid-template-columns: 180px minmax(240px, 1fr) auto auto;
    gap: 10px;
    align-items: end;
  }
  .jpba-result-search label { display:block; font-weight:700; margin-bottom:4px; }
  .jpba-result-search input,
  .jpba-result-search select { width:100%; min-height:38px; border:1px solid var(--jpba-line); border-radius:4px; padding:6px 8px; }
  .jpba-result-list { display:grid; gap:12px; }
  .jpba-result-event { border:1px solid var(--jpba-line); border-radius:6px; padding:13px; }
  .jpba-result-event h3 { margin:0 0 4px; color:var(--jpba-blue); font-size:1.04rem; font-weight:700; }
  .jpba-result-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
  .jpba-result-action { display:inline-flex; align-items:center; min-height:34px; border-radius:4px; padding:5px 12px; text-decoration:none; font-weight:700; }
  .jpba-result-action.live { color:#fff; background:var(--jpba-blue); }
  .jpba-result-action.final { color:#fff; background:var(--jpba-red); }
  .jpba-result-action.detail { color:var(--jpba-blue); background:var(--jpba-soft); border:1px solid var(--jpba-line); }
  @media(max-width:720px) { .jpba-result-search { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">速報・成績</h1>

<section class="jpba-panel">
  <p class="mb-0">開催中大会の途中経過と、終了大会の全成績を確認できます。速報値は大会進行中に更新され、確定後は全成績へ反映されます。</p>
</section>

<section class="jpba-panel" aria-labelledby="season-trial-ranking-heading">
  <h2 id="season-trial-ranking-heading" class="jpba-section-title">シーズントライアル</h2>
  <div class="jpba-link-grid">
    <a href="{{ route('rankings.season_trial') }}">ST年間ポイントランキング</a>
    <a href="{{ route('rankings.season_trial_championship_priority') }}">STチャンピオンズ優先出場一覧</a>
  </div>
</section>

<section class="jpba-panel" aria-labelledby="result-search-heading">
  <h2 id="result-search-heading" class="jpba-section-title">大会を検索</h2>
  <form method="GET" action="{{ route('public.tournaments.live_results') }}" class="jpba-result-search">
    <div>
      <label for="year">年度</label>
      <select id="year" name="year">
        <option value="">すべて</option>
        @foreach($years as $year)
          <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}年</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="keyword">大会名・会場</label>
      <input id="keyword" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="例：東海オープン">
    </div>
    <button class="btn btn-primary">検索</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.tournaments.live_results') }}">解除</a>
  </form>
</section>

@if($externalLinks->isNotEmpty())
  <section class="jpba-panel" aria-labelledby="external-live-heading">
    <h2 id="external-live-heading" class="jpba-section-title">特設速報</h2>
    <div class="jpba-link-grid">
      @foreach($externalLinks as $item)
        <a href="{{ route('flash_news.public', $item) }}" target="_blank" rel="noopener">{{ $item->title }}</a>
      @endforeach
    </div>
  </section>
@endif

<section class="jpba-panel" aria-labelledby="result-list-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="result-list-heading" class="jpba-section-title mb-0">大会別 速報・成績</h2>
    <div class="text-muted">{{ number_format($tournaments->total()) }}大会</div>
  </div>

  @if($tournaments->count())
    <div class="jpba-result-list">
      @foreach($tournaments as $tournament)
        <article class="jpba-result-event">
          <h3>{{ $tournament->name }}</h3>
          <div class="text-muted">
            {{ optional($tournament->start_date)->format('Y年n月j日') ?: '開催日未定' }}
            @if($tournament->venue_name) / {{ $tournament->venue_name }} @endif
          </div>
          <div class="jpba-result-actions">
            @if((int) $tournament->game_scores_count > 0)
              <a class="jpba-result-action live" href="{{ route('public.tournaments.live', $tournament) }}">
                速報を見る
              </a>
            @endif
            @if((int) $tournament->official_results_count > 0)
              <a class="jpba-result-action final" href="{{ route('public.tournaments.results', $tournament) }}">
                全成績を見る（{{ number_format((int) $tournament->official_results_count) }}名）
              </a>
            @endif
            <a class="jpba-result-action detail" href="{{ route('public.tournaments.show', $tournament) }}">大会ページ</a>
          </div>
        </article>
      @endforeach
    </div>
    <div class="mt-3">{{ $tournaments->links() }}</div>
  @else
    <p class="mb-0 text-muted">条件に該当する公開済みの速報・成績はありません。</p>
  @endif
</section>
@endsection
