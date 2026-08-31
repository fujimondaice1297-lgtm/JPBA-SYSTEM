@extends('public.layout')

@section('title', 'STチャンピオンズ優先出場一覧｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'STチャンピオンズ優先出場一覧')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="jpba-page-title mb-1">STチャンピオンズ優先出場一覧</h1>
            <div class="text-muted">年間チャンピオン決定戦の選考順に、現時点の対象者を自動表示します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-secondary">速報・成績へ戻る</a>
            <a href="{{ route('rankings.season_trial', ['year' => $selectedYear]) }}" class="btn btn-primary">ST年間ポイント</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('rankings.season_trial_championship_priority') }}" class="row g-3 align-items-end">
                <div class="col-sm-4 col-md-3">
                    <label class="form-label fw-bold" for="priority-year">年度</label>
                    <select id="priority-year" name="year" class="form-select">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((int) $selectedYear === (int) $year)>{{ $year }}年度</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary">表示</button>
                </div>
                <div class="col-md text-md-end text-muted small">
                    成績・シード・タイトル・推薦の現在データから自動再計算します。
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-warning">
        <div class="fw-bold mb-1">大会途中の一覧は暫定です</div>
        各シーズントライアルの成績公開、当該年度の優勝者、推薦選手の登録に応じて選考順位が変わります。
        ⑥の同ポイント順位はSTトータルピンで決定します。
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">選考順</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">① 当該年度トーナメントシードプロ</div>
                <div class="col-md-6">② 当該年度公認トーナメント優勝者シードプロ</div>
                <div class="col-md-6">③ 永久シードプロ</div>
                <div class="col-md-6">④ ST各会場優勝者</div>
                <div class="col-md-6">⑤ スポンサー推薦</div>
                <div class="col-md-6">⑥ ST年間ポイントランキング上位者</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">選考定員</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $priority['capacity']) }}名</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">現時点の表示数</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $priority['selected_count']) }}名</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">ランキング枠</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $priority['ranking_slot_count']) }}名</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">ST集計済み会場</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $priority['published_tournament_count']) }}会場</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">{{ $selectedYear }}年度 優先出場一覧</div>
        <div class="card-body p-0">
            @if (empty($priority['rows']))
                <div class="p-5 text-center text-muted">選考対象者はまだ登録されていません。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th class="text-end" style="width: 70px;">優先順</th>
                                <th style="width: 290px;">選考区分</th>
                                <th>選手</th>
                                <th class="text-end">ST順位</th>
                                <th class="text-end">STポイント</th>
                                <th class="text-end">STトータルピン</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($priority['rows'] as $row)
                                <tr>
                                    <td class="text-end fw-bold">{{ $row['priority_rank'] }}</td>
                                    <td>
                                        <span class="badge {{ $row['status'] === 'ranking_slot' ? 'bg-primary' : 'bg-dark' }} me-1">{{ $row['category_code'] }}</span>
                                        {{ $row['category_label'] }}
                                    </td>
                                    <td>
                                        @if ($row['pro_bowler_id'])
                                            <a href="{{ route('public.players.show', $row['pro_bowler_id']) }}" class="fw-bold">{{ $row['name_kanji'] }}</a>
                                        @else
                                            <span class="fw-bold">{{ $row['name_kanji'] }}</span>
                                        @endif
                                        <div class="small text-muted">
                                            {{ $row['license_no'] ?: '-' }}
                                            @if ($row['kibetsu']) ・第{{ $row['kibetsu'] }}期 @endif
                                            @if ($row['organization_name']) ・{{ $row['organization_name'] }} @endif
                                        </div>
                                    </td>
                                    <td class="text-end">{{ $row['st_ranking_rank'] ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((int) $row['st_points']) }}</td>
                                    <td class="text-end">{{ number_format((int) $row['st_total_pin']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
