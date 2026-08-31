@extends('public.layout')

@section('title', 'シーズントライアル年間ポイントランキング｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'シーズントライアル年間ポイントランキング')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="jpba-page-title mb-1">シーズントライアル年間ポイントランキング</h1>
            <div class="text-muted">確定・再公開された各会場の成績から、年間ポイントを自動集計します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-secondary">速報・成績へ戻る</a>
            <a href="{{ route('rankings.official_current', ['year' => $selectedYear, 'gender' => 'M', 'type' => 'points']) }}" class="btn btn-outline-primary">公式ポイント・賞金</a>
            <a href="{{ route('rankings.season_trial_championship_priority', ['year' => $selectedYear]) }}" class="btn btn-primary">優先出場一覧</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('rankings.season_trial') }}" class="row g-3 align-items-end">
                <div class="col-sm-4 col-md-3">
                    <label class="form-label fw-bold" for="ranking-year">年度</label>
                    <select id="ranking-year" name="year" class="form-select">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((int) $selectedYear === (int) $year)>{{ $year }}年度</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary">表示</button>
                </div>
                <div class="col-md text-md-end text-muted small">
                    大会成績の「現在版」を更新すると、この一覧にも自動で反映されます。
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">集計時点</div>
                <div class="fs-5 fw-bold">{{ $ranking['as_of_date'] ?: '成績公開前' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">集計済み会場</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $ranking['published_tournament_count']) }}会場</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">ランキング対象</div>
                <div class="fs-5 fw-bold">{{ number_format((int) $ranking['row_count']) }}名</div>
            </div></div>
        </div>
    </div>

    <div class="alert alert-info">
        同ポイントの場合は、シーズントライアルで投球したトータルピンが多い選手を上位として表示します。
        各大会のポイントは、シーズントライアル年間ポイントと公式ポイントの両方へ加算されます。
    </div>

    <div class="card">
        <div class="card-header fw-bold">{{ $selectedYear }}年度ランキング</div>
        <div class="card-body p-0">
            @if (empty($ranking['rows']))
                <div class="p-5 text-center text-muted">公開済みのシーズントライアル成績はまだありません。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th class="text-end" style="width: 70px;">順位</th>
                                <th>選手</th>
                                @foreach ($ranking['season_labels'] as $label)
                                    <th class="text-end">{{ $label }}</th>
                                @endforeach
                                <th class="text-end">年間ポイント</th>
                                <th class="text-end">トータルピン</th>
                                <th class="text-end">G数</th>
                                <th class="text-end">AVG</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ranking['rows'] as $row)
                                <tr>
                                    <td class="text-end fw-bold">{{ $row['rank'] }}</td>
                                    <td>
                                        <a href="{{ route('public.players.show', $row['pro_bowler_id']) }}" class="fw-bold">
                                            {{ $row['name_kanji'] ?: $row['license_no'] }}
                                        </a>
                                        <div class="small text-muted">
                                            {{ $row['license_no'] }}
                                            @if ($row['kibetsu']) ・第{{ $row['kibetsu'] }}期 @endif
                                            @if ($row['organization_name']) ・{{ $row['organization_name'] }} @endif
                                        </div>
                                    </td>
                                    @foreach (array_keys($ranking['season_labels']) as $seasonKey)
                                        <td class="text-end">{{ number_format((int) ($row['season_points'][$seasonKey] ?? 0)) }}</td>
                                    @endforeach
                                    <td class="text-end fw-bold">{{ number_format((int) $row['points']) }}</td>
                                    <td class="text-end">{{ number_format((int) $row['total_pin']) }}</td>
                                    <td class="text-end">{{ number_format((int) $row['games']) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['average'], 2) }}</td>
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
