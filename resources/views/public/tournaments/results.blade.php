@extends('public.layout')

@section('title', '全成績｜' . $tournament->name . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '速報・成績')

@push('styles')
<style>
  .jpba-results-table-wrap { overflow-x:auto; }
  .jpba-results-table { width:100%; min-width:900px; border-collapse:collapse; }
  .jpba-results-table th,
  .jpba-results-table td { border:1px solid var(--jpba-line); padding:8px 9px; text-align:right; white-space:nowrap; }
  .jpba-results-table th { background:var(--jpba-soft); color:#35465a; }
  .jpba-results-table th.player,
  .jpba-results-table td.player { text-align:left; min-width:210px; }
  .jpba-result-player { display:flex; align-items:center; gap:8px; }
  .jpba-result-player img { width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid var(--jpba-line); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <div>
    <h1 class="jpba-page-title mb-1">全成績</h1>
    <div class="fw-bold">{{ $tournament->name }}</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    @if($tournament->gameScores()->exists())
      <a class="jpba-small-button" href="{{ route('public.tournaments.live', $tournament) }}">速報</a>
    @endif
    <a class="jpba-small-button" href="{{ route('public.tournaments.show', $tournament) }}">大会ページへ戻る</a>
  </div>
</div>

<section class="jpba-panel">
  <form method="GET" action="{{ route('public.tournaments.results', $tournament) }}" class="d-flex flex-wrap gap-2 align-items-end">
    <div class="flex-grow-1">
      <label for="keyword" class="fw-bold d-block mb-1">選手名・ライセンスNo.</label>
      <input id="keyword" name="keyword" class="form-control" value="{{ $keyword }}" placeholder="選手を検索">
    </div>
    <button class="btn btn-primary">検索</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.tournaments.results', $tournament) }}">解除</a>
  </form>
</section>

<section class="jpba-panel" aria-labelledby="all-results-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="all-results-heading" class="jpba-section-title mb-0">最終成績</h2>
    <div class="text-muted">{{ number_format($results->total()) }}名</div>
  </div>

  <div class="jpba-results-table-wrap">
    <table class="jpba-results-table">
      <thead>
        <tr>
          <th>順位</th>
          <th class="player">選手</th>
          <th>ライセンスNo.</th>
          <th>ゲーム数</th>
          <th>トータルピン</th>
          <th>アベレージ</th>
          <th>ポイント</th>
          <th>獲得賞金</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results as $result)
          @php
            $bowler = $result->publicBowler;
            $entry = $result->public_entry;
            $playerName = $bowler?->name_kanji ?: ($result->amateur_name ?: '-');
            $licenseDisplay = str_starts_with(strtoupper((string)$result->pro_bowler_license_no), 'AMATEUR-')
                ? 'アマ'
                : ($result->pro_bowler_license_no ?: '-');
          @endphp
          <tr>
            <td>{{ $result->ranking ?: '-' }}</td>
            <td class="player">
              <div class="jpba-result-player">
                @if($bowler?->public_photo_url)
                  <img src="{{ $bowler->public_photo_url }}" alt="">
                @endif
                <div>
                  @if($entry)
                    <a href="{{ route('scores.entry_balls.show', ['entry' => $entry['id'], 'public' => 1, 'return' => request()->fullUrl()]) }}">{{ $playerName }}</a>
                    <div class="small text-muted">大会登録ボール {{ number_format((int)$entry['ball_count']) }}個</div>
                  @elseif($bowler)
                    <a href="{{ route('public.players.show', $bowler) }}">{{ $playerName }}</a>
                  @else
                    {{ $playerName }}
                  @endif
                </div>
              </div>
            </td>
            <td>{{ $licenseDisplay }}</td>
            <td>{{ $result->games ?: '-' }}</td>
            <td>{{ $result->total_pin !== null ? number_format((int)$result->total_pin) : '-' }}</td>
            <td>{{ $result->average !== null ? number_format((float)$result->average, 2) : '-' }}</td>
            <td>{{ $result->points !== null ? number_format((float)$result->points, 2) : '-' }}</td>
            <td>{{ $result->prize_money !== null ? '¥'.number_format((int)$result->prize_money) : '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($results->isEmpty())
    <p class="mt-3 mb-0 text-muted">条件に該当する成績はありません。</p>
  @endif
  <div class="mt-3">{{ $results->links() }}</div>
</section>
@endsection
