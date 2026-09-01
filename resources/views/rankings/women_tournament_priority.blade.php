@extends('public.layout')

@section('title', $priority['title'].'｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '女子トーナメント出場優先順位')

@push('styles')
<style>
  .jpba-priority-summary {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    color: var(--jpba-blue);
    font-weight: 700;
  }
  .jpba-priority-summary::-webkit-details-marker { display: none; }
  .jpba-priority-summary::after { content: '＋'; font-size: 1.25rem; }
  details[open] > .jpba-priority-summary::after { content: '−'; }
  .jpba-priority-rank { width: 72px; }
</style>
@endpush

@section('content')
<div>
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
      <h1 class="jpba-page-title mb-1">{{ $priority['title'] }}</h1>
      <div class="text-muted">女子公式トーナメントの出場優先順位を、シード区分と順位決定戦の成績に分けて表示します。</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-secondary">速報・成績へ戻る</a>
      <a href="{{ route('rankings.official_current', ['year' => $selectedYear, 'gender' => 'F', 'type' => 'points']) }}" class="btn btn-outline-primary">女子ポイントランキング</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('rankings.women_tournament_priority') }}" class="row g-3 align-items-end">
        <div class="col-sm-4 col-md-3">
          <label class="form-label fw-bold" for="women-priority-year">年度</label>
          <select id="women-priority-year" name="year" class="form-select">
            @foreach($years as $year)
              <option value="{{ $year }}" @selected((int)$selectedYear === (int)$year)>{{ $year }}年度</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-4 col-md-3">
          <label class="form-label fw-bold" for="women-priority-period">対象期間</label>
          <select id="women-priority-period" name="period" class="form-select">
            @foreach($periodLabels as $value => $label)
              <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-auto">
          <button type="submit" class="btn btn-primary">表示</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">基準日</div>
        <div class="fs-5 fw-bold">{{ $priority['as_of_date'] ?: '未公開' }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted small">出場優先順位</div>
        <div class="fs-5 fw-bold">{{ number_format((int)$priority['row_count']) }}名</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        @if($priority['period'] === 'upper')
          <div class="text-muted small">トーナメントサード</div>
          <div class="fs-5 fw-bold">{{ number_format((int)$priority['tournament_third_count']) }}名</div>
        @else
          <div class="text-muted small">順位決定戦成績</div>
          <div class="fs-5 fw-bold">{{ number_format((int)$priority['scored_qualifier_count']) }}名</div>
        @endif
      </div></div>
    </div>
  </div>

  <div class="alert alert-info">
    各区分の見出しを押すと選手一覧を開閉できます。
    @if((int)$priority['entry_only_count'] > 0)
      「エントリーのみ」{{ number_format((int)$priority['entry_only_count']) }}名も公式の優先順位に含まれます。
    @endif
  </div>

  @forelse($priority['category_groups'] as $group)
    <details class="card mb-3" @if($loop->first) open @endif>
      <summary class="jpba-priority-summary">
        <span>{{ $group['label'] }}</span>
        <span class="badge bg-primary">{{ number_format((int)$group['count']) }}名</span>
      </summary>
      <div class="table-responsive border-top">
        <table class="table table-bordered table-hover align-middle mb-0">
          <thead class="table-light text-nowrap">
            <tr>
              <th class="text-end jpba-priority-rank">優先順位</th>
              <th>選手</th>
              <th class="text-end">2025年ランキング</th>
              @if($group['key'] === 'priority_tournament')
                <th class="text-end">順位決定戦順位</th>
                <th class="text-end">G数</th>
                <th class="text-end">トータルピン</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach($group['rows'] as $row)
              <tr>
                <td class="text-end fw-bold">{{ $row['priority_rank'] }}</td>
                <td>
                  @if($row['pro_bowler_id'])
                    <a href="{{ route('public.players.show', $row['pro_bowler_id']) }}" class="fw-bold">{{ $row['display_name'] }}</a>
                  @else
                    <span class="fw-bold">{{ $row['display_name'] }}</span>
                  @endif
                  <div class="small text-muted">
                    {{ $row['license_no'] }}
                    @if($row['kibetsu']) ・第{{ $row['kibetsu'] }}期 @endif
                    @if($row['category_value']) ・{{ $row['category_value'] }} @endif
                    @if($row['affiliation']) ・{{ $row['affiliation'] }} @endif
                  </div>
                </td>
                <td class="text-end">{{ $row['previous_ranking_rank'] ?? '—' }}</td>
                @if($group['key'] === 'priority_tournament')
                  <td class="text-end">{{ $row['qualifier_rank'] ?? 'エントリーのみ' }}</td>
                  <td class="text-end">{{ $row['games'] ? number_format((int)$row['games']) : '—' }}</td>
                  <td class="text-end">{{ $row['total_pin'] ? number_format((int)$row['total_pin']) : '—' }}</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </details>
  @empty
    <div class="card"><div class="card-body p-5 text-center text-muted">公開済みの出場優先順位はまだありません。</div></div>
  @endforelse
</div>
@endsection
