@extends('layouts.app')

@section('content')
@php
    $data = is_array($shootout ?? null) ? $shootout : [];
    $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
    $seedRows = array_values((array) ($data['seed_rows'] ?? []));
    $shootoutBody = is_array($data['shootout'] ?? null) ? $data['shootout'] : [];
    $summary = is_array($shootoutBody['summary'] ?? null) ? $shootoutBody['summary'] : [];
    $matches = array_values((array) ($shootoutBody['matches'] ?? []));
    $missingSeedSnapshot = (bool) ($data['missing_seed_snapshot'] ?? false);

    $isPublic = ((int) request('public', 0) === 1);
    $shiftValue = (string) ($shifts ?? request('shifts', ''));
    $genderValue = (string) ($gender_filter ?? request('gender_filter', ''));

    $snapshotIndexUrl = null;
    if (!empty($meta['tournament_id'])) {
        $snapshotIndexUrl = route('tournaments.result_snapshots.index', ['tournament' => $meta['tournament_id']]);
    }

    $slotName = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'pending') {
            return (string) ($slot['display_name'] ?? $slot['label'] ?? '勝者未確定');
        }

        if ($type === 'empty') {
            return (string) ($slot['display_name'] ?? $slot['label'] ?? '未確定');
        }

        return (string) ($slot['display_name'] ?? $slot['label'] ?? '—');
    };

    $slotSub = function (array $slot): string {
        $parts = [];

        if (!empty($slot['source_ranking'])) {
            $parts[] = '通過順位 ' . $slot['source_ranking'] . '位';
        } elseif (isset($slot['seed']) && $slot['seed'] !== null) {
            $parts[] = '通過順位 ' . $slot['seed'] . '位';
        }

        if (!empty($slot['pro_bowler_license_no'])) {
            $parts[] = (string) $slot['pro_bowler_license_no'];
        }

        if (!empty($slot['total_pin'])) {
            $parts[] = number_format((int) $slot['total_pin']) . ' pin';
        }

        return implode(' / ', $parts);
    };

    $slotClass = function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'pending') {
            return 'so-slot-pending';
        }

        if ($type === 'empty') {
            return 'so-slot-empty';
        }

        return '';
    };

    $seedByNumber = [];
    foreach ($seedRows as $seedRow) {
        $seedRow = (array) $seedRow;
        $seedNo = (int) ($seedRow['seed'] ?? 0);

        if ($seedNo > 0) {
            $seedByNumber[$seedNo] = $seedRow;
        }
    }

    $matchByNo = [];
    foreach ($matches as $index => $matchItem) {
        $matchItem = (array) $matchItem;
        $matchNo = (int) ($matchItem['match_no'] ?? ($index + 1));

        if ($matchNo > 0) {
            $matchByNo[$matchNo] = $matchItem;
        }
    }

    $scoreForSeedInMatch = function (int $seed, int $matchNo) use ($matchByNo): ?int {
        $match = (array) ($matchByNo[$matchNo] ?? []);
        $slots = (array) ($match['slots'] ?? []);
        $scores = (array) ($match['scores'] ?? []);

        foreach ($slots as $slotCode => $slot) {
            $slot = (array) $slot;
            $slotSeed = isset($slot['seed']) && $slot['seed'] !== null ? (int) $slot['seed'] : null;

            if ($slotSeed !== $seed) {
                continue;
            }

            if (!array_key_exists($slotCode, $scores)) {
                return null;
            }

            if ($scores[$slotCode] === null || $scores[$slotCode] === '') {
                return null;
            }

            return (int) $scores[$slotCode];
        }

        return null;
    };

    $winnerSeedInMatch = function (int $matchNo) use ($matchByNo): ?int {
        $match = (array) ($matchByNo[$matchNo] ?? []);
        $winnerNode = (array) ($match['winner_node'] ?? []);

        if (!isset($winnerNode['seed']) || $winnerNode['seed'] === null) {
            return null;
        }

        return (int) $winnerNode['seed'];
    };

    $seedName = function (int $seed) use ($seedByNumber): string {
        $row = (array) ($seedByNumber[$seed] ?? []);

        return (string) ($row['display_name'] ?? $row['name'] ?? '—');
    };

    $seedLicense = function (int $seed) use ($seedByNumber): string {
        $row = (array) ($seedByNumber[$seed] ?? []);

        return (string) ($row['pro_bowler_license_no'] ?? '—');
    };

    $seedTermLabel = function (int $seed) use ($seedByNumber): string {
        $row = (array) ($seedByNumber[$seed] ?? []);

        $candidateKeys = [
            'term_label',
            'period_label',
            'generation_label',
            'class_label',
            'pro_period_label',
            'pro_generation_label',
            'bowler_period_label',
            'bowler_generation_label',
            'kisei_label',
            'term',
            'term_no',
            'period',
            'period_no',
            'generation',
            'generation_no',
            'class_no',
            'pro_period',
            'pro_period_no',
            'pro_generation',
            'pro_generation_no',
            'bowler_period',
            'bowler_generation',
            'membership_generation',
            'license_period',
            'license_period_no',
            'registered_period',
            'registered_period_no',
            'kisei',
        ];

        foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null || $value === '') {
                continue;
            }

            $value = (string) $value;

            if (str_contains($value, '期')) {
                return $value;
            }

            return $value . '期生';
        }

        return '';
    };

    $seedKana = function (int $seed) use ($seedByNumber): string {
        $row = (array) ($seedByNumber[$seed] ?? []);

        $candidateKeys = [
            'display_name_kana',
            'name_kana',
            'name_kana_full',
            'kana_name',
            'kana',
            'furigana',
            'name_furigana',
            'name_reading',
            'player_kana',
            'bowler_kana',
        ];

        foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null || $value === '') {
                continue;
            }

            return (string) $value;
        }

        return '';
    };

    $sourceRankLabel = function (int $seed) use ($seedByNumber): string {
        $row = (array) ($seedByNumber[$seed] ?? []);
        $rank = $row['source_ranking'] ?? null;

        return $rank !== null && $rank !== '' ? ((string) $rank . '位通過') : ((string) $seed . '位通過');
    };

    $scoreLabel = function (?int $score): string {
        return $score === null ? '—' : (string) $score;
    };

    $firstWinnerSeed = $winnerSeedInMatch(1);
    $secondWinnerSeed = $winnerSeedInMatch(2);
    $finalWinnerSeed = $winnerSeedInMatch(3);

    $championName = (string) ($summary['winner_name'] ?? '');
    if ($championName === '' && $finalWinnerSeed !== null) {
        $championName = $seedName($finalWinnerSeed);
    }

    $finalRankBySeed = [];

    if ($finalWinnerSeed !== null) {
        $finalRankBySeed[$finalWinnerSeed] = 1;
    }

    if ($secondWinnerSeed !== null && $finalWinnerSeed !== null) {
        $finalLoserSeed = $finalWinnerSeed === 1 ? $secondWinnerSeed : 1;
        if ($finalLoserSeed !== null && $finalLoserSeed > 0) {
            $finalRankBySeed[$finalLoserSeed] = 2;
        }
    }

    if ($firstWinnerSeed !== null && $secondWinnerSeed !== null) {
        $secondMatchSeeds = array_values(array_unique(array_filter([2, 3, 4, $firstWinnerSeed])));
        sort($secondMatchSeeds);
        $rank = 3;
        foreach ($secondMatchSeeds as $seed) {
            if ((int) $seed === (int) $secondWinnerSeed) {
                continue;
            }
            $finalRankBySeed[(int) $seed] = $rank;
            $rank++;
        }
    }

    if ($firstWinnerSeed !== null) {
        $firstMatchSeeds = [5, 6, 7, 8];
        $rank = 6;
        foreach ($firstMatchSeeds as $seed) {
            if ((int) $seed === (int) $firstWinnerSeed) {
                continue;
            }
            $finalRankBySeed[$seed] = $rank;
            $rank++;
        }
    }

    $finalRankLabel = function (int $seed) use ($finalRankBySeed): string {
        $rank = $finalRankBySeed[$seed] ?? null;

        if ($rank === null) {
            return '（未定）';
        }

        if ((int) $rank === 1) {
            return '（優　勝）';
        }

        return '（第' . $rank . '位）';
    };

    $seedRowsByFinalRank = $seedRows;
    usort($seedRowsByFinalRank, function ($a, $b) use ($finalRankBySeed) {
        $a = (array) $a;
        $b = (array) $b;

        $aSeed = (int) ($a['seed'] ?? 0);
        $bSeed = (int) ($b['seed'] ?? 0);

        $aRank = $finalRankBySeed[$aSeed] ?? 999;
        $bRank = $finalRankBySeed[$bSeed] ?? 999;

        if ($aRank === $bRank) {
            return $aSeed <=> $bSeed;
        }

        return $aRank <=> $bRank;
    });

    $pdfSeedTop = [
        1 => 18,
        2 => 64,
        3 => 110,
        4 => 156,
        5 => 222,
        6 => 268,
        7 => 314,
        8 => 360,
    ];

    $pdfSeedCenter = function (int $seed) use ($pdfSeedTop): int {
        return (int) (($pdfSeedTop[$seed] ?? 0) + 20);
    };

    $scoreTop = function (int $seed) use ($pdfSeedTop): int {
        return (int) (($pdfSeedTop[$seed] ?? 0) + 6);
    };

    $boxLeft = 116;
    $boxWidth = 268;
    $boxRight = $boxLeft + $boxWidth;
    $scoreWidth = 46;

    $match1ScoreX = 456;
    $match2ScoreX = 598;
    $match3ScoreX = 744;

    $match1BracketX = 532;
    $match2BracketX = 682;
    $match3BracketX = 812;

    $championX = 858;
    $championY = 150;
    $championMidY = 190;

    /*
     * 公式PDF風の見え方に寄せるため、1st勝者は実際の通過順位行から
     * いったん 5〜8位グループ中央の出力線へ上げてから 2nd マッチへ接続する。
     * これにより、赤線指示のような「下段から上がって次へ進む」勝ち上がり線になる。
     */
    $firstMatchOutputY = (int) (($pdfSeedCenter(5) + $pdfSeedCenter(8)) / 2);
    $secondMatchOutputY = $firstMatchOutputY;

    $firstWinnerY = $firstWinnerSeed ? $pdfSeedCenter($firstWinnerSeed) : $firstMatchOutputY;
    $secondWinnerY = $secondWinnerSeed ? $secondMatchOutputY : $firstMatchOutputY;
    $finalOpponentY = $secondMatchOutputY;

    $match2ScoreTopForSeed = function (int $seed) use ($scoreTop, $firstWinnerSeed, $firstMatchOutputY): int {
        if ($firstWinnerSeed !== null && (int) $seed === (int) $firstWinnerSeed) {
            return $firstMatchOutputY - 13;
        }

        return $scoreTop($seed);
    };

    $match3ScoreTopForSeed = function (int $seed) use ($scoreTop, $secondWinnerSeed, $secondMatchOutputY): int {
        if ($secondWinnerSeed !== null && (int) $seed === (int) $secondWinnerSeed) {
            return $secondMatchOutputY - 13;
        }

        return $scoreTop($seed);
    };

    /*
     * シュートアウト図式は、線・枠を画像テンプレートに固定し、
     * 選手名・スコア・最終順位・勝ち上がり太線だけを上に重ねる。
     * これによりブラウザ幅やフォント差による線ズレを避ける。
     */
    $shootoutTemplateUrl = asset('images/shootout_tournament_bracket_template.png');

    $templateLeft = 96;
    $templateTop = 0;

    $templateRows = [
        1 => 37,
        2 => 111,
        3 => 185,
        4 => 259,
        5 => 333,
        6 => 407,
        7 => 481,
        8 => 555,
    ];

    $templateRowCenter = function (int $seed) use ($templateRows, $templateTop): int {
        return (int) ($templateTop + ($templateRows[$seed] ?? 0) + 29);
    };

    $templateBoxRight = $templateLeft + 272;
    $templateFirstX = $templateLeft + 345;
    $templateSecondX = $templateLeft + 430;
    $templateFinalX = $templateLeft + 514;
    $templateWinnerX = $templateLeft + 600;
    $templateWinnerY = $templateTop + 212;
    $templateWinnerMidY = $templateWinnerY + 38;

    /*
     * 背景テンプレートの線位置に合わせる固定座標。
     * 1st: 5〜8位通過
     * 2nd: 2〜4位通過 + 1st勝者
     * Final: 1位通過 + 2nd勝者
     */
    $templateFirstOutY = $templateTop + 445;
    $templateSecondOutY = $templateTop + 363;

    $scorePositions = [];

    /*
     * スコアは「線の上」に置き、縦線には重ならないように
     * ラウンドごとに横位置を固定する。
     */
    foreach ([5, 6, 7, 8] as $seed) {
        $scoreTopOffsetBySeed = [
            8 => -5,
        ];

        $scorePositions[] = [
            'seed' => $seed,
            'match_no' => 1,
            'left' => $templateLeft + 298,
            'top' => $templateRowCenter($seed) - 34 + (int) ($scoreTopOffsetBySeed[$seed] ?? 0),
            'winner' => $firstWinnerSeed !== null && (int) $firstWinnerSeed === (int) $seed,
        ];
    }

    foreach ([2, 3, 4] as $seed) {
        $scoreTopOffsetBySeed = [
            3 => -5,
            4 => -5,
        ];

        $scorePositions[] = [
            'seed' => $seed,
            'match_no' => 2,
            'left' => $templateLeft + 376,
            'top' => $templateRowCenter($seed) - 24 + (int) ($scoreTopOffsetBySeed[$seed] ?? 0),
            'winner' => $secondWinnerSeed !== null && (int) $secondWinnerSeed === (int) $seed,
        ];
    }

    if ($firstWinnerSeed !== null) {
        $scorePositions[] = [
            'seed' => (int) $firstWinnerSeed,
            'match_no' => 2,
            'left' => $templateLeft + 378,
            'top' => $templateFirstOutY - 19,
            'winner' => $secondWinnerSeed !== null && (int) $secondWinnerSeed === (int) $firstWinnerSeed,
        ];
    }

    $scorePositions[] = [
        'seed' => 1,
        'match_no' => 3,
        'left' => $templateLeft + 462,
        'top' => $templateRowCenter(1) - 34,
        'winner' => $finalWinnerSeed !== null && (int) $finalWinnerSeed === 1,
    ];

    if ($secondWinnerSeed !== null) {
        $scorePositions[] = [
            'seed' => (int) $secondWinnerSeed,
            'match_no' => 3,
            'left' => $templateLeft + 462,
            'top' => $templateSecondOutY - 34,
            'winner' => $finalWinnerSeed !== null && (int) $finalWinnerSeed === (int) $secondWinnerSeed,
        ];
    }

    $pathSegments = [];

    /*
     * 検証用：
     * 背景テンプレート上の「対戦に使う線」すべてに太線を重ねる。
     * 勝ち上がり判定は使わず、全ルートを固定座標で表示する。
     */
    $addHorizontalPath = function (int $x1, int $x2, int $y) use (&$pathSegments): void {
        if ($x1 === $x2) {
            return;
        }

        $left = min($x1, $x2) - 3;
        $width = abs($x2 - $x1) + 3;

        $pathSegments[] = [
            'class' => 'so-path-horizontal',
            'left' => $left,
            'top' => $y - 3,
            'width' => $width,
            'height' => 6,
        ];
    };

    $addSeedHorizontalPath = function (int $x1, int $x2, int $y) use (&$pathSegments): void {
        if ($x1 === $x2) {
            return;
        }

        /*
         * 選手枠から出る横線だけを左へ延長する。
         * 赤〇で指定された「線の長さ不足」への対応。
         * 入口線以外は変更しない。
         */
        $left = min($x1, $x2) - 7;
        $width = abs($x2 - $x1) + 7;

        $pathSegments[] = [
            'class' => 'so-path-horizontal',
            'left' => $left,
            'top' => $y - 3,
            'width' => $width,
            'height' => 6,
        ];
    };

    $addVerticalPath = function (int $x, int $y1, int $y2) use (&$pathSegments): void {
        if ($y1 === $y2) {
            return;
        }

        /*
         * v25:
         * 縦線の位置は変えず、上下だけを3pxずつ延長する。
         * 横線の太さ6pxに対して、角の欠けを埋めるための調整。
         */
        $top = min($y1, $y2) - 3;
        $height = abs($y2 - $y1) + 6;

        $pathSegments[] = [
            'class' => 'so-path-vertical',
            'left' => $x - 3,
            'top' => $top,
            'width' => 6,
            'height' => $height,
        ];
    };

    /*
     * 横線は実表示で背景線より少し上に乗っていたため、
     * 対戦で使う横線だけを個別に下へ補正する。
     * 縦線は現状ほぼ合っているため、X座標は v8 のまま維持。
     */
    /*
     * 横線Y座標の個別補正。
     * 上段は現状維持。
     * 下段は実表示で下に落ちていたため、下段へ行くほど上方向へ強めに戻す。
     */
    /*
     * 背景テンプレート画像の実線位置に合わせた最終補正。
     * 行ごとの横線Yを個別に合わせる。
     */
    /*
     * v17:
     * v15の横方向補正は維持し、Y座標だけを背景画像の元線へ戻す。
     * v16で悪化した下方向補正は採用しない。
     */
    /*
     * v18:
     * 赤丸指定箇所に合わせて、行ごとの横線Yをさらに個別補正。
     * 上段1本目と下段6〜8位側は、元線より下に見えていたため上方向へ寄せる。
     */
    /*
     * v21:
     * v20を基準に、赤丸指定の2箇所だけを縦方向に補正。
     * 1位通過の横線は上にずれていたため下へ移動。
     * 8位通過の横線は下にずれていたため上へ移動。
     */
    $horizontalLineOffsetBySeed = [
        1 => 3,
        2 => 3,
        3 => -1,
        4 => -2,
        5 => -3,
        6 => -5,
        7 => -5,
        8 => -10,
    ];

    $lineY = function (int $seed) use ($templateRowCenter, $horizontalLineOffsetBySeed): int {
        return $templateRowCenter($seed) + (int) ($horizontalLineOffsetBySeed[$seed] ?? 0);
    };

    $firstOutputLineY = $templateTop + 464;
    $secondOutputLineY = $templateTop + 360;
    $winnerOutputLineY = $templateTop + 243;

    /*
     * v34:
     * 座標・位置は変更せず、勝ち進んだ選手のルートだけ太線を重ねる。
     * 背景画像の細線はそのまま残るため、敗退者側の線は細線のまま表示される。
     */

    /*
     * 1stマッチ勝者：5位〜8位通過のうち勝ち上がった選手だけ
     */
    if ($firstWinnerSeed !== null && in_array((int) $firstWinnerSeed, [5, 6, 7, 8], true)) {
        $addSeedHorizontalPath($templateBoxRight, $templateFirstX, $lineY((int) $firstWinnerSeed));
        $addVerticalPath($templateFirstX, $lineY((int) $firstWinnerSeed), $firstOutputLineY);
        $addHorizontalPath($templateFirstX, $templateSecondX, $firstOutputLineY);
    }

    /*
     * 2ndマッチ勝者：2位〜4位通過、または1stマッチ勝者
     */
    if ($secondWinnerSeed !== null) {
        $secondWinnerSeed = (int) $secondWinnerSeed;

        if (in_array($secondWinnerSeed, [2, 3, 4], true)) {
            $addSeedHorizontalPath($templateBoxRight, $templateSecondX, $lineY($secondWinnerSeed));
            $addVerticalPath($templateSecondX, $lineY($secondWinnerSeed), $secondOutputLineY);
            $addHorizontalPath($templateSecondX, $templateFinalX, $secondOutputLineY);
        } elseif (
            $firstWinnerSeed !== null
            && $secondWinnerSeed === (int) $firstWinnerSeed
            && in_array($secondWinnerSeed, [5, 6, 7, 8], true)
        ) {
            /*
             * 1st勝者が2ndも勝った場合。
             * 1st側のルートは上で描画済みなので、2nd縦線以降だけを追加する。
             */
            $addVerticalPath($templateSecondX, $firstOutputLineY, $secondOutputLineY);
            $addHorizontalPath($templateSecondX, $templateFinalX, $secondOutputLineY);
        }
    }

    /*
     * 優勝決定戦勝者：1位通過、または2ndマッチ勝者
     */
    if ($finalWinnerSeed !== null) {
        $finalWinnerSeed = (int) $finalWinnerSeed;

        if ($finalWinnerSeed === 1) {
            $addSeedHorizontalPath($templateBoxRight, $templateFinalX, $lineY(1));
            $addVerticalPath($templateFinalX, $lineY(1), $winnerOutputLineY);
            $addHorizontalPath($templateFinalX, $templateWinnerX, $winnerOutputLineY);
        } elseif (
            $secondWinnerSeed !== null
            && $finalWinnerSeed === (int) $secondWinnerSeed
        ) {
            /*
             * 2nd勝者が優勝した場合。
             * 2ndまでのルートは上で描画済みなので、Final縦線以降だけを追加する。
             */
            $addVerticalPath($templateFinalX, $secondOutputLineY, $winnerOutputLineY);
            $addHorizontalPath($templateFinalX, $templateWinnerX, $winnerOutputLineY);
        }
    }
