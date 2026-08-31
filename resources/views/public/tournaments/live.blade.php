@extends('public.layout')

@section('title', '速報｜' . $tournament->name . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '速報・成績')

@push('styles')
<style>
  .jpba-live-note { border-left:5px solid var(--jpba-red); background:#fff7f7; padding:10px 12px; margin-bottom:14px; }
  .jpba-live-form { display:grid; grid-template-columns:200px 150px minmax(220px,1fr) auto auto; gap:10px; align-items:end; }
  .jpba-live-form label { display:block; font-weight:700; margin-bottom:4px; }
  .jpba-live-form input,
  .jpba-live-form select { width:100%; min-height:38px; border:1px solid var(--jpba-line); border-radius:4px; padding:6px 8px; }
  .jpba-live-table-wrap { overflow-x:auto; }
  .jpba-live-table { width:100%; min-width:760px; border-collapse:collapse; }
  .jpba-live-table th,
  .jpba-live-table td { border:1px solid var(--jpba-line); padding:7px 8px; text-align:right; white-space:nowrap; }
  .jpba-live-table th { background:var(--jpba-soft); color:#35465a; }
  .jpba-live-table th.player,
  .jpba-live-table td.player { text-align:left; min-width:190px; }
  .jpba-live-player { display:flex; align-items:center; gap:8px; }
  .jpba-live-player img { width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid var(--jpba-line); }
  @media(max-width:820px) { .jpba-live-form { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <div>
    <h1 class="jpba-page-title mb-1">速報</h1>
    <div class="fw-bold">{{ $tournament->name }}</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    @if($tournament->officialResults()->exists())
      <a class="jpba-small-button" href="{{ route('public.tournaments.results', $tournament) }}">全成績</a>
    @endif
    <a class="jpba-small-button" href="{{ route('public.tournaments.show', $tournament) }}">大会ページへ戻る</a>
  </div>
</div>

<div class="jpba-live-note">
  これは大会進行中の速報値です。訂正・再集計により順位やスコアが変わる場合があります。
  @if($lastUpdatedAt)
    <span class="d-block small text-muted">最終データ更新：{{ \Illuminate\Support\Carbon::parse($lastUpdatedAt)->format('Y年n月j日 H:i') }}</span>
  @endif
</div>

<section class="jpba-panel" aria-labelledby="live-filter-heading">
  <h2 id="live-filter-heading" class="jpba-section-title">表示を切り替える</h2>
  <form method="GET" action="{{ route('public.tournaments.live', $tournament) }}" class="jpba-live-form">
    <div>
      <label for="stage">ステージ</label>
      <select id="stage" name="stage">
        @foreach($stageOptions as $option)
          <option value="{{ $option->stage }}" @selected($stage === (string)$option->stage)>
            {{ $option->stage }}（最大{{ (int)$option->max_game }}G）
          </option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="upto_game">ゲームまで</label>
      <select id="upto_game" name="upto_game">
        @for($game = 1; $game <= $maxGame; $game++)
          <option value="{{ $game }}" @selected($uptoGame === $game)>{{ $game }}G</option>
        @endfor
      </select>
    </div>
    <div>
      <label for="keyword">選手名・ライセンスNo.</label>
      <input id="keyword" name="keyword" value="{{ $keyword }}" placeholder="選手を検索">
    </div>
    <button class="btn btn-primary">表示</button>
    <a class="btn btn-outline-secondary" href="{{ route('public.tournaments.live', $tournament) }}">最新へ</a>
  </form>
</section>

<section class="jpba-panel" aria-labelledby="live-table-heading">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 id="live-table-heading" class="jpba-section-title mb-0">
      {{ $stage }} {{ $uptoGame }}G終了時点
      @if($carryGameCount > 0)
        （通算{{ $carryGameCount + $uptoGame }}G）
      @endif
    </h2>
    <div class="text-muted">{{ number_format($rankings->total()) }}名</div>
  </div>

  @if($carryGameCount > 0)
    <div class="alert alert-light border py-2 mb-3">
      トータルピン・AVG・順位には、{{ $carryStageLabel !== '' ? $carryStageLabel : '前ステージ' }}の
      {{ number_format($carryGameCount) }}Gを持ち込んでいます。
      @if($isRoundRobinPointRanking)
        ラウンドロビン順位は、通算トータルピンに勝敗ボーナスを加えたトータルポイント順です。
      @endif
    </div>
  @endif

  @if($rankings->count())
    <div class="jpba-live-table-wrap">
      <table class="jpba-live-table">
        <thead>
          <tr>
            <th>順位</th>
            <th class="player">選手</th>
            <th>ライセンスNo.</th>
            @if($carryGameCount > 0)
              <th>持込<br><span class="small">{{ number_format($carryGameCount) }}G</span></th>
            @endif
            @foreach($gameNumbers as $gameNumber)
              <th>{{ $gameNumber }}G</th>
            @endforeach
            @if($isRoundRobinPointRanking)
              <th>RR計</th>
            @endif
            <th>ゲーム数</th>
            <th>トータルピン</th>
            <th>AVG</th>
            @if($isRoundRobinPointRanking)
              <th>勝-敗-分</th>
              <th>ボーナス</th>
              <th>トータルP</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($rankings as $row)
            @php
              $rawIds = (array)($row['raw_ids'] ?? []);
              $entry = $row['entry'] ?? null;
              $playerName = (string)($rawIds['name'] ?? ($entry['name'] ?? '-'));
              $scores = (array)($row['breakdown'][$stage] ?? []);
              $gamesCounted = (int)($row['games_counted'] ?? 0);
              $average = $gamesCounted > 0 ? ((int)$row['total'] / $gamesCounted) : null;
              $rawLicense = (string)($rawIds['license_number'] ?? '');
              $licenseDisplay = str_starts_with(strtoupper($rawLicense), 'AMATEUR-')
                  ? 'アマ'
                  : ($rawLicense !== '' ? $rawLicense : ($row['display_license'] ?? '-'));
            @endphp
            <tr>
              <td>{{ number_format((int)$row['rank']) }}</td>
              <td class="player">
                <div class="jpba-live-player">
                  @if($entry && !empty($entry['photo_url']))
                    <img src="{{ $entry['photo_url'] }}" alt="">
                  @endif
                  <div>
                    @if($entry)
                      <a href="{{ route('scores.entry_balls.show', ['entry' => $entry['id'], 'public' => 1, 'return' => request()->fullUrl()]) }}">{{ $playerName }}</a>
                      <div class="small text-muted">大会登録ボール {{ number_format((int)$entry['ball_count']) }}個</div>
                    @elseif(!empty($rawIds['pro_bowler_id']))
                      <a href="{{ route('public.players.show', $rawIds['pro_bowler_id']) }}">{{ $playerName }}</a>
                    @else
                      {{ $playerName }}
                    @endif
                  </div>
                </div>
              </td>
              <td>{{ $licenseDisplay }}</td>
              @if($carryGameCount > 0)
                <td>{{ number_format((int)($row['carry_pin'] ?? 0)) }}</td>
              @endif
              @foreach($gameNumbers as $gameNumber)
                <td>{{ isset($scores[$gameNumber]) ? number_format((int)$scores[$gameNumber]) : '-' }}</td>
              @endforeach
              @if($isRoundRobinPointRanking)
                <td>{{ number_format((int)data_get($row, 'round_robin.stage_pin', 0)) }}</td>
              @endif
              <td>{{ number_format($gamesCounted) }}</td>
              <td><strong>{{ number_format((int)$row['total']) }}</strong></td>
              <td>{{ $average !== null ? number_format($average, 2) : '-' }}</td>
              @if($isRoundRobinPointRanking)
                @php $totalPoint = (int)data_get($row, 'round_robin.over_under_points', 0); @endphp
                <td>{{ data_get($row, 'round_robin.record', '-') }}</td>
                <td>{{ number_format((int)data_get($row, 'round_robin.bonus_points', 0)) }}</td>
                <td><strong>{{ $totalPoint > 0 ? '+' : '' }}{{ number_format($totalPoint) }}</strong></td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-3">{{ $rankings->links() }}</div>
  @else
    <p class="mb-0 text-muted">条件に該当する選手はいません。</p>
  @endif
</section>
@endsection
