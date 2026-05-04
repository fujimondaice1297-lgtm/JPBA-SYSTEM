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

        .official-prize-table .rank-col { width: 11%; }
        .official-prize-table .license-col { width: 8%; }
        .official-prize-table .name-col { width: 17%; }
        .official-prize-table .period-col { width: 5%; }
        .official-prize-table .belong-col { width: 26%; }
        .official-prize-table .total-point-col { width: 10%; }
        .official-prize-table .award-point-col { width: 8%; }
        .official-prize-table .step-point-col { width: 8%; }
        .official-prize-table .prize-col { width: 7%; }

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
            margin: 26px 0 24px 0;
            text-align: center;
            font-size: 21px;
            line-height: 1.4;
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

        $resolveName = function ($result) use ($valueOf) {
            return optional($result->player)->name_kanji
                ?? optional($result->bowler)->name_kanji
                ?? $valueOf($result, ['player_name', 'name', 'display_name', 'amateur_name'], '-');
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

            return trim((string) $period) !== '' ? trim((string) $period) : '-';
        };

        $resolveBelong = function ($result) use ($valueOf) {
            $belong = $valueOf($result, [
                'affiliation',
                'belonging',
                'organization',
                'sponsor',
                'sponsor_name',
                'company_name',
                'club_name',
                'center_name',
                'shop_name',
            ], '');

            if ($belong === '') {
                $belong = optional($result->player)->affiliation
                    ?? optional($result->player)->belonging
                    ?? optional($result->player)->organization
                    ?? optional($result->player)->sponsor
                    ?? optional($result->bowler)->affiliation
                    ?? optional($result->bowler)->belonging
                    ?? optional($result->bowler)->organization
                    ?? optional($result->bowler)->sponsor
                    ?? '';
            }

            return trim((string) $belong) !== '' ? trim((string) $belong) : '-';
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
                        <td class="nowrap">{{ $licenseNo }}</td>
                        <td class="text-left">{{ $name }}</td>
                        <td class="nowrap">{{ $period }}</td>
                        <td class="text-left">{{ $belong }}</td>
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
                {{ $seriesTitle }}<br>
                決勝（8名によるシュートアウト方式）
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
            <h2 class="official-plain-score-title">シュートアウト・スコア表</h2>

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

</body>
</html>