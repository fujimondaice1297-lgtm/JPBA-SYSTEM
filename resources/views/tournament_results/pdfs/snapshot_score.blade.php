<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tournament->name }} {{ $snapshot->result_name }}</title>
    <style>
        @page {
            margin: {{ $orientation === 'landscape' ? '6mm 6mm 7mm 6mm' : '8mm 7mm 8mm 7mm' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: ipaexg, sans-serif;
            color: #111;
            font-size: {{ $orientation === 'landscape' ? '7.2px' : '7.8px' }};
            line-height: 1.18;
        }

        .pdf-title {
            text-align: center;
            font-weight: normal;
            letter-spacing: 0.10em;
            font-size: {{ $orientation === 'landscape' ? '18px' : '20px' }};
            margin: 0 0 3px;
        }

        .pdf-subtitle {
            text-align: center;
            font-weight: normal;
            font-size: {{ $orientation === 'landscape' ? '12px' : '13px' }};
            margin: 0 0 3px;
        }

        .pdf-meta {
            text-align: right;
            font-size: 8px;
            font-weight: normal;
            margin-bottom: 4px;
        }

        table.score-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .score-table th,
        .score-table td {
            border: 0.7px solid #111;
            padding: 1.5px 2px;
            text-align: center;
            vertical-align: middle;
            word-break: keep-all;
            overflow-wrap: anywhere;
        }

        .score-table th {
            font-weight: normal;
            background: #f5f5f5;
        }

        .score-table thead tr:first-child th {
            border-top-width: 1.2px;
        }

        .score-table tr td:first-child,
        .score-table tr th:first-child {
            border-left-width: 1.2px;
        }

        .score-table tr td:last-child,
        .score-table tr th:last-child {
            border-right-width: 1.2px;
        }

        .score-table tbody tr:last-child td {
            border-bottom-width: 1.2px;
        }

        .rank-col { width: {{ $orientation === 'landscape' ? '3.6%' : '4.2%' }}; }
        .license-col { width: {{ $orientation === 'landscape' ? '4.8%' : '6.0%' }}; }
        .name-col { width: {{ $orientation === 'landscape' ? '8.0%' : '10.0%' }}; }
        .period-col { width: {{ $orientation === 'landscape' ? '3.0%' : '3.7%' }}; }
        .throw-col { width: {{ $orientation === 'landscape' ? '2.8%' : '3.2%' }}; }
        .affiliation-col { width: {{ $orientation === 'landscape' ? '12.8%' : '15.0%' }}; }
        .point-col { width: {{ $orientation === 'landscape' ? '4.0%' : '4.6%' }}; background: #fff8bf; }
        .game-col { width: {{ $orientation === 'landscape' ? '3.0%' : '3.5%' }}; }
        .sum-col { width: {{ $orientation === 'landscape' ? '4.6%' : '5.2%' }}; background: #fff8bf; }
        .avg-col { width: {{ $orientation === 'landscape' ? '4.0%' : '4.6%' }}; }

        .text-left { text-align: left !important; }
        .fw-bold { font-weight: normal; }
        .highlight { background: #fff8bf; }
        .small-note { margin-top: 6px; font-size: 7.5px; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $formatPin = fn ($value) => $value === null ? '-' : number_format((int) $value);
    $formatAvg = fn ($value) => $value === null ? '-' : number_format((float) $value, 2);
    $scoreAt = fn ($row, int $game) => $scoreMatrix[$row->id][$game] ?? null;
    $blockTotal = function ($row, array $games) use ($scoreAt) {
        $total = 0;
        $played = 0;
        foreach ($games as $game) {
            $score = $scoreAt($row, (int) $game);
            if ($score !== null) {
                $total += (int) $score;
                $played++;
            }
        }

        return $played > 0 ? $total : null;
    };
    $blockAvg = function ($row, array $games) use ($scoreAt, $blockTotal) {
        $total = $blockTotal($row, $games);
        if ($total === null) {
            return null;
        }

        $played = 0;
        foreach ($games as $game) {
            if ($scoreAt($row, (int) $game) !== null) {
                $played++;
            }
        }

        return $played > 0 ? $total / $played : null;
    };
@endphp

<h1 class="pdf-title">{{ $tournament->name }}</h1>
<h2 class="pdf-subtitle">
    @if($isPreliminary)
        {{ $snapshot->result_name }} ／ {{ $tournament->venue_name ?? $tournament->venue ?? '' }}
    @else
        {{ $snapshot->result_name }} ／ {{ $stageName }}{{ $stageGames > 0 ? $stageGames . 'G' : '' }}・通算{{ $totalGames }}G成績
    @endif
</h2>
<div class="pdf-meta">
    @if(!empty($tournament->start_date))
        {{ \Carbon\Carbon::parse($tournament->start_date)->format('Y. n. j') }}
        @if(!empty($tournament->end_date) && $tournament->end_date !== $tournament->start_date)
            〜 {{ \Carbon\Carbon::parse($tournament->end_date)->format('n. j') }}
        @endif
    @endif
    @if(!empty($tournament->venue_name))
        ／ 会場: {{ $tournament->venue_name }}
    @endif
</div>

@if($isPreliminary)
    <table class="score-table">
        <thead>
            <tr>
                <th rowspan="2" class="rank-col">順位</th>
                <th rowspan="2" class="license-col">ﾗｲｾﾝｽ<br>No.</th>
                <th rowspan="2" class="name-col">氏 名</th>
                <th rowspan="2" class="period-col">期</th>
                <th rowspan="2" class="throw-col">投</th>
                <th rowspan="2" class="affiliation-col">所 属<br>/ 用品契約</th>
                @foreach($seriesBlocks as $block)
                    <th colspan="{{ count($block['games']) + 3 }}">{{ $block['label'] }}</th>
                @endforeach
                <th rowspan="2" class="sum-col">{{ $totalGames }}G<br>T/PIN</th>
                <th rowspan="2" class="avg-col">AVG</th>
            </tr>
            <tr>
                @foreach($seriesBlocks as $block)
                    @foreach($block['games'] as $game)
                        <th class="game-col">{{ $game }}G</th>
                    @endforeach
                    <th class="sum-col">T/PIN</th>
                    <th class="avg-col">AVG</th>
                    <th class="rank-col">順位</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php($profile = $participantProfiles[$row->id] ?? [])
                <tr>
                    <td>{{ $row->ranking }}</td>
                    <td>{{ $profile['license_display'] ?? ($row->pro_bowler_license_no ?? '-') }}</td>
                    <td class="text-left fw-bold">{{ $row->display_name }}</td>
                    <td>{{ $profile['period'] ?? '' }}</td>
                    <td>{{ $profile['throw'] ?? '' }}</td>
                    <td class="text-left">{{ $profile['affiliation'] ?? '-' }}</td>
                    @foreach($seriesBlocks as $block)
                        @foreach($block['games'] as $game)
                            <td>{{ $formatPin($scoreAt($row, (int) $game)) }}</td>
                        @endforeach
                        <td class="highlight fw-bold">{{ $formatPin($blockTotal($row, $block['games'])) }}</td>
                        <td>{{ $formatAvg($blockAvg($row, $block['games'])) }}</td>
                        <td>{{ $block['rank_map'][$row->id] ?? '-' }}</td>
                    @endforeach
                    <td class="highlight fw-bold">{{ $formatPin($row->total_pin) }}</td>
                    <td>{{ $formatAvg($row->average) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <table class="score-table">
        <thead>
            <tr>
                <th rowspan="2" class="point-col">ｽﾃｯﾌﾟ<br>ﾎﾟｲﾝﾄ</th>
                <th rowspan="2" class="rank-col">順位</th>
                <th rowspan="2" class="license-col">ﾗｲｾﾝｽ<br>No.</th>
                <th rowspan="2" class="name-col">氏 名</th>
                <th rowspan="2" class="period-col">期</th>
                <th rowspan="2" class="throw-col">投</th>
                <th rowspan="2" class="affiliation-col">所 属<br>/ 用品契約</th>
                <th rowspan="2" class="sum-col">予選{{ $carryGames }}G</th>
                <th rowspan="2" class="avg-col">AVG</th>
                <th rowspan="2" class="rank-col">順位</th>
                <th colspan="{{ $stageGames + 2 }}">{{ $stageName }}</th>
                <th rowspan="2" class="sum-col">通算{{ $totalGames }}G<br>T/PIN</th>
                <th rowspan="2" class="avg-col">AVG</th>
            </tr>
            <tr>
                @for($game = 1; $game <= $stageGames; $game++)
                    <th class="game-col">{{ $game }}G</th>
                @endfor
                <th class="sum-col">T/PIN</th>
                <th class="avg-col">AVG</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php($profile = $participantProfiles[$row->id] ?? [])
                @php($stageAverage = $stageGames > 0 ? ((int) $row->scratch_pin / max(1, $stageGames)) : null)
                <tr>
                    <td class="highlight">{{ $row->points !== null ? (int) $row->points . 'P' : '' }}</td>
                    <td>{{ $row->ranking }}</td>
                    <td>{{ $profile['license_display'] ?? ($row->pro_bowler_license_no ?? '-') }}</td>
                    <td class="text-left fw-bold">{{ $row->display_name }}</td>
                    <td>{{ $profile['period'] ?? '' }}</td>
                    <td>{{ $profile['throw'] ?? '' }}</td>
                    <td class="text-left">{{ $profile['affiliation'] ?? '-' }}</td>
                    <td class="highlight fw-bold">{{ $formatPin($row->carry_pin) }}</td>
                    <td>{{ $carryGames > 0 ? $formatAvg(((int) $row->carry_pin) / $carryGames) : '-' }}</td>
                    <td>{{ $carryRankMap[$row->id] ?? '-' }}</td>
                    @for($game = 1; $game <= $stageGames; $game++)
                        <td>{{ $formatPin($scoreAt($row, $game)) }}</td>
                    @endfor
                    <td class="highlight fw-bold">{{ $formatPin($row->scratch_pin) }}</td>
                    <td>{{ $formatAvg($stageAverage) }}</td>
                    <td class="highlight fw-bold">{{ $formatPin($row->total_pin) }}</td>
                    <td>{{ $formatAvg($row->average) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="small-note">
    ※このPDFは正式反映済みスナップショットとゲーム別スコアをもとに出力しています。
</div>
</body>
</html>
