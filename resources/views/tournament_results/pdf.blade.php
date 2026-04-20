<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ isset($tournament) ? $tournament->year . '年 ' . $tournament->name . ' 大会成績一覧PDF' : '大会成績一覧PDF' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        h2 {
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    @if (isset($tournament))
        <h2>{{ $tournament->year }}年 {{ $tournament->name }}：大会成績一覧</h2>
    @else
        <h2>大会成績一覧</h2>
    @endif

    <table>
        <thead>
            <tr>
                <th>年度</th>
                @if (!isset($tournament))
                    <th>大会名</th>
                @endif
                <th>選手名</th>
                <th>ライセンスNo</th>
                <th>順位</th>
                <th>ポイント</th>
                <th>トータルピン</th>
                <th>G</th>
                <th>アベレージ</th>
                <th>賞金</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results as $result)
                @php
                    $name = optional($result->player)->name_kanji
                            ?? optional($result->bowler)->name_kanji
                            ?? ($result->amateur_name ?? '不明な選手');

                    $licenseNo = $result->pro_bowler_license_no
                                ?? optional($result->bowler)->license_no
                                ?? '-';

                    $rank = $result->ranking
                            ?? $result->rank
                            ?? $result->position
                            ?? $result->placing
                            ?? $result->result_rank
                            ?? $result->order_no
                            ?? '-';
                @endphp
                <tr>
                    <td>{{ $result->ranking_year ?? '-' }}</td>
                    @if (!isset($tournament))
                        <td>{{ optional($result->tournament)->name ?? '不明な大会' }}</td>
                    @endif
                    <td>{{ $name }}</td>
                    <td>{{ $licenseNo }}</td>
                    <td>{{ $rank }}</td>
                    <td>{{ number_format($result->points ?? 0) }}</td>
                    <td>{{ number_format($result->total_pin ?? 0) }}</td>
                    <td>{{ $result->games ?? '-' }}</td>
                    <td>{{ isset($result->average) ? number_format($result->average, 2) : '-' }}</td>
                    <td>{{ isset($result->prize_money) ? '¥' . number_format($result->prize_money) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