@endphp

@if($isPublic)
<style>
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }
    body { padding-top: 0 !important; }
    .container, .container-fluid { margin-top: 0 !important; }
</style>
@endif

<style>
    .so-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        padding: 1rem;
        margin: 1rem auto;
        max-width: 1180px;
    }

    .so-title {
        font-weight: 800;
        font-size: 1.35rem;
        margin-bottom: .25rem;
    }

    .so-sub {
        color: #6b7280;
        font-size: .92rem;
        margin-bottom: .75rem;
    }

    .so-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin: .85rem 0 1rem;
    }

    .so-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: .6rem;
        margin: 1rem 0;
    }

    .so-summary-box {
        border: 1px solid #e5e7eb;
        border-radius: .85rem;
        padding: .75rem;
        background: #f9fafb;
    }

    .so-summary-label {
        font-size: .8rem;
        color: #6b7280;
    }

    .so-summary-value {
        font-size: 1.15rem;
        font-weight: 800;
    }

    .so-table-wrap {
        overflow-x: auto;
        margin-top: 1rem;
    }

    .so-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    .so-table th,
    .so-table td {
        border: 1px solid #d1d5db;
        padding: .5rem .55rem;
        text-align: center;
        vertical-align: middle;
        font-size: 1.0rem;
    }

    .so-table th {
        background: #f3f4f6;
        font-weight: 700;
    }

    .so-left {
        text-align: left !important;
    }

    .so-match-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(280px, 1fr));
        gap: .85rem;
        margin-top: 1rem;
    }

    .so-match {
        border: 1px solid #d1d5db;
        border-radius: .9rem;
        padding: .85rem;
        background: #ffffff;
    }

    .so-match-head {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        align-items: flex-start;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: .55rem;
        margin-bottom: .65rem;
    }

    .so-match-title {
        font-weight: 800;
    }

    .so-match-rank {
        color: #6b7280;
        font-size: .8rem;
        white-space: nowrap;
    }

    .so-description {
        color: #6b7280;
        font-size: .83rem;
        margin-bottom: .65rem;
    }

    .so-slot {
        border: 1px solid #e5e7eb;
        border-radius: .7rem;
        padding: .55rem;
        margin-bottom: .45rem;
        background: #f9fafb;
    }

    .so-slot-pending {
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .so-slot-empty {
        background: #f3f4f6;
        color: #6b7280;
    }

    .so-seed {
        display: inline-block;
        font-weight: 800;
        margin-right: .35rem;
        color: #1f2937;
    }

    .so-name {
        font-weight: 700;
    }

    .so-mini {
        font-size: .78rem;
        color: #6b7280;
        margin-top: .15rem;
    }

    .so-score-form {
        margin-top: .65rem;
        padding-top: .65rem;
        border-top: 1px dashed #d1d5db;
    }

    .so-score-row {
        display: grid;
        grid-template-columns: 1fr 88px;
        gap: .5rem;
        align-items: center;
        margin-bottom: .45rem;
    }

    .so-score-row label {
        margin: 0;
        font-size: .85rem;
        color: #374151;
        font-weight: 700;
    }

    .so-score-row input[type="number"] {
        width: 98px;
        text-align: right;
    }

    .so-winner {
        margin-top: .5rem;
        padding: .45rem .55rem;
        border-radius: .55rem;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
        font-weight: 800;
    }

    .so-tie-warning {
        margin-top: .5rem;
        padding: .45rem .55rem;
        border-radius: .55rem;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        font-weight: 800;
    }

    .so-note {
        margin-top: 1rem;
        padding: .85rem;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        border-radius: .85rem;
        color: #1e3a8a;
        font-size: 1.0rem;
    }

    .so-official-wrap {
        margin-top: 1.25rem;
        border: 2px solid #111827;
        background: #fff;
        border-radius: .35rem;
        padding: .8rem;
        overflow-x: auto;
    }

    .so-official-title {
        text-align: center;
        font-weight: 900;
        font-size: 1.25rem;
        line-height: 1.4;
        margin-bottom: .35rem;
        color: #111827;
    }

    .so-official-rule {
        text-align: center;
        font-size: 1.0rem;
        font-weight: 700;
        line-height: 1.55;
        color: #111827;
        margin-bottom: .65rem;
    }

    .so-official-board {
        position: relative;
        width: 1040px;
        height: 430px;
        margin: 0 auto;
        background: #fff;
    }

    .so-pdf-lines {
        position: absolute;
        inset: 0;
        width: 1040px;
        height: 430px;
        pointer-events: none;
        z-index: 1;
    }

    .so-pdf-line {
        stroke: #111827;
        stroke-width: 2.2;
        fill: none;
        stroke-linecap: square;
        stroke-linejoin: miter;
        shape-rendering: crispEdges;
    }

    .so-pdf-line-thin {
        stroke: #111827;
        stroke-width: 1.4;
        fill: none;
        stroke-linecap: square;
        shape-rendering: crispEdges;
    }

    .so-pdf-line-active {
        stroke: #111827;
        stroke-width: 5;
        fill: none;
        stroke-linecap: square;
        stroke-linejoin: miter;
        shape-rendering: crispEdges;
    }

    .so-pdf-final-rank {
        position: absolute;
        left: 0;
        width: 96px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: .5rem;
        font-weight: 900;
        font-size: .92rem;
        color: #111827;
        z-index: 2;
        white-space: nowrap;
    }

    .so-pdf-seed-box {
        position: absolute;
        left: 116px;
        width: 268px;
        height: 40px;
        border: 2px solid #111827;
        background: #fff;
        z-index: 3;
        display: grid;
        grid-template-columns: 72px 1fr;
        align-items: center;
    }

    .so-pdf-seed-box.is-winner {
        border-width: 4px;
    }

    .so-pdf-seed-label {
        height: 100%;
        border-right: 1px solid #111827;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: .82rem;
        line-height: 1.1;
    }

    .so-pdf-player {
        padding: .12rem .45rem;
        line-height: 1.12;
        text-align: center;
    }

    .so-pdf-name {
        font-weight: 900;
        font-size: .95rem;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .so-pdf-license {
        font-size: .68rem;
        color: #4b5563;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .so-pdf-score {
        position: absolute;
        width: 46px;
        height: 26px;
        line-height: 26px;
        text-align: center;
        font-weight: 900;
        font-size: 1rem;
        background: #fff;
        color: #111827;
        z-index: 4;
    }

    .so-pdf-score.is-winner {
        font-size: 1.08rem;
        font-weight: 900;
    }

    .so-pdf-score-1 { left: 414px; }
    .so-pdf-score-2 { left: 560px; }
    .so-pdf-score-3 { left: 706px; }

    .so-pdf-champion {
        position: absolute;
        left: 858px;
        top: 150px;
        width: 178px;
        min-height: 80px;
        border: 4px solid #111827;
        background: #fff;
        z-index: 5;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: .45rem;
        text-align: center;
    }

    .so-pdf-champion-label {
        font-weight: 900;
        font-size: 1rem;
        color: #111827;
    }

    .so-pdf-champion-name {
        margin-top: .2rem;
        font-weight: 900;
        font-size: 1.08rem;
        color: #111827;
        line-height: 1.2;
    }

    .so-pdf-caption {
        position: absolute;
        left: 858px;
        top: 242px;
        width: 200px;
        font-weight: 800;
        color: #111827;
        line-height: 1.45;
        font-size: 1.0rem;
    }


    .so-template-board {
        position: relative;
        width: 1110px;
        height: 636px;
        margin: 0 auto;
        background: #fff;
        overflow: hidden;
    }

    .so-template-image {
        position: absolute;
        left: 96px;
        top: 0;
        width: 922px;
        height: 636px;
        object-fit: contain;
        z-index: 1;
        user-select: none;
        pointer-events: none;
    }

    .so-template-final-rank-heading {
        position: absolute;
        left: 0;
        width: 98px;
        height: 30px;
        line-height: 30px;
        text-align: right;
        padding-right: 6px;
        font-weight: 900;
        font-size: 1.36rem;
        color: #111827;
        z-index: 4;
        white-space: nowrap;
    }

    .so-template-final-rank {
        position: absolute;
        left: 0;
        width: 98px;
        height: 34px;
        line-height: 34px;
        text-align: right;
        padding-right: 6px;
        font-weight: 900;
        font-size: 1.32rem;
        color: #111827;
        z-index: 4;
        white-space: nowrap;
    }

    .so-template-term {
        position: absolute;
        width: 98px;
        height: 24px;
        line-height: 24px;
        text-align: left;
        font-weight: 800;
        font-size: 1.06rem;
        color: #111827;
        z-index: 5;
        pointer-events: none;
        white-space: nowrap;
    }

    .so-template-player {
        position: absolute;
        width: 262px;
        text-align: center;
        color: #111827;
        z-index: 5;
        pointer-events: none;
    }

    .so-template-player-name {
        font-weight: 900;
        font-size: 1.72rem;
        line-height: 1.0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .so-template-score {
        position: absolute;
        width: 50px;
        min-height: 24px;
        line-height: 24px;
        text-align: center;
        font-weight: 800;
        font-size: 1.12rem;
        color: #111827;
        background: transparent;
        z-index: 7;
        pointer-events: none;
    }

    .so-template-score.is-winner {
        font-weight: 900;
        font-size: 1.34rem;
        color: #dc2626;
    }

    .so-template-champion-kana {
        position: absolute;
        left: 712px;
        top: 218px;
        width: 262px;
        text-align: center;
        font-weight: 800;
        font-size: 1.0rem;
        line-height: 1;
        color: #111827;
        z-index: 7;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .so-template-champion-name {
        position: absolute;
        left: 712px;
        top: 241px;
        width: 262px;
        text-align: center;
        font-weight: 900;
        font-size: 2.05rem;
        line-height: 1.1;
        color: #111827;
        z-index: 7;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .so-path-horizontal,
    .so-path-vertical {
        position: absolute;
        background: #111827;
        pointer-events: none;
    }

    .so-path-horizontal {
        z-index: 6;
    }

    .so-path-vertical {
        z-index: 7;
    }

    @media (max-width: 1199.98px) {
        .so-match-grid {
            grid-template-columns: 1fr;
        }

        .so-summary {
            grid-template-columns: repeat(2, minmax(120px, 1fr));
        }
    }
</style>

<div class="so-card">
    <div class="so-title">{{ $tournament_name }} / シュートアウト</div>
    <div class="so-sub">
        進出元：{{ $meta['seed_source_name'] ?? '—' }}
        ／ 進出人数：{{ (int) ($meta['qualifier_count'] ?? 0) }}名
        ／ 方式：{{ $meta['shootout_format_name'] ?? '標準8名方式' }}
        @if(!empty($meta['seed_snapshot_id']))
            ／ seed snapshot: #{{ $meta['seed_snapshot_id'] }}
        @endif
    </div>

    @unless($isPublic)
    <div class="so-toolbar">
        <a class="btn btn-outline-secondary" href="{{ url('/scores/input') }}">速報入力ページへ</a>

        @if($snapshotIndexUrl)
            <a class="btn btn-outline-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
        @endif

        <a class="btn btn-outline-secondary" href="{{ route('scores.result', array_filter([
            'tournament_id' => request('tournament_id'),
            'stage' => 'シュートアウト',
            'upto_game' => $upto_game ?? request('upto_game', 1),
            'shifts' => $shiftValue,
            'gender_filter' => $genderValue,
            'public' => 1,
        ])) }}">共有URL(公開)</a>
    </div>
    @endunless

    <div class="so-summary">
        <div class="so-summary-box">
            <div class="so-summary-label">進出人数</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['qualifier_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">マッチ数</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['match_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">完了マッチ</div>
            <div class="so-summary-value">{{ number_format((int) ($summary['completed_match_count'] ?? 0)) }}</div>
        </div>
        <div class="so-summary-box">
            <div class="so-summary-label">現在の勝者</div>
            <div class="so-summary-value">{{ $summary['winner_name'] ?? '—' }}</div>
        </div>
    </div>

    @if($missingSeedSnapshot)
        <div class="alert alert-warning">
            シュートアウト進出元 snapshot（{{ $meta['seed_source_result_code'] ?? '—' }} / {{ $meta['seed_source_name'] ?? '—' }}）が見つかりません。<br>
            先に進出元ステージの正式成績反映を実行してください。

            @unless($isPublic)
                @if($snapshotIndexUrl)
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
                    </div>
                @endif
            @endunless
        </div>
    @else
        @if((int) ($meta['seed_row_count'] ?? 0) < 8)
            <div class="alert alert-warning">
                標準8名シュートアウトに対して、進出元snapshotから取得できたseed行が {{ (int) ($meta['seed_row_count'] ?? 0) }} 名です。<br>
                先に準決勝通算などの進出元成績を8名以上で反映してください。
            </div>
        @endif

        <div class="so-official-wrap">
            <div class="so-official-title">{{ $tournament_name }} シュートアウト</div>
            <div class="so-official-rule">
                ① シュートアウト1stマッチ（5位〜8位通過の4名にて1Gを投球し最上位者を選出）<br>
                ② シュートアウト2ndマッチ（2位〜4位通過の3名及び1stマッチ最上位者の計4名にて1Gを投球し最上位者を選出）<br>
                ③ 優勝決定戦（トップシードと2ndマッチ最上位者にて1Gを投球し優勝者を決定）
            </div>

            <div class="so-template-board"><!-- shootout overlay v4: fixed score/rank/name coordinates -->
                <img
                    src="{{ $shootoutTemplateUrl }}"
                    alt="シュートアウト対戦表テンプレート"
                    class="so-template-image"
                >

                @foreach($pathSegments as $segment)
                    <div
                        class="{{ $segment['class'] }}"
                        style="left: {{ $segment['left'] }}px; top: {{ $segment['top'] }}px; width: {{ $segment['width'] }}px; height: {{ $segment['height'] }}px;"
                    ></div>
                @endforeach

                <div class="so-template-final-rank-heading" style="top: {{ ($templateRows[1] ?? 37) - 28 }}px;">
                    最終順位
                </div>

                @for($seed = 1; $seed <= 8; $seed++)
                    @php
                        $templateRowTop = $templateRows[$seed] ?? 0;
                        $termLabel = $seedTermLabel($seed);

                        /*
                         * v27:
                         * 選手名の縦位置だけを個別調整。
                         * 1〜2位通過は下へ、5〜8位通過は上へ。
                         * 3〜4位通過は基準位置として変更しない。
                         */
                        $playerNameTopOffsetBySeed = [
                            1 => 5,
                            2 => 4,
                            5 => -4,
                            6 => -7,
                            7 => -9,
                            8 => -11,
                        ];
                        $playerNameTopOffset = (int) ($playerNameTopOffsetBySeed[$seed] ?? 0);

                        /*
                         * v32:
                         * 最終順位ラベルの縦位置だけを個別調整。
                         * 各選手枠の中央に揃えるため、上段は下へ、下段は上へ補正する。
                         * 線・スコア・選手名・期表示は変更しない。
                         */
                        $finalRankTopOffsetBySeed = [
                            1 => 8,
                            2 => 5,
                            3 => 2,
                            4 => -1,
                            5 => -3,
                            6 => -6,
                            7 => -10,
                            8 => -12,
                        ];
                        $finalRankTopOffset = (int) ($finalRankTopOffsetBySeed[$seed] ?? 0);
                    @endphp

                    <div class="so-template-final-rank" style="top: {{ $templateRowTop + 12 + $finalRankTopOffset }}px;">
                        {{ $finalRankLabel($seed) }}
                    </div>

                    @if($termLabel !== '')
                        @php
                            /*
                             * v30:
                             * 期表示の縦位置だけを個別調整。
                             * 3位通過の位置を基準にし、1〜2位通過は下へ、4〜8位通過は上へ移動。
                             * 何も付けていない基準位置（3位通過）へ寄せる。
                             * 線・スコア・選手名・順位表示は変更しない。
                             */
                            $termTopOffsetBySeed = [
                                1 => 4,
                                2 => 3,
                                4 => -3,
                                5 => -5,
                                6 => -10,
                                7 => -13,
                                8 => -16,
                            ];
                            $termTopOffset = (int) ($termTopOffsetBySeed[$seed] ?? 0);
                        @endphp
                        <div class="so-template-term" style="left: {{ $templateLeft + 122 }}px; top: {{ $templateRowTop + 4 + $termTopOffset }}px;">
                            （{{ $termLabel }}）
                        </div>
                    @endif

                    <div class="so-template-player" style="left: {{ $templateLeft + 10 }}px; top: {{ $templateRowTop + 31 + $playerNameTopOffset }}px;">
                        <div class="so-template-player-name">{{ $seedName($seed) }}</div>
                    </div>
                @endfor

                @foreach($scorePositions as $scorePosition)
                    @php
                        $scoreSeed = (int) ($scorePosition['seed'] ?? 0);
                        $scoreMatchNo = (int) ($scorePosition['match_no'] ?? 0);
                        $scoreValue = $scoreSeed > 0 && $scoreMatchNo > 0 ? $scoreForSeedInMatch($scoreSeed, $scoreMatchNo) : null;
                    @endphp

                    @if($scoreValue !== null)
                        <div
                            class="so-template-score {{ !empty($scorePosition['winner']) ? 'is-winner' : '' }}"
                            style="left: {{ $scorePosition['left'] }}px; top: {{ $scorePosition['top'] }}px;"
                        >
                            {{ $scoreLabel($scoreValue) }}
                        </div>
                    @endif
                @endforeach

                @php
                    $championKana = $finalWinnerSeed !== null ? $seedKana((int) $finalWinnerSeed) : '';
                @endphp

                @if($championKana !== '')
                    <div class="so-template-champion-kana">
                        {{ $championKana }}
                    </div>
                @endif


                <div class="so-template-champion-name">
                    {{ $championName !== '' ? $championName : '—' }}
                </div>
            </div>
        </div>

        <div class="so-table-wrap">
            <h4>進出者一覧</h4>
            <table class="so-table">
                <thead>
                    <tr>
                        <th>最終順位</th>
                        <th>通過順位</th>
                        <th class="so-left">選手名</th>
                        <th>ライセンスNo</th>
                        <th>通算ピン</th>
                        <th>G数</th>
                        <th>AVG</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($seedRowsByFinalRank as $row)
                        @php
                            $row = (array) $row;
                            $seed = (int) ($row['seed'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $seed > 0 ? $finalRankLabel($seed) : '—' }}</td>
                            <td>{{ $row['source_ranking'] ?? $row['seed'] ?? '—' }}</td>
                            <td class="so-left">{{ $row['display_name'] ?? '—' }}</td>
                            <td>{{ $row['pro_bowler_license_no'] ?? '—' }}</td>
                            <td>{{ isset($row['total_pin']) && $row['total_pin'] !== null ? number_format((int) $row['total_pin']) : '—' }}</td>
                            <td>{{ $row['games'] ?? '—' }}</td>
                            <td>{{ isset($row['average']) && $row['average'] !== null ? number_format((float) $row['average'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="so-match-grid">
            @foreach($matches as $match)
                @php
                    $match = (array) $match;
                    $slotCodes = array_values((array) ($match['slot_codes'] ?? []));
                    $slots = (array) ($match['slots'] ?? []);
                    $scores = (array) ($match['scores'] ?? []);
                    $canInput = !$isPublic && !empty($match['can_input']);
                    $winnerNode = (array) ($match['winner_node'] ?? []);
                @endphp

                <div class="so-match">
                    <div class="so-match-head">
                        <div class="so-match-title">{{ $match['label'] ?? '—' }}</div>
                        <div class="so-match-rank">敗退者: {{ $match['loser_rank_range'] ?? '—' }}</div>
                    </div>

                    <div class="so-description">{{ $match['description'] ?? '' }}</div>

                    @foreach($slotCodes as $slotCode)
                        @php
                            $slot = (array) ($slots[$slotCode] ?? []);
                            $scoreValue = $scores[$slotCode] ?? null;
                        @endphp

                        <div class="so-slot {{ $slotClass($slot) }}">
                            @if(in_array(($slot['type'] ?? ''), ['seed', 'advanced'], true) && isset($slot['seed']))
                                <span class="so-seed">{{ !empty($slot['source_ranking']) ? $slot['source_ranking'] . '位通過' : $slot['seed'] . '位通過' }}</span>
                            @endif
                            <span class="so-name">{{ $slotName($slot) }}</span>
                            @if($slotSub($slot) !== '')
                                <div class="so-mini">{{ $slotSub($slot) }}</div>
                            @endif
                            @if($scoreValue !== null)
                                <div class="so-mini">スコア: {{ $scoreValue }}</div>
                            @endif
                        </div>
                    @endforeach

                    @if($canInput)
                        <form method="POST" action="{{ route('scores.shootout.store') }}" class="so-score-form">
                            @csrf
                            <input type="hidden" name="tournament_id" value="{{ $meta['tournament_id'] ?? request('tournament_id') }}">
                            <input type="hidden" name="match_no" value="{{ $match['match_no'] ?? 1 }}">
                            <input type="hidden" name="match_key" value="{{ $match['match_key'] ?? '' }}">
                            <input type="hidden" name="upto_game" value="{{ $upto_game ?? request('upto_game', 1) }}">
                            <input type="hidden" name="shifts" value="{{ $shiftValue }}">
                            <input type="hidden" name="gender_filter" value="{{ $genderValue }}">

                            @foreach($slotCodes as $slotCode)
                                @php $slot = (array) ($slots[$slotCode] ?? []); @endphp
                                @foreach([
                                    'type',
                                    'seed',
                                    'display_name',
                                    'label',
                                    'pro_bowler_id',
                                    'pro_bowler_license_no',
                                    'amateur_name',
                                    'participant_key',
                                    'source_row_id',
                                    'source_ranking',
                                    'total_pin',
                                    'games',
                                    'average',
                                    'min_seed',
                                    'max_seed',
                                ] as $field)
                                    @if(array_key_exists($field, $slot) && !is_array($slot[$field]))
                                        <input type="hidden" name="slots[{{ $slotCode }}][{{ $field }}]" value="{{ $slot[$field] }}">
                                    @endif
                                @endforeach
                            @endforeach

                            @foreach($slotCodes as $slotCode)
                                @php
                                    $slot = (array) ($slots[$slotCode] ?? []);
                                    $scoreValue = $scores[$slotCode] ?? null;
                                @endphp
                                @if(in_array(($slot['type'] ?? ''), ['seed', 'advanced'], true))
                                    <div class="so-score-row">
                                        <label>{{ $slotCode }}：{{ $slotName($slot) }}</label>
                                        <input type="number" name="scores[{{ $slotCode }}]" class="form-control form-control-sm" min="0" max="300" value="{{ $scoreValue !== null ? $scoreValue : '' }}">
                                    </div>
                                @endif
                            @endforeach

                            <button type="submit" class="btn btn-sm btn-primary">このマッチを保存</button>
                        </form>
                    @else
                        <div class="so-mini mt-2">
                            @if(!empty($match['is_complete']))
                                マッチ完了済みです。
                            @else
                                前マッチの勝者確定後に入力できます。
                            @endif
                        </div>
                    @endif

                    @if(!empty($match['is_tied']))
                        <div class="so-tie-warning">
                            最高点が同点です。勝者を確定できません。タイブレーク後のスコアに修正してください。
                        </div>
                    @elseif(!empty($match['winner_node']))
                        <div class="so-winner">
                            勝者：{{ $winnerNode['display_name'] ?? $winnerNode['label'] ?? '—' }}
                            @if(!empty($match['winner_to']))
                                → {{ $match['winner_to'] }}
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="so-note">
            シュートアウトでは、各マッチのスコアは勝ち上がり者を決めるために使います。<br>
            敗退者の最終順位は、そのマッチのスコア順ではなく、敗退ラウンドと進出元snapshotの通過順位で決定します。
        </div>
    @endif
</div>
@endsection
