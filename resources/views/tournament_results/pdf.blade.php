<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>
        @php
            $jp = fn (string $s) => html_entity_decode($s, ENT_QUOTES, 'UTF-8');
            $titlePdf = $jp('&#x6700;&#x7D42;&#x6210;&#x7E3E;PDF');
        @endphp
        {{ isset($tournament) ? $tournament->year . $jp('&#x5E74;') . ' ' . $tournament->name . ' ' . $titlePdf : $titlePdf }}
    </title>
    <style>
        @page {
            margin: 15px 20px;
        }

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
            font-weight: normal !important;
        }

        body {
            font-size: 10px;
            color: #000;
        }

        .nowrap {
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .jpba-heavy {
            text-shadow:
                0.45px 0 0 #000,
                0 0.45px 0 #000,
                0.45px 0.45px 0 #000;
        }

        .jpba-extra-heavy {
            text-shadow:
                0.65px 0 0 #000,
                0 0.65px 0 #000,
                0.65px 0.65px 0 #000,
                -0.35px 0 0 #000,
                0 -0.35px 0 #000;
        }

        .official-result-page {
            page-break-after: auto;
        }

        .official-top-title {
            text-align: center;
            line-height: 1.16;
            margin: 15px 0 12px 0;
        }

        .official-logo-wrap {
            height: 46px;
            margin: 0 auto 3px auto;
            text-align: center;
        }

        .official-logo-image {
            display: inline-block;
            width: 55px;
            height: auto;
            max-height: 44px;
        }

        .official-logo-text {
            display: inline-block;
            font-size: 14px;
            letter-spacing: 1px;
            border: 1px solid #111;
            padding: 2px 7px;
            line-height: 1.1;
        }

        .official-title-line-1 {
            font-size: 26px;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .official-title-line-2 {
            font-size: 34px;
            letter-spacing: 5.5px;
            margin-top: 5px;
        }

        .official-title-line-3 {
            font-size: 32px;
            letter-spacing: 2px;
            margin-top: 4px;
        }

        .official-title-line-4 {
            font-size: 27px;
            letter-spacing: 1px;
            margin-top: 15px;
        }

        .official-title-line-5 {
            font-size: 31px;
            letter-spacing: 2px;
            margin-top: 5px;
        }

        .official-info {
            width: 82%;
            margin: 19px auto 16px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12.3px;
            line-height: 1.38;
        }

        .official-info th,
        .official-info td {
            border: none;
            padding: 2px 4px;
            vertical-align: top;
        }

        .official-info th {
            width: 15%;
            text-align: right;
            white-space: nowrap;
        }

        .official-info td {
            text-align: left;
        }

        .official-info .left-info {
            width: 47%;
        }

        .official-info .right-info {
            width: 53%;
        }

        .official-competition-note {
            width: 82%;
            margin: 0 auto 14px auto;
            font-size: 12.2px;
            line-height: 1.55;
        }

        .official-competition-note-row {
            margin: 0;
            padding: 0;
        }

        .official-competition-label {
            display: inline-block;
            width: 98px;
            text-align: right;
            margin-right: 18px;
        }

        .official-prize-title {
            width: 86%;
            margin: 14px auto 8px auto;
            text-align: center;
            font-size: 26px;
            letter-spacing: 10px;
        }

        .official-prize-table {
            width: 88%;
            margin: 0 auto 9px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12.4px;
        }

        .official-prize-table th,
        .official-prize-table td {
            border: 2px solid #111;
            padding: 5px 4px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.18;
            background: #fff;
        }

        .official-prize-table th {
            font-size: 11.4px;
            line-height: 1.14;
        }

        .official-prize-table .rank-col { width: 10.5%; }
        .official-prize-table .license-col { width: 10%; }
        .official-prize-table .license-cell {
            text-align: right;
            white-space: nowrap;
            padding-right: 8px;
            padding-left: 3px;
            letter-spacing: 0.2px;
        }
        .official-prize-table .name-col { width: 16%; }
        .official-prize-table .period-col { width: 5%; }
        .official-prize-table .belong-col { width: 25.5%; }
        .official-prize-table .total-point-col { width: 9.5%; }
        .official-prize-table .award-point-col { width: 7.5%; }
        .official-prize-table .step-point-col { width: 7.5%; }
        .official-prize-table .prize-col { width: 8.5%; }

        .official-prize-table .belong-cell {
            padding: 2px 3px;
            white-space: nowrap;
            overflow: hidden;
        }

        .official-prize-table .belong-text {
            display: block;
            width: 100%;
            line-height: 1.05;
            text-align: center;
            white-space: nowrap;
            letter-spacing: -0.15px;
        }

        .official-prize-table .belong-text-size-1 { font-size: 10.8px; }
        .official-prize-table .belong-text-size-2 { font-size: 9.8px; letter-spacing: -0.25px; }
        .official-prize-table .belong-text-size-3 { font-size: 8.8px; letter-spacing: -0.35px; }
        .official-prize-table .belong-text-size-4 { font-size: 7.8px; letter-spacing: -0.45px; }
        .official-prize-table .belong-text-size-5 { font-size: 6.9px; letter-spacing: -0.55px; }
        .official-prize-table .belong-text-size-6 { font-size: 6.1px; letter-spacing: -0.65px; }
        .official-prize-table .belong-text-size-7 { font-size: 5.4px; letter-spacing: -0.75px; }

        .official-record-box {
            width: 88%;
            margin: 10px auto 0 auto;
            font-size: 11.6px;
            line-height: 1.42;
        }

        .official-record-title {
            margin-bottom: 3px;
            font-size: 12px;
        }

        .official-borderless-note {
            width: 88%;
            margin: 7px auto 0 auto;
            font-size: 10.8px;
            line-height: 1.35;
        }

        .official-shootout-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-bracket-title {
            text-align: center;
            line-height: 1.06;
            margin: 34px 0 16px 0;
        }

        .official-bracket-title-line-1 {
            font-size: 29px;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .official-bracket-title-line-2 {
            font-size: 28px;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .official-bracket-title-line-3 {
            font-size: 24px;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .official-bracket-title-line-4 {
            font-size: 25px;
            letter-spacing: 1px;
            margin-top: 26px;
            text-align: left;
            width: 74%;
            margin-left: auto;
            margin-right: auto;
            border-bottom: 2px solid #111;
            padding-bottom: 4px;
        }

        .official-bracket-rule-block {
            width: 74%;
            margin: 6px auto 18px auto;
            font-size: 13.5px;
            line-height: 1.55;
        }

        .official-bracket-rule-row {
            margin: 0 0 2px 0;
        }

        .official-bracket-wrap {
            width: 100%;
            text-align: center;
            margin: 0 0 8px 0;
        }

        .official-bracket-image {
            width: 87%;
            max-width: 87%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .official-score-section {
            margin: 4px 0 0 0;
            page-break-inside: avoid;
        }

        .official-score-heading {
            margin: 4px 0 4px 56px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .official-score-logo {
            display: inline-block;
            width: 34px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border: 1px solid #222;
            border-radius: 10px;
            font-size: 8px;
            vertical-align: middle;
            margin-right: 4px;
        }

        .official-score-title {
            display: inline-block;
            font-size: 17px;
            vertical-align: middle;
        }

        .official-score-image {
            width: 86%;
            max-width: 86%;
            height: auto;
            display: block;
            margin: 0 auto 4px auto;
        }

        .official-next-score-page {
            page-break-before: always;
        }

        .official-next-score-main-title {
            margin: 8px 0 16px 0;
            text-align: center;
            line-height: 1.25;
        }

        .official-next-score-title-line-1 {
            font-size: 20px;
            letter-spacing: 1px;
            margin-top: 0;
        }

        .official-next-score-title-line-2 {
            font-size: 24px;
            letter-spacing: 3px;
            margin-top: 4px;
        }

        .official-next-score-title-line-3 {
            font-size: 18px;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .official-next-score-block {
            margin: 0 0 16px 0;
            page-break-inside: avoid;
        }

        .official-next-score-heading {
            margin: 0 0 4px 56px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .official-next-score-title {
            display: inline-block;
            font-size: 16px;
            vertical-align: middle;
        }

        .official-plain-score-page {
            page-break-before: always;
        }

        .official-plain-score-title {
            margin: 0 0 8px 0;
            text-align: center;
            font-size: 18px;
            line-height: 1.35;
        }


        .official-snapshot-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-snapshot-title {
            margin: 10px 0 2px 0;
            text-align: center;
            font-size: 20px;
            line-height: 1.05;
        }

        .official-snapshot-main {
            margin: 0;
            text-align: center;
            font-size: 20px;
            line-height: 1.08;
        }

        .official-snapshot-subtitle {
            margin: 0 0 5px 0;
            text-align: center;
            font-size: 12.5px;
            line-height: 1.2;
        }

        .official-snapshot-date {
            margin: 0 0 4px 0;
            text-align: right;
            width: 98%;
            font-size: 8.5px;
            line-height: 1.1;
        }

        .official-snapshot-table {
            width: 99%;
            margin: 0 auto 8px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.8px;
            line-height: 1.04;
        }

        .official-snapshot-table th,
        .official-snapshot-table td {
            border: 1px solid #111;
            padding: 1.2px 1.2px;
            text-align: center;
            vertical-align: middle;
            background: #fff;
        }

        .official-snapshot-table th {
            font-size: 7.4px;
            line-height: 1.0;
        }

        .official-snapshot-table .snap-rank-col { width: 3.9%; }
        .official-snapshot-table .snap-step-col { width: 4.5%; }
        .official-snapshot-table .snap-license-col { width: 5.1%; }
        .official-snapshot-table .snap-name-col { width: 8.4%; }
        .official-snapshot-table .snap-period-col { width: 2.9%; }
        .official-snapshot-table .snap-arm-col { width: 3.2%; }
        .official-snapshot-table .snap-belong-col { width: 12.5%; }
        .official-snapshot-table .snap-game-col { width: 3.75%; }
        .official-snapshot-table .snap-half-col { width: 4.5%; }
        .official-snapshot-table .snap-total-col { width: 5.1%; }
        .official-snapshot-table .snap-avg-col { width: 4.8%; }
        .official-snapshot-table .snap-wide-total-col { width: 5.5%; }

        .official-snapshot-table .snapshot-belong-cell {
            font-size: 5.6px;
            line-height: 1.0;
            overflow-wrap: anywhere;
            word-break: break-all;
            white-space: normal;
            padding-left: 1px;
            padding-right: 1px;
        }

        .official-snapshot-table .snapshot-belong-cell.long-text { font-size: 4.8px; }
        .official-snapshot-table .snapshot-belong-cell.extra-long-text { font-size: 4.1px; }

        .official-snapshot-table .qualified-cell,
        .official-snapshot-table .step-point-cell {
            background: #fff7a8;
        }

        .official-snapshot-table .finalist-ref-cell {
            background: #fff7a8;
            font-size: 7px;
            line-height: 1.35;
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table .score-red {
            color: #d00;
        }

        .official-snapshot-table tr.prelim-top-eight-border td {
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table tr.semifinal-finalist-border td {
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table tr.prelim-qualified-border td {
            border-bottom: 4px double #111 !important;
        }

        .official-snapshot-note {
            width: 96%;
            margin: 0 auto;
            font-size: 8px;
            line-height: 1.25;
        }

    </style>
</head>
<body>
    @php
        $scoreImages = isset($matchScoreSheetImages) && is_array($matchScoreSheetImages)
            ? array_values($matchScoreSheetImages)
            : [];

        $firstScoreImage = $scoreImages[0] ?? null;
        $remainingScoreImages = count($scoreImages) > 1 ? array_slice($scoreImages, 1) : [];

        $scoreHeading = function ($scoreSheetImage, int $index) {
            $label = trim((string) ($scoreSheetImage['match_label'] ?? ''));

            if ($label !== '') {
                return $label;
            }

            return $index === 0 ? '優勝決定戦' : 'シュートアウトマッチ';
        };

        $resultRows = collect($results ?? []);

        $jpbaLogoPath = public_path('images/jpba_logo.png');
        $jpbaLogoSrc = null;

        if (is_file($jpbaLogoPath) && is_readable($jpbaLogoPath)) {
            $mime = function_exists('mime_content_type') ? mime_content_type($jpbaLogoPath) : null;

            if (!is_string($mime) || $mime === '') {
                $mime = 'image/png';
            }

            $imageBinary = file_get_contents($jpbaLogoPath);

            if (is_string($imageBinary) && $imageBinary !== '') {
                $jpbaLogoSrc = 'data:' . $mime . ';base64,' . base64_encode($imageBinary);
            }
        }

        $valueOf = function ($source, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (is_object($source) && isset($source->{$key}) && trim((string) $source->{$key}) !== '') {
                    return trim((string) $source->{$key});
                }

                if (is_array($source) && isset($source[$key]) && trim((string) $source[$key]) !== '') {
                    return trim((string) $source[$key]);
                }
            }

            return $default;
        };

        $tournamentName = isset($tournament) ? trim((string) ($tournament->name ?? '')) : '';
        $venueText = '';

        if (isset($tournament)) {
            $venueText = $valueOf($tournament, [
                'venue_name',
                'venue',
                'bowling_center_name',
                'center_name',
                'place',
                'location',
            ], '');
        }

        $dateText = '';
        if (isset($tournament)) {
            $start = $tournament->start_date ?? null;
            $end = $tournament->end_date ?? null;

            if ($start && $end && (string) $start !== (string) $end) {
                $dateText = (string) $start . '～' . (string) $end;
            } elseif ($start) {
                $dateText = (string) $start;
            } elseif ($end) {
                $dateText = (string) $end;
            }
        }

        $shootoutSettings = [];
        if (isset($tournament)) {
            $rawShootoutSettings = $tournament->shootout_settings ?? [];

            if (is_array($rawShootoutSettings)) {
                $shootoutSettings = $rawShootoutSettings;
            } elseif (is_string($rawShootoutSettings) && trim($rawShootoutSettings) !== '') {
                $decodedShootoutSettings = json_decode($rawShootoutSettings, true);
                $shootoutSettings = is_array($decodedShootoutSettings) ? $decodedShootoutSettings : [];
            }
        }

        $stageProgress = $shootoutSettings['stage_progress'] ?? [];
        $stageProgress = is_array($stageProgress) ? $stageProgress : [];

        $readStageInt = function (string $key, ?int $default = null) use ($stageProgress) {
            $value = $stageProgress[$key] ?? null;

            if ($value === null || $value === '') {
                return $default;
            }

            if (!is_numeric($value)) {
                return $default;
            }

            return (int) $value;
        };

        $autoSemifinalQualifierCount = function (?int $playerCount): ?int {
            if ($playerCount === null || $playerCount <= 0) {
                return null;
            }

            $half = (int) ceil($playerCount / 2);

            return $half % 2 === 0 ? $half : $half + 1;
        };

        $prelimPlayerCount = $readStageInt('prelim_player_count');
        $prelimGameCount = $readStageInt('prelim_game_count', 8);
        $prelimQualifierCount = $readStageInt('prelim_qualifier_count', $autoSemifinalQualifierCount($prelimPlayerCount));
        $semifinalGameCount = $readStageInt('semifinal_game_count', 4);
        $semifinalTotalGameCount = $readStageInt(
            'semifinal_total_game_count',
            ($prelimGameCount ?? 8) + ($semifinalGameCount ?? 4)
        );
        $semifinalQualifierCount = $readStageInt(
            'semifinal_qualifier_count',
            isset($tournament) && isset($tournament->shootout_qualifier_count)
                ? (int) $tournament->shootout_qualifier_count
                : 8
        );

        $stageNumber = function (?int $value): string {
            return $value !== null && $value > 0 ? number_format($value) : '-';
        };

        $seriesTitle = $tournamentName !== '' ? $tournamentName : 'JPBAシーズントライアル';

        $officialMainTitle = $tournamentName !== '' ? $tournamentName : 'メリーランドカップ';
        $officialSeriesTitle = 'ＪＰＢＡシーズントライアル２０２６';
        $officialSeasonTitle = 'ウィンターシリーズ';
        $officialVenueTitle = $venueText !== '' ? $venueText : '会場';

        $formatPlayerName = function ($name): string {
            $name = trim((string) $name);

            if ($name === '') {
                return '-';
            }

            $name = preg_replace('/\s+/u', '　', $name) ?: $name;

            if (str_contains($name, '　')) {
                return $name;
            }

            if (preg_match('/[A-Za-z0-9ｦ-ﾟァ-ヶー]/u', $name)) {
                return $name;
            }

            $length = mb_strlen($name);
            if ($length < 3) {
                return $name;
            }

            foreach (['野々山'] as $surname) {
                if (str_starts_with($name, $surname) && $length > mb_strlen($surname)) {
                    return $surname . '　' . mb_substr($name, mb_strlen($surname));
                }
            }

            foreach (['辻'] as $surname) {
                if (str_starts_with($name, $surname) && $length > 1) {
                    return $surname . '　' . mb_substr($name, 1);
                }
            }

            return mb_substr($name, 0, 2) . '　' . mb_substr($name, 2);
        };

        $resolveName = function ($result) use ($valueOf, $formatPlayerName) {
            $name = '';

            if (is_object($result) && method_exists($result, 'getAttribute')) {
                $name = trim((string) ($result->getAttribute('pdf_display_name') ?? ''));
            }

            if ($name === '') {
                $name = $valueOf($result, ['pdf_display_name', 'player_name', 'name', 'display_name', 'amateur_name'], '');
            }

            if ($name === '') {
                $name = optional($result->player)->name_kanji
                    ?? optional($result->bowler)->name_kanji
                    ?? '-';
            }

            return $formatPlayerName($name);
        };

        $resolveLicense = function ($result) use ($valueOf) {
            $raw = $valueOf($result, ['pro_bowler_license_no', 'license_no'], '');

            if ($raw === '') {
                $raw = optional($result->player)->license_no
                    ?? optional($result->bowler)->license_no
                    ?? '';
            }

            $license = trim((string) $raw);

            if ($license === '') {
                return '-';
            }

            $license = preg_replace('/\s+/', '', $license) ?? $license;

            return mb_substr($license, -4);
        };

        $resolveRank = function ($result) use ($valueOf) {
            $rank = $valueOf($result, [
                'ranking',
                'rank',
                'position',
                'placing',
                'result_rank',
                'order_no',
            ], '-');

            if (!is_numeric($rank)) {
                return $rank;
            }

            $rank = (int) $rank;
            return match ($rank) {
                1 => '優　勝',
                2 => '第２位',
                3 => '第３位',
                4 => '第４位',
                5 => '第５位',
                6 => '第６位',
                7 => '第７位',
                8 => '第８位',
                default => '第' . $rank . '位',
            };
        };

        $resolvePeriod = function ($result) use ($valueOf) {
            $period = $valueOf($result, ['bowler_period_label', 'period_label', 'period', 'generation'], '');

            if ($period === '') {
                $period = optional($result->player)->period_label
                    ?? optional($result->bowler)->period_label
                    ?? optional($result->player)->period
                    ?? optional($result->bowler)->period
                    ?? '';
            }

            $period = trim((string) $period);

            if ($period === '') {
                return '-';
            }

            $digits = preg_replace('/[^0-9]+/u', '', $period);

            return $digits !== '' ? $digits : $period;
        };

        $resolveBelong = function ($result) use ($valueOf) {
            $belong = $valueOf($result, [
                'affiliation_display',
                'affiliation',
                'belonging',
                'organization_name',
                'organization',
                'sponsor',
                'sponsor_name',
                'company_name',
                'club_name',
                'center_name',
                'shop_name',
            ], '');

            if ($belong === '') {
                $player = $result->player ?? $result->bowler ?? null;

                $organizationName = trim((string) (
                    optional($player)->organization_name
                    ?? optional($player)->affiliation
                    ?? optional($player)->belonging
                    ?? optional($player)->organization
                    ?? optional($player)->sponsor
                    ?? ''
                ));

                $equipmentContract = trim((string) (
                    optional($player)->equipment_contract
                    ?? optional($player)->equipment
                    ?? optional($player)->equipment_sponsor
                    ?? ''
                ));

                if ($organizationName !== '' && $equipmentContract !== '') {
                    $belong = $organizationName . '/' . $equipmentContract;
                } elseif ($organizationName !== '') {
                    $belong = $organizationName;
                } elseif ($equipmentContract !== '') {
                    $belong = $equipmentContract;
                }
            }

            return trim((string) $belong) !== '' ? trim((string) $belong) : '-';
        };



        $belongTextClass = function ($text): string {
            $text = trim((string) $text);
            $width = function_exists('mb_strwidth')
                ? mb_strwidth($text, 'UTF-8')
                : strlen($text);

            if ($width >= 62) {
                return 'belong-text belong-text-size-7';
            }

            if ($width >= 54) {
                return 'belong-text belong-text-size-6';
            }

            if ($width >= 46) {
                return 'belong-text belong-text-size-5';
            }

            if ($width >= 38) {
                return 'belong-text belong-text-size-4';
            }

            if ($width >= 30) {
                return 'belong-text belong-text-size-3';
            }

            if ($width >= 22) {
                return 'belong-text belong-text-size-2';
            }

            return 'belong-text belong-text-size-1';
        };

        $resolveNumber = function ($result, array $keys, $default = 0) use ($valueOf) {
            $value = $valueOf($result, $keys, '');

            if ($value === '') {
                return $default;
            }

            $numeric = str_replace([',', '¥', '\\'], '', (string) $value);

            return is_numeric($numeric) ? (float) $numeric : $default;
        };

        $formatNumber = function ($value, int $decimals = 0) {
            if ($value === null || $value === '') {
                return '-';
            }

            $numeric = str_replace([',', '¥', '\\'], '', (string) $value);

            if (!is_numeric($numeric)) {
                return (string) $value;
            }

            return number_format((float) $numeric, $decimals);
        };

        $formatPrize = function ($value) {
            if ($value === null || $value === '') {
                return '-';
            }

            $numeric = str_replace([',', '¥', '\\'], '', (string) $value);

            if (!is_numeric($numeric)) {
                return (string) $value;
            }

            return number_format((float) $numeric);
        };

        $pdfScoreSnapshots = [];

        if (isset($scoreSnapshots) && is_iterable($scoreSnapshots)) {
            foreach ($scoreSnapshots as $snapshotSet) {
                if (is_array($snapshotSet)) {
                    $snapshot = $snapshotSet['snapshot'] ?? null;
                    $rows = $snapshotSet['rows'] ?? [];
                } else {
                    $snapshot = $snapshotSet->snapshot ?? $snapshotSet;
                    $rows = $snapshotSet->rows ?? [];
                }

                if ($snapshot) {
                    $pdfScoreSnapshots[] = [
                        'snapshot' => $snapshot,
                        'rows' => collect($rows)->values(),
                    ];
                }
            }
        } elseif (isset($tournament) && isset($tournament->id)) {
            try {
                $snapshotHeaders = \Illuminate\Support\Facades\DB::table('tournament_result_snapshots')
                    ->where('tournament_id', $tournament->id)
                    ->where('is_current', true)
                    ->whereIn('result_code', ['prelim_total', 'semifinal_total'])
                    ->orderByRaw("case result_code when 'semifinal_total' then 1 when 'prelim_total' then 2 else 9 end")
                    ->orderBy('id')
                    ->get();

                foreach ($snapshotHeaders as $snapshot) {
                    $rows = \Illuminate\Support\Facades\DB::table('tournament_result_snapshot_rows')
                        ->where('snapshot_id', $snapshot->id)
                        ->orderBy('ranking')
                        ->orderByDesc('total_pin')
                        ->orderBy('id')
                        ->get();

                    $pdfScoreSnapshots[] = [
                        'snapshot' => $snapshot,
                        'rows' => $rows,
                    ];
                }
            } catch (\Throwable $e) {
                $pdfScoreSnapshots = [];
            }
        }

        $gameScoreMap = [];
        $gameScoreNameByLicense = [];
        $gameScoreRowsForPdf = collect();
        if (isset($tournament) && isset($tournament->id)) {
            try {
                $gameScoreRows = \Illuminate\Support\Facades\DB::table('game_scores')
                    ->where('tournament_id', $tournament->id)
                    ->whereIn('stage', ['予選', '準決勝'])
                    ->orderBy('stage')
                    ->orderBy('game_number')
                    ->get();
                $gameScoreRowsForPdf = $gameScoreRows;

                foreach ($gameScoreRows as $gameScoreRow) {
                    $stage = trim((string) ($gameScoreRow->stage ?? ''));
                    $gameNumber = (int) ($gameScoreRow->game_number ?? 0);

                    if ($stage === '' || $gameNumber <= 0) {
                        continue;
                    }

                    $licenseKey = strtoupper(trim((string) ($gameScoreRow->license_number ?? '')));
                    $rawGameScoreName = trim((string) ($gameScoreRow->name ?? ''));
                    $nameKey = preg_replace('/\s+/u', '', $rawGameScoreName);

                    if ($licenseKey !== '') {
                        $gameScoreMap[$stage]['license'][$licenseKey][$gameNumber] = (int) ($gameScoreRow->score ?? 0);

                        if ($rawGameScoreName !== '' && !isset($gameScoreNameByLicense[$licenseKey])) {
                            $gameScoreNameByLicense[$licenseKey] = $formatPlayerName($rawGameScoreName);
                        }
                    }

                    if (is_string($nameKey) && $nameKey !== '') {
                        $gameScoreMap[$stage]['name'][$nameKey][$gameNumber] = (int) ($gameScoreRow->score ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                $gameScoreMap = [];
            }
        }

        $normalizeLicenseKey = function ($license): string {
            return strtoupper(preg_replace('/\s+/u', '', trim((string) $license)) ?? trim((string) $license));
        };

        $licenseTailKey = function ($license): string {
            $digits = preg_replace('/\D+/u', '', trim((string) $license)) ?? '';

            return $digits === '' ? '' : ltrim($digits, '0');
        };

        $snapshotMetaById = [];
        foreach ($pdfScoreSnapshots as $snapshotSetForMeta) {
            $snapshotForMeta = $snapshotSetForMeta['snapshot'] ?? null;
            $snapshotIdForMeta = (int) ($snapshotForMeta->id ?? 0);
            $definitionRaw = $snapshotForMeta->calculation_definition ?? null;
            $definition = is_string($definitionRaw)
                ? (json_decode($definitionRaw, true) ?: [])
                : (is_array($definitionRaw) ? $definitionRaw : []);

            foreach (($definition['participant_meta'] ?? []) as $licenseForMeta => $metaForLicense) {
                $licenseKeyForMeta = $normalizeLicenseKey($licenseForMeta);
                $tailKeyForMeta = $licenseTailKey($licenseForMeta);

                if ($snapshotIdForMeta > 0 && $licenseKeyForMeta !== '') {
                    $snapshotMetaById[$snapshotIdForMeta][$licenseKeyForMeta] = $metaForLicense;
                }

                if ($snapshotIdForMeta > 0 && $tailKeyForMeta !== '') {
                    $snapshotMetaById[$snapshotIdForMeta]['tail:' . $tailKeyForMeta] = $metaForLicense;
                }
            }
        }

        usort($pdfScoreSnapshots, function ($a, $b) {
            $codeA = (string) (($a['snapshot']->result_code ?? '') ?: '');
            $codeB = (string) (($b['snapshot']->result_code ?? '') ?: '');
            $order = ['semifinal_total' => 1, 'prelim_total' => 2];

            return ($order[$codeA] ?? 9) <=> ($order[$codeB] ?? 9);
        });

        $proBowlerInfoById = [];
        $proBowlerInfoByLicense = [];
        $proBowlerInfoByTail = [];

        try {
            $proBowlerIds = [];
            $licenseCandidates = [];

            foreach ($resultRows as $result) {
                $id = $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null;
                if ($id) {
                    $proBowlerIds[] = (int) $id;
                }

                $licenseCandidates[] = $result->pro_bowler_license_no
                    ?? optional($result->player)->license_no
                    ?? optional($result->bowler)->license_no
                    ?? null;
            }

            foreach ($pdfScoreSnapshots as $snapshotSet) {
                foreach (($snapshotSet['rows'] ?? []) as $row) {
                    $id = $row->pro_bowler_id ?? ($row['pro_bowler_id'] ?? null);
                    if ($id) {
                        $proBowlerIds[] = (int) $id;
                    }

                    $licenseCandidates[] = $row->pro_bowler_license_no ?? ($row['pro_bowler_license_no'] ?? null) ?? null;
                }
            }

            foreach ($gameScoreRowsForPdf as $gameScoreRow) {
                if (!empty($gameScoreRow->pro_bowler_id)) {
                    $proBowlerIds[] = (int) $gameScoreRow->pro_bowler_id;
                }
                $licenseCandidates[] = $gameScoreRow->license_number ?? null;
            }

            $proBowlerIds = array_values(array_unique(array_filter($proBowlerIds)));
            $licenseCandidates = array_values(array_unique(array_filter(array_map($normalizeLicenseKey, $licenseCandidates))));

            $query = \Illuminate\Support\Facades\DB::table('pro_bowlers');

            if (count($proBowlerIds) > 0 || count($licenseCandidates) > 0) {
                $query->where(function ($q) use ($proBowlerIds, $licenseCandidates) {
                    if (count($proBowlerIds) > 0) {
                        $q->whereIn('id', $proBowlerIds);
                    }

                    if (count($licenseCandidates) > 0) {
                        $q->orWhereIn('license_no', $licenseCandidates);
                    }
                });

                foreach ($query->get() as $proBowlerInfo) {
                    $proBowlerInfoById[(int) $proBowlerInfo->id] = $proBowlerInfo;

                    $licenseKey = $normalizeLicenseKey($proBowlerInfo->license_no ?? '');
                    if ($licenseKey !== '') {
                        $proBowlerInfoByLicense[$licenseKey] = $proBowlerInfo;
                    }

                    $tailKey = $licenseTailKey($proBowlerInfo->license_no ?? '');
                    if ($tailKey !== '') {
                        $proBowlerInfoByTail[$tailKey] = $proBowlerInfo;
                    }
                }
            }
        } catch (\Throwable $e) {
            $proBowlerInfoById = [];
            $proBowlerInfoByLicense = [];
            $proBowlerInfoByTail = [];
        }

        $snapshotValue = function ($row, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (is_object($row) && isset($row->{$key}) && trim((string) $row->{$key}) !== '') {
                    return trim((string) $row->{$key});
                }

                if (is_array($row) && isset($row[$key]) && trim((string) $row[$key]) !== '') {
                    return trim((string) $row[$key]);
                }
            }

            return $default;
        };

        $snapshotLicenseRaw = function ($row) use ($snapshotValue) {
            return $snapshotValue($row, ['pro_bowler_license_no', 'license_number', 'license_no'], '');
        };

        $snapshotMeta = function ($row) use ($snapshotValue, $snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey, $snapshotMetaById) {
            $snapshotId = (int) $snapshotValue($row, ['snapshot_id'], 0);
            $license = $snapshotLicenseRaw($row);
            $licenseKey = $normalizeLicenseKey($license);
            $tailKey = $licenseTailKey($license);

            if ($snapshotId > 0 && $licenseKey !== '' && isset($snapshotMetaById[$snapshotId][$licenseKey])) {
                return $snapshotMetaById[$snapshotId][$licenseKey];
            }

            if ($snapshotId > 0 && $tailKey !== '' && isset($snapshotMetaById[$snapshotId]['tail:' . $tailKey])) {
                return $snapshotMetaById[$snapshotId]['tail:' . $tailKey];
            }

            return [];
        };

        $snapshotLicense = function ($row) use ($snapshotLicenseRaw) {
            $license = $snapshotLicenseRaw($row);

            if ($license === '') {
                return '-';
            }

            $license = preg_replace('/\s+/', '', $license) ?? $license;
            return mb_substr($license, -4);
        };

        $snapshotName = function ($row) use ($snapshotValue, $snapshotLicenseRaw, $normalizeLicenseKey, $gameScoreNameByLicense, $formatPlayerName) {
            $licenseKey = $normalizeLicenseKey($snapshotLicenseRaw($row));

            if ($licenseKey !== '' && isset($gameScoreNameByLicense[$licenseKey])) {
                return $gameScoreNameByLicense[$licenseKey];
            }

            return $formatPlayerName($snapshotValue($row, ['display_name', 'name', 'amateur_name'], '-'));
        };

        $snapshotInfo = function ($row) use ($snapshotValue, $snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey, $proBowlerInfoById, $proBowlerInfoByLicense, $proBowlerInfoByTail) {
            $id = $snapshotValue($row, ['pro_bowler_id'], '');
            if ($id !== '' && isset($proBowlerInfoById[(int) $id])) {
                return $proBowlerInfoById[(int) $id];
            }

            $license = $snapshotLicenseRaw($row);
            $licenseKey = $normalizeLicenseKey($license);
            if ($licenseKey !== '' && isset($proBowlerInfoByLicense[$licenseKey])) {
                return $proBowlerInfoByLicense[$licenseKey];
            }

            $tailKey = $licenseTailKey($license);
            if ($tailKey !== '' && isset($proBowlerInfoByTail[$tailKey])) {
                return $proBowlerInfoByTail[$tailKey];
            }

            return null;
        };

        $infoValue = function ($info, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (is_object($info) && isset($info->{$key}) && trim((string) $info->{$key}) !== '') {
                    return trim((string) $info->{$key});
                }
            }

            return $default;
        };

        $snapshotPeriod = function ($row) use ($snapshotValue, $snapshotInfo, $infoValue, $snapshotMeta) {
            $period = $snapshotValue($row, ['period', 'bowler_period_label', 'period_label', 'generation'], '');
            $meta = $snapshotMeta($row);

            if ($period === '' && is_array($meta) && !empty($meta['period'])) {
                $period = (string) $meta['period'];
            }

            if ($period === '') {
                $period = $infoValue($snapshotInfo($row), ['period', 'term', 'generation', 'professional_generation', 'professional_period'], '');
            }

            return $period !== '' ? preg_replace('/[^0-9]+/u', '', (string) $period) ?: (string) $period : '-';
        };

        $snapshotArm = function ($row) use ($snapshotValue, $snapshotInfo, $infoValue, $snapshotMeta) {
            $arm = $snapshotValue($row, ['arm', 'dominant_arm', 'dominant_hand', 'throwing_arm'], '');
            $meta = $snapshotMeta($row);

            if ($arm === '' && is_array($meta) && !empty($meta['arm'])) {
                $arm = (string) $meta['arm'];
            }

            if ($arm === '') {
                $arm = $infoValue($snapshotInfo($row), ['dominant_arm', 'dominant_hand', 'throwing_arm', 'handedness', 'arm'], '');
            }

            $arm = trim((string) $arm);

            if ($arm === '') {
                return '-';
            }

            if (str_contains($arm, 'サムレス') || str_contains($arm, '両手')) {
                return $arm;
            }

            if (str_contains($arm, '左')) {
                return '左';
            }

            if (str_contains($arm, '右')) {
                return '右';
            }

            return $arm;
        };

        $snapshotBelong = function ($row) use ($snapshotInfo, $infoValue, $snapshotMeta) {
            $meta = $snapshotMeta($row);
            if (is_array($meta) && !empty($meta['affiliation'])) {
                return (string) $meta['affiliation'];
            }

            $info = $snapshotInfo($row);
            $organization = $infoValue($info, ['organization_name', 'affiliation', 'belonging', 'organization', 'sponsor', 'company_name', 'club_name', 'center_name'], '');
            $equipment = $infoValue($info, ['equipment_contract', 'equipment', 'equipment_sponsor'], '');

            if ($organization !== '' && $equipment !== '') {
                return $organization . '/' . $equipment;
            }

            if ($organization !== '') {
                return $organization;
            }

            if ($equipment !== '') {
                return $equipment;
            }

            return '-';
        };

        $snapshotBelongClass = function (string $text): string {
            $length = mb_strlen($text);

            if ($length >= 34) {
                return 'snapshot-belong-cell extra-long-text';
            }

            if ($length >= 22) {
                return 'snapshot-belong-cell long-text';
            }

            return 'snapshot-belong-cell';
        };

        $snapshotScoreFor = function ($stage, $row, int $gameNumber) use ($snapshotValue, $snapshotName, $gameScoreMap) {
            $license = strtoupper(trim((string) $snapshotValue($row, ['pro_bowler_license_no', 'license_number', 'license_no'], '')));
            if ($license !== '' && isset($gameScoreMap[$stage]['license'][$license][$gameNumber])) {
                return $gameScoreMap[$stage]['license'][$license][$gameNumber];
            }

            $nameKey = preg_replace('/\s+/u', '', trim((string) $snapshotName($row)));
            if (is_string($nameKey) && $nameKey !== '' && isset($gameScoreMap[$stage]['name'][$nameKey][$gameNumber])) {
                return $gameScoreMap[$stage]['name'][$nameKey][$gameNumber];
            }

            return null;
        };

        $snapshotTitle = function ($snapshot) {
            $resultCode = trim((string) ($snapshot->result_code ?? ''));
            $resultName = trim((string) ($snapshot->result_name ?? ''));

            if ($resultName !== '') {
                return $resultName;
            }

            return match ($resultCode) {
                'prelim_total' => '予選８Ｇトータルピン成績',
                'semifinal_total' => '準決勝４Ｇ・通算１２Ｇトータルピン成績',
                default => '大会成績',
            };
        };


        $scoreTextClass = function ($score): string {
            if (is_numeric($score) && (int) $score >= 300) {
                return 'score-red';
            }

            return '';
        };

        $snapshotLicenseKey = function ($row) use ($snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey): string {
            $raw = $snapshotLicenseRaw($row);
            $key = $normalizeLicenseKey($raw);

            if ($key !== '') {
                return $key;
            }

            return $licenseTailKey($raw);
        };

        $prelimRankByLicense = [];
        foreach ($pdfScoreSnapshots as $snapshotSetForRank) {
            $snapshotForRank = $snapshotSetForRank['snapshot'] ?? null;
            if (($snapshotForRank->result_code ?? '') !== 'prelim_total') {
                continue;
            }

            foreach (($snapshotSetForRank['rows'] ?? []) as $prelimRankRow) {
                $key = $snapshotLicenseKey($prelimRankRow);
                $rank = $snapshotValue($prelimRankRow, ['ranking'], '');

                if ($key !== '' && is_numeric($rank)) {
                    $prelimRankByLicense[$key] = (int) $rank;
                }
            }
        }

        $snapshotPrelimRank = function ($row) use ($snapshotLicenseKey, $prelimRankByLicense) {
            $key = $snapshotLicenseKey($row);

            return $key !== '' && isset($prelimRankByLicense[$key]) ? $prelimRankByLicense[$key] : null;
        };

        $stepPointLabelForSemifinalRank = function ($rank, $points = null) use ($semifinalQualifierCount, $prelimQualifierCount) {
            if (!is_numeric($rank)) {
                return '-';
            }

            $rank = (int) $rank;
            $finalistCount = (int) ($semifinalQualifierCount ?: 8);

            if ($rank <= $finalistCount) {
                return null;
            }

            if ($points !== null && $points !== '' && is_numeric($points)) {
                return (int) $points . 'P';
            }

            // 18名準決勝なら、9位=10P、18位=1P。通過人数が変わっても最下位から1Pずつ積み上げる。
            $point = (($prelimQualifierCount ?? 0) > 0)
                ? max(1, (int) $prelimQualifierCount - $rank + 1)
                : max(1, $finalistCount + 1 + ($finalistCount + 2 - $rank));

            return $point . 'P';
        };
    @endphp

    <div class="official-result-page">
        <div class="official-top-title">
            <div class="official-logo-wrap">
                @if ($jpbaLogoSrc)
                    <img class="official-logo-image" src="{!! $jpbaLogoSrc !!}" alt="JPBA">
                @else
                    <div class="official-logo-text">JPBA</div>
                @endif
            </div>

            <div class="official-title-line-1 jpba-extra-heavy">{{ $officialMainTitle }}</div>
            <div class="official-title-line-2 jpba-extra-heavy">{{ $officialSeriesTitle }}</div>
            <div class="official-title-line-3 jpba-extra-heavy">{{ $officialSeasonTitle }}</div>
            <div class="official-title-line-4 jpba-extra-heavy">会場：{{ $officialVenueTitle }}</div>
            <div class="official-title-line-5 jpba-extra-heavy">成績表</div>
        </div>

        <table class="official-info jpba-heavy">
            <tr>
                <th>【主　　催】</th>
                <td class="left-info">（公社）日本プロボウリング協会</td>
                <th>【開催日】</th>
                <td class="right-info">{{ $dateText !== '' ? $dateText : '-' }}</td>
            </tr>
            <tr>
                <th>【公　　認】</th>
                <td class="left-info">（公社）日本プロボウリング協会</td>
                <th>【会　　場】</th>
                <td class="right-info">{{ $venueText !== '' ? $venueText : '-' }}</td>
            </tr>
            <tr>
                <th>【主管運営】</th>
                <td class="left-info">会場該当地区 及び事務局</td>
                <th>【競技内容】</th>
                <td class="right-info">決勝･･･{{ $stageNumber($semifinalQualifierCount) }}名によるシュートアウト方式</td>
            </tr>
        </table>

        <div class="official-competition-note jpba-heavy">
            <div class="official-competition-note-row">
                <span class="official-competition-label">【競技内容】</span>
                予　選･･･{{ $stageNumber($prelimPlayerCount) }}名にて{{ $stageNumber($prelimGameCount) }}Ｇ投球し上位{{ $stageNumber($prelimQualifierCount) }}名を準決勝へ選出。
            </div>
            <div class="official-competition-note-row">
                <span class="official-competition-label"></span>
                準決勝･･･{{ $stageNumber($prelimQualifierCount) }}名にて{{ $stageNumber($semifinalGameCount) }}Ｇ投球し通算{{ $stageNumber($semifinalTotalGameCount) }}Ｇ上位{{ $stageNumber($semifinalQualifierCount) }}名を決勝へ選出。
            </div>
            <div class="official-competition-note-row">
                <span class="official-competition-label"></span>
                決　勝･･･{{ $stageNumber($semifinalQualifierCount) }}名によるシュートアウト方式
            </div>
        </div>

        <div class="official-prize-title jpba-extra-heavy">〔入 賞 者 リ ス ト〕</div>

        <table class="official-prize-table jpba-heavy">
            <thead>
                <tr>
                    <th class="rank-col">順位</th>
                    <th class="license-col">ﾗｲｾﾝｽ<br>No.</th>
                    <th class="name-col">氏　名</th>
                    <th class="period-col">期</th>
                    <th class="belong-col">所　属<br>/ 用品契約</th>
                    <th class="total-point-col">獲得合計<br>ポイント</th>
                    <th class="award-point-col">入賞<br>ﾎﾟｲﾝﾄ</th>
                    <th class="step-point-col">ｽﾃｯﾌﾟ<br>ﾎﾟｲﾝﾄ</th>
                    <th class="prize-col">賞 金(¥)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resultRows->take(8) as $result)
                    @php
                        $rankLabel = $resolveRank($result);
                        $licenseNo = $resolveLicense($result);
                        $name = $resolveName($result);
                        $period = $resolvePeriod($result);
                        $belong = $resolveBelong($result);

                        $totalPoint = $resolveNumber($result, [
                            'total_points',
                            'earned_total_points',
                            'earned_points',
                            'points',
                        ], 0);

                        $awardPoint = $resolveNumber($result, [
                            'award_points',
                            'prize_points',
                            'rank_points',
                            'entry_points',
                        ], null);

                        $stepPoint = $resolveNumber($result, [
                            'step_points',
                            'step_point',
                            'shootout_points',
                            'final_points',
                        ], null);

                        $prizeMoney = $result->prize_money ?? null;
                    @endphp
                    <tr>
                        <td class="nowrap">{{ $rankLabel }}</td>
                        <td class="license-cell">{{ $licenseNo }}</td>
                        <td class="text-left">{{ $name }}</td>
                        <td class="nowrap">{{ $period }}</td>
                        <td class="text-left belong-cell"><span class="{{ $belongTextClass($belong) }}">{{ $belong }}</span></td>
                        <td class="nowrap">{{ $formatNumber($totalPoint) }}</td>
                        <td class="nowrap">{{ $awardPoint === null ? '-' : $formatNumber($awardPoint) }}</td>
                        <td class="nowrap">{{ $stepPoint === null ? '-' : $formatNumber($stepPoint) }}</td>
                        <td class="text-right nowrap">{{ $formatPrize($prizeMoney) }}</td>
                    </tr>
                @endforeach

                <tr>
                    <td class="nowrap">以上</td>
                    <td colspan="8" class="text-left">決勝（シュートアウト方式）　※賞金獲得者</td>
                </tr>
            </tbody>
        </table>

        <div class="official-record-box jpba-heavy">
            <div class="official-record-title">☆パーフェクトゲーム達成者</div>
            <div>該当データがある場合はここに表示します。</div>
        </div>

        <div class="official-borderless-note jpba-heavy">
            ※このPDFは登録済み大会成績データをもとに出力しています。決勝トーナメント表およびスコア表は次ページ以降に表示します。
        </div>
    </div>

    @if (isset($shootoutBracketImage) && is_string($shootoutBracketImage) && $shootoutBracketImage !== '')
        <div class="official-shootout-page">
            <div class="official-bracket-title">
                <div class="official-bracket-title-line-1 jpba-extra-heavy">{{ $officialMainTitle }}</div>
                <div class="official-bracket-title-line-2 jpba-extra-heavy">{{ $officialSeriesTitle }} {{ $officialSeasonTitle }}</div>
                <div class="official-bracket-title-line-3 jpba-extra-heavy">決勝（{{ $stageNumber($semifinalQualifierCount) }}名によるシュートアウト方式）</div>
                <div class="official-bracket-title-line-4 jpba-extra-heavy">会場：{{ $officialVenueTitle }}</div>
            </div>

            <div class="official-bracket-rule-block jpba-heavy">
                <div class="official-bracket-rule-row">
                    ①シュートアウト 1stマッチ（５位〜８位通過の４名にて１Ｇを投球し最上位者を選出）
                </div>
                <div class="official-bracket-rule-row">
                    ②シュートアウト 2ndマッチ（２位〜４位通過の３名及び1stマッチ最上位者の計４名にて１Ｇを投球し最上位者を選出）
                </div>
                <div class="official-bracket-rule-row">
                    ③優勝決定戦（トップシードと2ndマッチの最上位者にて１Ｇを投球し優勝者を決定）
                </div>
            </div>

            <div class="official-bracket-wrap">
                <img class="official-bracket-image" src="{!! $shootoutBracketImage !!}" alt="シュートアウト結果図">
            </div>

            @if ($firstScoreImage)
                <div class="official-score-section">
                    <div class="official-score-heading">
                        <span class="official-score-logo">JPBA</span>
                        <span class="official-score-title">{{ $scoreHeading($firstScoreImage, 0) }}</span>
                    </div>

                    <img class="official-score-image" src="{!! $firstScoreImage['image'] ?? '' !!}" alt="優勝決定戦スコア表">
                </div>
            @endif
        </div>
    @endif

    @if (count($remainingScoreImages) > 0)
        <div class="official-next-score-page">
            <div class="official-next-score-main-title">
                <div class="official-next-score-title-line-1 jpba-extra-heavy">{{ $officialMainTitle }}</div>
                <div class="official-next-score-title-line-2 jpba-extra-heavy">{{ $officialSeriesTitle }} {{ $officialSeasonTitle }}</div>
                <div class="official-next-score-title-line-3 jpba-heavy">決勝（{{ $stageNumber($semifinalQualifierCount) }}名によるシュートアウト方式） ／ 会場：{{ $officialVenueTitle }}</div>
            </div>

            @foreach ($remainingScoreImages as $index => $scoreSheetImage)
                <div class="official-next-score-block">
                    <div class="official-next-score-heading">
                        <span class="official-score-logo">JPBA</span>
                        <span class="official-next-score-title">{{ $scoreHeading($scoreSheetImage, $index + 1) }}</span>
                    </div>

                    <img class="official-score-image" src="{!! $scoreSheetImage['image'] ?? '' !!}" alt="スコア表">
                </div>
            @endforeach
        </div>
    @elseif (!isset($shootoutBracketImage) && count($scoreImages) > 0)
        <div class="official-plain-score-page">
            <div class="official-next-score-main-title">
                <div class="official-next-score-title-line-1 jpba-extra-heavy">{{ $officialMainTitle }}</div>
                <div class="official-next-score-title-line-2 jpba-extra-heavy">{{ $officialSeriesTitle }} {{ $officialSeasonTitle }}</div>
                <div class="official-next-score-title-line-3 jpba-heavy">決勝（{{ $stageNumber($semifinalQualifierCount) }}名によるシュートアウト方式） ／ 会場：{{ $officialVenueTitle }}</div>
            </div>

            @foreach ($scoreImages as $index => $scoreSheetImage)
                <div class="official-next-score-block">
                    <div class="official-next-score-heading">
                        <span class="official-score-logo">JPBA</span>
                        <span class="official-score-title">{{ $scoreHeading($scoreSheetImage, $index) }}</span>
                    </div>

                    <img class="official-score-image" src="{!! $scoreSheetImage['image'] ?? '' !!}" alt="スコア表">
                </div>
            @endforeach
        </div>
    @endif


    @if (count($pdfScoreSnapshots) > 0)
        @foreach ($pdfScoreSnapshots as $snapshotSet)
            @php
                $snapshot = $snapshotSet['snapshot'] ?? null;
                $snapshotRows = collect($snapshotSet['rows'] ?? [])->values();
                $resultCode = trim((string) ($snapshot->result_code ?? ''));
                $isPrelimSnapshot = $resultCode === 'prelim_total';
                $isSemifinalSnapshot = $resultCode === 'semifinal_total';
                $targetStage = $isSemifinalSnapshot ? '準決勝' : '予選';
                $gameCount = $isSemifinalSnapshot ? 4 : 8;
            @endphp

            <div class="official-snapshot-page">
                <div class="official-snapshot-title jpba-extra-heavy">
                    {{ $officialMainTitle }}<br>
                    {{ $officialSeriesTitle }} {{ $officialSeasonTitle }}
                </div>
                <div class="official-snapshot-subtitle jpba-heavy">
                    {{ $snapshot ? $snapshotTitle($snapshot) : '大会成績' }}
                    @if ($venueText !== '')
                        ／ {{ $venueText }}
                    @endif
                </div>

                @if ($isSemifinalSnapshot)
                    <table class="official-snapshot-table jpba-heavy">
                        <thead>
                            <tr>
                                <th rowspan="2" class="snap-step-col">ｽﾃｯﾌﾟ<br>ﾎﾟｲﾝﾄ</th>
                                <th rowspan="2" class="snap-rank-col">順位</th>
                                <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                                <th rowspan="2" class="snap-name-col">氏　名</th>
                                <th rowspan="2" class="snap-period-col">期</th>
                                <th rowspan="2" class="snap-arm-col">投</th>
                                <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                                <th rowspan="2" class="snap-wide-total-col">予選8G</th>
                                <th rowspan="2" class="snap-avg-col">AVG</th>
                                <th rowspan="2" class="snap-rank-col">順位</th>
                                <th colspan="4">準　決　勝</th>
                                <th rowspan="2" class="snap-half-col">準決勝<br>4G</th>
                                <th rowspan="2" class="snap-avg-col">AVG</th>
                                <th rowspan="2" class="snap-wide-total-col">通算12G<br>T/PIN</th>
                                <th rowspan="2" class="snap-avg-col">AVG</th>
                            </tr>
                            <tr>
                                @for ($game = 1; $game <= $gameCount; $game++)
                                    <th class="snap-game-col">{{ $game }}G</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshotRows as $index => $row)
                                @php
                                    $rank = $snapshotValue($row, ['ranking'], '-');
                                    $carryPin = $snapshotValue($row, ['carry_pin'], '');
                                    $scratchPin = $snapshotValue($row, ['scratch_pin'], '');
                                    $totalPin = $snapshotValue($row, ['total_pin'], '');
                                    $average = $snapshotValue($row, ['average'], '');
                                    $points = $snapshotValue($row, ['points'], '');
                                    $semiAverage = ($scratchPin !== '' && is_numeric($scratchPin) && $gameCount > 0) ? ((float) $scratchPin / $gameCount) : null;
                                    $prelimAverage = ($carryPin !== '' && is_numeric($carryPin) && ($prelimGameCount ?? 0) > 0) ? ((float) $carryPin / (int) $prelimGameCount) : null;
                                    $prelimRank = $snapshotPrelimRank($row);
                                    $belong = $snapshotBelong($row);
                                    $stepPointLabel = $stepPointLabelForSemifinalRank($rank, $points);
                                    $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                    $semifinalRowClasses = [];
                                    if ($rankInt === (int) ($semifinalQualifierCount ?: 8)) {
                                        $semifinalRowClasses[] = 'semifinal-finalist-border';
                                    }
                                @endphp
                                <tr class="{{ implode(' ', $semifinalRowClasses) }}">
                                    @if ($index === 0)
                                        <td rowspan="{{ (int) ($semifinalQualifierCount ?: 8) }}" class="finalist-ref-cell">入<br>賞<br>者<br>リ<br>ス<br>ト<br>参<br>照</td>
                                    @elseif ($index >= (int) ($semifinalQualifierCount ?: 8))
                                        <td class="step-point-cell">{{ $stepPointLabel }}</td>
                                    @endif
                                    <td>{{ $rank }}</td>
                                    <td>{{ $snapshotLicense($row) }}</td>
                                    <td class="text-left">{{ $snapshotName($row) }}</td>
                                    <td>{{ $snapshotPeriod($row) }}</td>
                                    <td>{{ $snapshotArm($row) }}</td>
                                    <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                    <td>{{ $formatNumber($carryPin) }}</td>
                                    <td>{{ $prelimAverage === null ? '-' : number_format($prelimAverage, 2) }}</td>
                                    <td>{{ $prelimRank ?? '-' }}</td>
                                    @for ($game = 1; $game <= $gameCount; $game++)
                                        @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                        <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                    @endfor
                                    <td>{{ $formatNumber($scratchPin) }}</td>
                                    <td>{{ $semiAverage === null ? '-' : number_format($semiAverage, 2) }}</td>
                                    <td>{{ $formatNumber($totalPin) }}</td>
                                    <td>{{ $average === '' ? '-' : number_format((float) $average, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    @php
                        $prelimPreparedRows = [];
                        $firstHalfTotals = [];
                        foreach ($snapshotRows as $rowForHalf) {
                            $key = $snapshotLicenseKey($rowForHalf);
                            $firstHalf = 0;
                            $secondHalf = 0;
                            for ($game = 1; $game <= 4; $game++) {
                                $score = $snapshotScoreFor($targetStage, $rowForHalf, $game);
                                $firstHalf += is_numeric($score) ? (int) $score : 0;
                            }
                            for ($game = 5; $game <= 8; $game++) {
                                $score = $snapshotScoreFor($targetStage, $rowForHalf, $game);
                                $secondHalf += is_numeric($score) ? (int) $score : 0;
                            }
                            $firstHalfTotals[$key] = $firstHalf;
                            $prelimPreparedRows[] = [$rowForHalf, $firstHalf, $secondHalf, $key];
                        }
                        arsort($firstHalfTotals);
                        $firstHalfRankByKey = [];
                        $firstHalfRank = 1;
                        foreach ($firstHalfTotals as $key => $total) {
                            $firstHalfRankByKey[$key] = $firstHalfRank++;
                        }
                    @endphp
                    <table class="official-snapshot-table jpba-heavy">
                        <thead>
                            <tr>
                                <th rowspan="2" class="snap-rank-col">順位</th>
                                <th rowspan="2" class="snap-license-col">ﾗｲｾﾝｽ<br>No.</th>
                                <th rowspan="2" class="snap-name-col">氏　名</th>
                                <th rowspan="2" class="snap-period-col">期</th>
                                <th rowspan="2" class="snap-arm-col">投</th>
                                <th rowspan="2" class="snap-belong-col">所　属<br>/ 用品契約</th>
                                <th colspan="4">1G&nbsp;&nbsp;2G&nbsp;&nbsp;3G&nbsp;&nbsp;4G</th>
                                <th rowspan="2" class="snap-half-col">前半</th>
                                <th rowspan="2" class="snap-rank-col">順位</th>
                                <th colspan="4">5G&nbsp;&nbsp;6G&nbsp;&nbsp;7G&nbsp;&nbsp;8G</th>
                                <th rowspan="2" class="snap-half-col">後半</th>
                                <th rowspan="2" class="snap-wide-total-col">8G<br>T/PIN</th>
                                <th rowspan="2" class="snap-avg-col">AVG</th>
                            </tr>
                            <tr>
                                @for ($game = 1; $game <= 8; $game++)
                                    <th class="snap-game-col">{{ $game }}G</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prelimPreparedRows as $prepared)
                                @php
                                    [$row, $firstHalf, $secondHalf, $key] = $prepared;
                                    $rank = $snapshotValue($row, ['ranking'], '-');
                                    $totalPin = $snapshotValue($row, ['total_pin'], '');
                                    $average = $snapshotValue($row, ['average'], '');
                                    $belong = $snapshotBelong($row);
                                    $rankInt = is_numeric($rank) ? (int) $rank : 0;
                                    $isQualified = $rankInt > 0 && $rankInt <= (int) ($prelimQualifierCount ?: 0);
                                    $prelimRowClasses = [];
                                    if ($rankInt === 8) {
                                        $prelimRowClasses[] = 'prelim-top-eight-border';
                                    }
                                    if ($rankInt === (int) ($prelimQualifierCount ?: 0)) {
                                        $prelimRowClasses[] = 'prelim-qualified-border';
                                    }
                                @endphp
                                <tr class="{{ implode(' ', $prelimRowClasses) }}">
                                    <td class="{{ $isQualified ? 'qualified-cell' : '' }}">{{ $rank }}</td>
                                    <td>{{ $snapshotLicense($row) }}</td>
                                    <td class="text-left">{{ $snapshotName($row) }}</td>
                                    <td>{{ $snapshotPeriod($row) }}</td>
                                    <td>{{ $snapshotArm($row) }}</td>
                                    <td class="{{ $snapshotBelongClass($belong) }}">{{ $belong }}</td>
                                    @for ($game = 1; $game <= 4; $game++)
                                        @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                        <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                    @endfor
                                    <td>{{ $formatNumber($firstHalf) }}</td>
                                    <td>{{ $firstHalfRankByKey[$key] ?? '-' }}</td>
                                    @for ($game = 5; $game <= 8; $game++)
                                        @php $score = $snapshotScoreFor($targetStage, $row, $game); @endphp
                                        <td class="{{ $scoreTextClass($score) }}">{{ $score === null ? '-' : $formatNumber($score) }}</td>
                                    @endfor
                                    <td>{{ $formatNumber($secondHalf) }}</td>
                                    <td>{{ $formatNumber($totalPin) }}</td>
                                    <td>{{ $average === '' ? '-' : number_format((float) $average, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="official-snapshot-note jpba-heavy">
                    ※このページは正式反映済みスナップショットとゲーム別スコアをもとに出力しています。
                </div>
            </div>
        @endforeach
    @endif

</body>
</html>