{{-- resources/views/tournament_round_lane_assignments/pdf.blade.php --}}
@php
    $normalizeBoxLabel = function (?string $label): string {
        $label = trim((string) $label);
        $label = str_replace(['・', '･'], '･', $label);
        return $label;
    };

    $isTvBox = function (?string $label) use ($tvLaneLabel, $normalizeBoxLabel): bool {
        $label = $normalizeBoxLabel($label);
        $tv = $normalizeBoxLabel($tvLaneLabel ?? '');
        return $tv !== '' && $label === $tv;
    };

    $compactName = function (?string $name): string {
        $name = trim((string) $name);
        return preg_replace('/\s+/u', '', $name) ?: '';
    };

    $fmtPin = fn($v) => $v === null ? '' : number_format((int) $v);
    $fmtAvg = fn($v) => $v === null ? '' : number_format((float) $v, 2);
@endphp
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<style>
@page {
    margin: 22px 20px 20px;
}
* {
    font-family: ipaexg, sans-serif !important;
    font-weight: normal !important;
}
body {
    font-family: ipaexg, sans-serif;
    color: #111;
    font-size: 9px;
    line-height: 1.15;
}
.title {
    text-align: center;
    font-size: 18px;
    letter-spacing: 0.08em;
    margin-bottom: 1px;
}
.subtitle {
    text-align: center;
    font-size: 13px;
    margin-bottom: 3px;
}
.meta {
    text-align: right;
    font-size: 8px;
    margin-bottom: 4px;
}
.time-line {
    text-align: right;
    font-size: 8px;
    margin-bottom: 3px;
}
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
td {
    border: 1px solid #111;
    padding: 2px 2px;
    vertical-align: middle;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
}
.header-cell {
    background: #fff;
    font-weight: normal !important;
    text-align: center;
}
strong, b {
    font-weight: normal !important;
}
.main-table {
    border: 2px solid #111;
}
.main-table td {
    height: 22px;
}
.th-red {
    background: #ef6f7b;
    color: #fff;
}
.start-lane { width: 6.5%; }
.seed-rank { width: 5.5%; }
.license { width: 6.5%; }
.name { width: 12.2%; }
.period { width: 3.8%; }
.arm { width: 3.8%; }
.affiliation { width: 18.0%; text-align: left; font-size: 6.9px; }
.pin { width: 6.5%; }
.avg { width: 5.5%; }
.game-cell { width: 8.0%; font-size: 9px; }
.name-cell {
    text-align: left;
    font-size: 8.3px;
}
.affiliation-cell {
    text-align: left;
    font-size: 6.7px;
}
.pin-cell {
    font-size: 9px;
}
.avg-cell {
    font-size: 8px;
}
.tv-cell {
    color: #d00;
}
.note {
    margin-top: 5px;
    font-size: 8px;
}
</style>
</head>
<body>
    <div class="title">{{ $tournament->name }}</div>
    <div class="subtitle">
        {{ $roundLabel }} 移動表
        ※１Ｇごとに{{ ($assignments->first()->movement_direction ?? 'left') === 'right' ? '右隣' : '左隣' }}のＢＯＸに移動
        @if($tvLaneLabel)
            <span style="color:#d00;">※ＴＶ収録レーン{{ str_replace(['L', '･'], ['', '・'], $tvLaneLabel) }}Ｌ</span>
        @endif
    </div>
    <div class="meta">
        {{ $tournament->start_date ? \Carbon\Carbon::parse($tournament->start_date)->format('Y.n.j') : '' }}
        @if($tournament->end_date && $tournament->end_date != $tournament->start_date)
            ～ {{ \Carbon\Carbon::parse($tournament->end_date)->format('n.j') }}
        @endif
        ／ 会場：{{ $tournament->venue_name ?: '—' }}
    </div>

    <div class="time-line">
        ゲーム進行予定時間　
        @foreach($gameHeaders as $header)
            {{ $header['time'] ?: '—' }}@if(!$loop->last)　@endif
        @endforeach
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <td class="header-cell start-lane" rowspan="2">スタート<br>レーン</td>
                <td class="header-cell seed-rank" rowspan="2">予選<br>順位</td>
                <td class="header-cell license" rowspan="2">ライセンス<br>No.</td>
                <td class="header-cell name" rowspan="2">氏　名</td>
                <td class="header-cell period" rowspan="2">期</td>
                <td class="header-cell arm" rowspan="2">投</td>
                <td class="header-cell affiliation" rowspan="2">所　属<br><span style="font-size:6px;">/ 用品契約</span></td>
                <td class="header-cell pin" rowspan="2">予選{{ $assignments->first()->source_games ?: '' }}G<br>T/PIN</td>
                <td class="header-cell avg" rowspan="2">AVG</td>
                @foreach($gameHeaders as $header)
                    <td class="header-cell game-cell th-red">{{ $header['round_game_no'] }}Ｇ目</td>
                @endforeach
            </tr>
            <tr>
                @foreach($gameHeaders as $header)
                    <td class="header-cell game-cell th-red">通算{{ $header['game_no'] }}G目</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($assignments as $row)
                <tr>
                    <td>{{ $row->start_lane_label ?: (($row->start_lane && $row->lane_slot) ? ($row->start_lane . 'L-' . $row->lane_slot) : '') }}</td>
                    <td>{{ $row->seed_rank ? $row->seed_rank . '位' : '' }}</td>
                    <td>{{ $row->display_license_no }}</td>
                    <td class="name-cell">{{ $compactName($row->display_name) }}</td>
                    <td>{{ $row->period_label }}</td>
                    <td>{{ $row->dominant_arm }}</td>
                    <td class="affiliation-cell">{{ $row->affiliation_display }}</td>
                    <td class="pin-cell">{{ $fmtPin($row->source_total_pin) }}</td>
                    <td class="avg-cell">{{ $fmtAvg($row->source_average) }}</td>

                    @foreach($gameHeaders as $i => $header)
                        @php
                            $box = $row->movement_boxes_array[$i] ?? '';
                        @endphp
                        <td class="game-cell {{ $isTvBox($box) ? 'tv-cell' : '' }}">
                            {{ $box }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
