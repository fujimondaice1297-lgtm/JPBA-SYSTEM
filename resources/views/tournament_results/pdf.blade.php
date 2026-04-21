<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>
        @php
            $jp = fn (string $s) => html_entity_decode($s, ENT_QUOTES, 'UTF-8');
            $titlePdf = $jp('&#x5927;&#x4F1A;&#x6210;&#x7E3E;&#x4E00;&#x89A7;PDF');
        @endphp
        {{ isset($tournament) ? $tournament->year . $jp('&#x5E74;') . ' ' . $tournament->name . ' ' . $titlePdf : $titlePdf }}
    </title>
    <style>
        body,
        table,
        th,
        td,
        h1,
        h2,
        h3,
        div,
        span,
        p {
            font-family: ipaexg, sans-serif;
        }

        body {
            font-size: 11px;
            font-weight: normal;
        }

        h2 {
            margin: 0 0 12px 0;
            font-size: 24px;
            font-weight: normal !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }

        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
            font-weight: normal !important;
        }

        th {
            background: #f0f0f0;
        }

        .text-left {
            text-align: left;
        }
    </style>
</head>
<body>
    @php
        $jp = fn (string $s) => html_entity_decode($s, ENT_QUOTES, 'UTF-8');

        $labelYear = $jp('&#x5E74;&#x5EA6;');
        $labelTournament = $jp('&#x5927;&#x4F1A;&#x540D;');
        $labelPlayer = $jp('&#x9078;&#x624B;&#x540D;');
        $labelLicense = $jp('&#x30E9;&#x30A4;&#x30BB;&#x30F3;&#x30B9;No');
        $labelRank = $jp('&#x9806;&#x4F4D;');
        $labelPoint = $jp('&#x30DD;&#x30A4;&#x30F3;&#x30C8;');
        $labelTotalPin = $jp('&#x30C8;&#x30FC;&#x30BF;&#x30EB;&#x30D4;&#x30F3;');
        $labelAverage = $jp('&#x30A2;&#x30D9;&#x30EC;&#x30FC;&#x30B8;');
        $labelPrize = $jp('&#x8CDE;&#x91D1;');
        $labelResults = $jp('&#x5927;&#x4F1A;&#x6210;&#x7E3E;&#x4E00;&#x89A7;');
        $labelUnknownPlayer = $jp('&#x4E0D;&#x660E;&#x306A;&#x9078;&#x624B;');
        $labelUnknownTournament = $jp('&#x4E0D;&#x660E;&#x306A;&#x5927;&#x4F1A;');
        $labelColon = $jp('&#xFF1A;');
        $labelYearSuffix = $jp('&#x5E74;');
    @endphp

    @if (isset($tournament))
        <h2>{{ $tournament->year }}{{ $labelYearSuffix }} {{ $tournament->name }}{{ $labelColon }}{{ $labelResults }}</h2>
    @else
        <h2>{{ $labelResults }}</h2>
    @endif

    <table>
        <thead>
            <tr>
                <th>{{ $labelYear }}</th>
                @if (!isset($tournament))
                    <th>{{ $labelTournament }}</th>
                @endif
                <th>{{ $labelPlayer }}</th>
                <th>{{ $labelLicense }}</th>
                <th>{{ $labelRank }}</th>
                <th>{{ $labelPoint }}</th>
                <th>{{ $labelTotalPin }}</th>
                <th>G</th>
                <th>{{ $labelAverage }}</th>
                <th>{{ $labelPrize }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results as $result)
                @php
                    $name = optional($result->player)->name_kanji
                            ?? optional($result->bowler)->name_kanji
                            ?? ($result->amateur_name ?? $labelUnknownPlayer);

                    $rawLicenseNo = $result->pro_bowler_license_no
                                    ?? optional($result->player)->license_no
                                    ?? optional($result->bowler)->license_no
                                    ?? null;

                    $licenseNo = $rawLicenseNo
                                ? substr((string) $rawLicenseNo, -4)
                                : '-';

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
                        <td class="text-left">{{ optional($result->tournament)->name ?? $labelUnknownTournament }}</td>
                    @endif

                    <td class="text-left">{{ $name }}</td>
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