@extends('public.layout')

@section('title', 'JPBAポイント配分表｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'JPBAポイント配分表')

@section('content')
<div>
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
      <h1 class="jpba-page-title mb-1">JPBAポイント配分表</h1>
      <div class="text-muted">公認トーナメントの総合順位に応じて加算される公式ポイントです。</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-secondary">速報・成績へ戻る</a>
      <a href="{{ route('rankings.official_current') }}" class="btn btn-outline-primary">当年度公式ランキング</a>
    </div>
  </div>

  <div class="alert alert-info">
    男子は96位、女子は72位まで適用します。プロ・アマの区別にかかわらず、大会の総合順位でポイントを決定します。
  </div>

  <div class="row g-4">
    <div class="col-xl-8">
      <section class="card h-100" aria-labelledby="men-point-heading">
        <h2 id="men-point-heading" class="card-header fs-5 fw-bold">男子（96名）</h2>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle mb-0 text-end">
            <thead class="table-light">
              <tr>
                @foreach($distribution['men_columns'] as $column)
                  <th>順位</th><th>ポイント</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @for($index = 0; $index < 32; $index++)
                <tr>
                  @foreach($distribution['men_columns'] as $column)
                    @php($row = $column[$index] ?? null)
                    <td>{{ $row ? $row['rank'].'位' : '' }}</td>
                    <td class="fw-bold">{{ $row ? number_format($row['points']) : '' }}</td>
                  @endforeach
                </tr>
              @endfor
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="col-xl-4">
      <section class="card h-100" aria-labelledby="season-trial-point-heading">
        <h2 id="season-trial-point-heading" class="card-header fs-5 fw-bold">シーズントライアル（8名）</h2>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle mb-0 text-end">
            <thead class="table-light"><tr><th>順位</th><th>入賞ポイント</th><th>ステップポイント</th></tr></thead>
            <tbody>
              @foreach($distribution['season_trial'] as $row)
                <tr>
                  <td>{{ $row['rank'] }}位</td>
                  <td class="fw-bold">{{ number_format($row['points']) }}</td>
                  <td>別途加算</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <p class="mb-0 small text-muted">ステップポイントは各会場の参加人数により配分が異なり、入賞ポイントへ加算されます。</p>
        </div>
      </section>
    </div>

    <div class="col-12">
      <section class="card" aria-labelledby="women-point-heading">
        <h2 id="women-point-heading" class="card-header fs-5 fw-bold">女子（72名）</h2>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle mb-0 text-end">
            <thead class="table-light">
              <tr>
                @foreach($distribution['women_columns'] as $column)
                  <th>順位</th><th>ポイント</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @for($index = 0; $index < 36; $index++)
                <tr>
                  @foreach($distribution['women_columns'] as $column)
                    @php($row = $column[$index] ?? null)
                    <td>{{ $row ? $row['rank'].'位' : '' }}</td>
                    <td class="fw-bold">{{ $row ? number_format($row['points']) : '' }}</td>
                  @endforeach
                </tr>
              @endfor
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
