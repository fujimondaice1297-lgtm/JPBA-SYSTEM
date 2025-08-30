<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>大会成績一覧PDF</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>大会成績一覧</h2>
    <table>
        <thead>
            <tr>
                <th>年度</th>
                <th>大会名</th>
                <th>選手名</th>
                <th>順位</th>
                <th>ポイント</th>
                <th>トータルピン</th>
                <th>アベレージ</th>
                <th>賞金</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results as $result)
                <tr>
                    <td>{{ $result->ranking_year }}</td>
                    <td>{{ $result->tournament->name ?? '不明な大会' }}</td>
                    <td>{{ optional($result->player)->name_kanji ?? '不明な選手' }}</td>
                    <td>{{ $result->ranking }}</td>
                    <td>{{ $result->points }}</td>
                    <td>{{ $result->total_pin }}</td>
                    <td>{{ $result->average }}</td>
                    <td>¥{{ number_format($result->prize_money) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
