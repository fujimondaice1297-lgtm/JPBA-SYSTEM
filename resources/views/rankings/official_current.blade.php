@extends('public.layout')

@section('title', $ranking['year'].'年 '.$ranking['gender_label'].$ranking['ranking_type_label'].'｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', '当年度公式ランキング')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="jpba-page-title mb-1">{{ $ranking['year'] }}年 {{ $ranking['gender_label'] }}{{ $ranking['ranking_type_label'] }}</h1>
            <div class="text-muted">公開済み大会の現在版成績から、公式ポイント・賞金・AVGを自動集計します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('public.tournaments.live_results') }}" class="btn btn-outline-secondary">速報・成績へ戻る</a>
            @if($gender === 'M')
                <a href="{{ route('rankings.season_trial', ['year' => $selectedYear]) }}" class="btn btn-outline-primary">ST年間ポイント</a>
            @else
                <a href="{{ route('rankings.women_tournament_priority', ['year' => $selectedYear]) }}" class="btn btn-outline-primary">女子出場優先順位</a>
            @endif
            <a href="{{ route('rankings.point_distribution') }}" class="btn btn-outline-primary">ポイント配分表</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('rankings.official_current') }}" class="row g-3 align-items-end">
                <div class="col-sm-4 col-md-3">
                    <label class="form-label fw-bold" for="official-ranking-year">年度</label>
                    <select id="official-ranking-year" name="year" class="form-select">
                        @foreach($years as $year)
                            <option value="{{ $year }}" @selected((int)$selectedYear === (int)$year)>{{ $year }}年</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label fw-bold" for="official-ranking-gender">性別</label>
                    <select id="official-ranking-gender" name="gender" class="form-select">
                        @foreach($genderLabels as $value => $label)
                            <option value="{{ $value }}" @selected($gender === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label fw-bold" for="official-ranking-type">ランキング</label>
                    <select id="official-ranking-type" name="type" class="form-select">
                        @foreach($rankingTypeLabels as $value => $label)
                            <option value="{{ $value }}" @selected($rankingType === $value)>{{ $label }}</option>
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
                <div class="text-muted small">集計時点</div>
                <div class="fs-5 fw-bold">{{ $ranking['as_of_date'] ?: '成績公開前' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">集計済み大会</div>
                <div class="fs-5 fw-bold">{{ number_format((int)$ranking['published_tournament_count']) }}大会</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">ランキング対象</div>
                <div class="fs-5 fw-bold">{{ number_format((int)$ranking['row_count']) }}名</div>
            </div></div>
        </div>
    </div>

    <div class="alert alert-info">
        大会成績の現在版を確定・再公開すると自動更新されます。対象外に設定された大会のポイント、賞金、タイトルは加算されません。
    </div>

    <div class="card">
        <div class="card-header fw-bold">{{ $ranking['year'] }}年 {{ $ranking['gender_label'] }}{{ $ranking['ranking_type_label'] }}</div>
        <div class="card-body p-0">
            @if(empty($ranking['rows']))
                <div class="p-5 text-center text-muted">公開済みの対象成績はまだありません。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th class="text-end" style="width:70px;">順位</th>
                                <th>選手</th>
                                <th class="text-end">大会数</th>
                                <th class="text-end">G数</th>
                                <th class="text-end">トータルピン</th>
                                <th class="text-end">AVG</th>
                                <th class="text-end">ポイント</th>
                                <th class="text-end">獲得賞金</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ranking['rows'] as $row)
                                <tr>
                                    <td class="text-end fw-bold">{{ $row['rank'] }}</td>
                                    <td>
                                        <a href="{{ route('public.players.show', $row['pro_bowler_id']) }}" class="fw-bold">
                                            {{ $row['name_kanji'] ?: \App\Support\PublicLicenseNumber::format($row['license_no']) }}
                                        </a>
                                        <div class="small text-muted">
                                            {{ \App\Support\PublicLicenseNumber::format($row['license_no']) }}
                                            @if($row['kibetsu']) ・第{{ $row['kibetsu'] }}期 @endif
                                            @if($row['organization_name']) ・{{ $row['organization_name'] }} @endif
                                        </div>
                                    </td>
                                    <td class="text-end">{{ number_format((int)$row['tournament_count']) }}</td>
                                    <td class="text-end">{{ number_format((int)$row['games']) }}</td>
                                    <td class="text-end">{{ number_format((int)$row['total_pin']) }}</td>
                                    <td class="text-end">{{ number_format((float)$row['average'], 2) }}</td>
                                    <td class="text-end {{ $rankingType === 'points' ? 'fw-bold' : '' }}">{{ number_format((int)$row['points']) }}</td>
                                    <td class="text-end {{ $rankingType === 'prize' ? 'fw-bold' : '' }}">￥{{ number_format((int)$row['prize_money']) }}</td>
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
