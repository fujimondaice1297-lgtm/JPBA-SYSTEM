@extends('layouts.app')

@section('content')
@php
    $data = (array) ($singleElimination ?? []);
    $meta = (array) ($data['meta'] ?? []);
    $bracket = (array) ($data['bracket'] ?? []);
    $summary = (array) ($bracket['summary'] ?? []);
    $rounds = array_values((array) ($bracket['rounds'] ?? []));
    $seedRows = array_values((array) ($data['seed_rows'] ?? []));
    $missingSeedSnapshot = (bool) ($data['missing_seed_snapshot'] ?? false);

    $shiftValue = trim((string) request('shifts', $shifts ?? ''));
    $genderValue = trim((string) request('gender_filter', $gender_filter ?? ''));
    $isPublic = ((int) request('public', 0) === 1);

    $tournamentId = (int) ($meta['tournament_id'] ?? request('tournament_id', 0));
    $currentUptoGame = (int) ($upto_game ?? request('upto_game', 1));
    $qualifierCount = (int) ($summary['qualifier_count'] ?? $meta['qualifier_count'] ?? count($seedRows));
    $bracketSize = (int) ($summary['bracket_size'] ?? 0);
    $roundCount = (int) ($summary['round_count'] ?? count($rounds));

    $snapshotIndexUrl = $tournamentId > 0
        ? route('tournaments.result_snapshots.index', ['tournament' => $tournamentId])
        : null;

    $publicUrl = $tournamentId > 0
        ? route('scores.result', array_filter([
            'tournament_id' => $tournamentId,
            'stage' => 'トーナメント',
            'upto_game' => $currentUptoGame,
            'shifts' => $shiftValue,
            'gender_filter' => $genderValue,
            'public' => 1,
        ], static fn ($v) => $v !== null && $v !== ''))
        : request()->fullUrlWithQuery(['public' => 1]);

    $allMatches = [];
    $matchByKey = [];
    foreach ($rounds as $round) {
        $round = (array) $round;
        foreach (array_values((array) ($round['matches'] ?? [])) as $match) {
            $match = (array) $match;
            $matchKey = (string) ($match['match_key'] ?? '');
            $allMatches[] = $match;
            if ($matchKey !== '') {
                $matchByKey[$matchKey] = $match;
            }
        }
    }

    $actualMatchCount = 0;
    $completedMatchCount = 0;
    $tiedMatchCount = 0;
    $waitingMatchCount = 0;
    $byeMatchCount = 0;
    $championName = '未確定';

    $isPlayableSlot = static function (array $slot): bool {
        return in_array((string) ($slot['type'] ?? ''), ['seed', 'advanced'], true);
    };

    foreach ($allMatches as $match) {
        $isBye = (bool) ($match['is_bye'] ?? false);
        if ($isBye) {
            $byeMatchCount++;
        } else {
            $actualMatchCount++;
        }

        if (!empty($match['is_tied'])) {
            $tiedMatchCount++;
        }

        if (!$isBye && !empty($match['is_complete']) && !empty($match['winner_node'])) {
            $completedMatchCount++;
        }

        $slotA = (array) ($match['slot_a'] ?? []);
        $slotB = (array) ($match['slot_b'] ?? []);
        $slotAPlayable = $isPlayableSlot($slotA);
        $slotBPlayable = $isPlayableSlot($slotB);
        $hasScoreA = ($match['score_a'] ?? null) !== null && ($match['score_a'] ?? '') !== '';
        $hasScoreB = ($match['score_b'] ?? null) !== null && ($match['score_b'] ?? '') !== '';

        if (!$isBye && $slotAPlayable && $slotBPlayable && empty($match['is_complete']) && (!$hasScoreA || !$hasScoreB || !empty($match['is_tied']))) {
            $waitingMatchCount++;
        }
    }

    $lastRound = !empty($rounds) ? (array) $rounds[count($rounds) - 1] : [];
    $finalMatches = array_values((array) ($lastRound['matches'] ?? []));
    if (!empty($finalMatches)) {
        $finalMatch = (array) $finalMatches[count($finalMatches) - 1];
        $winnerNode = (array) ($finalMatch['winner_node'] ?? []);
        if (!empty($finalMatch['is_complete']) && !empty($winnerNode)) {
            $championName = trim((string) ($winnerNode['display_name'] ?? $winnerNode['label'] ?? '')) ?: '未確定';
        }
    }

    $progressPercent = $actualMatchCount > 0
        ? min(100, (int) floor(($completedMatchCount / $actualMatchCount) * 100))
        : 0;

    $slotName = static function (array $slot): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'bye') {
            return 'BYE';
        }

        if ($type === 'winner') {
            return trim((string) ($slot['display_name'] ?? $slot['label'] ?? '前試合の勝者')) ?: '前試合の勝者';
        }

        return trim((string) ($slot['display_name'] ?? $slot['label'] ?? '—')) ?: '—';
    };

    $formatLicenseShort = static function ($license): string {
        $license = trim((string) $license);
        if ($license === '') {
            return '';
        }

        return mb_strlen($license) > 4 ? mb_substr($license, -4) : $license;
    };

    $slotSub = static function (array $slot) use ($formatLicenseShort): string {
        $type = (string) ($slot['type'] ?? '');

        if ($type === 'bye') {
            return '自動進出';
        }

        if ($type === 'winner') {
            $sourceMatch = trim((string) ($slot['source_match_key'] ?? ''));
            return $sourceMatch !== '' ? $sourceMatch . ' 勝者待ち' : '勝者待ち';
        }

        $parts = [];

        if (isset($slot['seed']) && $slot['seed'] !== null && $slot['seed'] !== '') {
            $parts[] = (int) $slot['seed'] . '位通過';
        }

        $license = $formatLicenseShort($slot['pro_bowler_license_no'] ?? '');
        if ($license !== '') {
            $parts[] = 'Lic.' . $license;
        }


        return implode(' / ', $parts);
    };

    $slotSeedLabel = static function (array $slot): string {
        if (isset($slot['seed']) && $slot['seed'] !== null && $slot['seed'] !== '') {
            return (int) $slot['seed'] . '位';
        }

        if (isset($slot['min_seed']) && $slot['min_seed'] !== null && $slot['min_seed'] !== 999999) {
            return 'S' . (int) $slot['min_seed'];
        }

        return '';
    };

    $slotStatusText = static function (array $slot, array $match, string $slotCode): string {
        $type = (string) ($slot['type'] ?? '');
        $winnerSlot = (string) ($match['winner_slot'] ?? '');

        if ($type === 'bye') {
            return 'BYE';
        }

        if ($type === 'winner') {
            return '待機';
        }

        if (!empty($match['is_tied'])) {
            return '同点';
        }

        if (!empty($match['is_complete']) && $winnerSlot === $slotCode) {
            return '勝者';
        }

        if (!empty($match['is_complete']) && $winnerSlot !== '' && $winnerSlot !== $slotCode) {
            return '敗退';
        }

        return '未確定';
    };

    $scoreText = static function ($score): string {
        if ($score === null || $score === '') {
            return '—';
        }

        return (string) (int) $score;
    };

    $tournamentSeedSettings = [];
    if (isset($tournament)) {
        $rawTournamentSeedSettings = $tournament->single_elimination_seed_settings ?? [];
        if (is_string($rawTournamentSeedSettings) && trim($rawTournamentSeedSettings) !== '') {
            $decodedTournamentSeedSettings = json_decode($rawTournamentSeedSettings, true);
            $tournamentSeedSettings = is_array($decodedTournamentSeedSettings) ? $decodedTournamentSeedSettings : [];
        } elseif (is_array($rawTournamentSeedSettings)) {
            $tournamentSeedSettings = $rawTournamentSeedSettings;
        }
    }

    $laneSettingCandidates = [
        $data['single_elimination_lane_settings'] ?? null,
        $data['lane_settings'] ?? null,
        $meta['single_elimination_lane_settings'] ?? null,
        $meta['lane_settings'] ?? null,
        $tournamentSeedSettings['lane_settings'] ?? null,
        $tournamentSeedSettings['single_elimination_lane_settings'] ?? null,
    ];

    $laneSettings = [];
    foreach ($laneSettingCandidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $decodedCandidate = json_decode($candidate, true);
            $candidate = is_array($decodedCandidate) ? $decodedCandidate : [];
        }

        if (is_array($candidate) && !empty($candidate)) {
            $laneSettings = $candidate;
            break;
        }
    }

    // 互換対応：rounds で包まれず {"1": {...}, "2": {...}} の形で保存されていても表示できるようにする。
    if (!isset($laneSettings['rounds']) && !isset($laneSettings['matches'])) {
        $looksLikeRoundMap = false;
        foreach ($laneSettings as $roundKey => $roundSetting) {
            if ((string) (int) $roundKey === (string) $roundKey && is_array($roundSetting)) {
                $looksLikeRoundMap = true;
                break;
            }
        }

        if ($looksLikeRoundMap) {
            $laneSettings = ['rounds' => $laneSettings];
        }
    }

    $laneLabelForMatch = static function (array $match) use ($laneSettings): string {
        $matchKey = trim((string) ($match['match_key'] ?? ''));
        $roundNo = (int) ($match['round_no'] ?? 0);
        $matchNo = (int) ($match['match_no'] ?? 0);

        // Service側の配列構造によっては round_no / match_no が入らず、match_key だけが来る場合がある。
        // 例: R2-M1。この場合もレーン設定を引けるように、match_key から補完する。
        if (($roundNo <= 0 || $matchNo <= 0) && preg_match('/^R(\d+)-M(\d+)$/i', $matchKey, $matchKeyParts)) {
            $roundNo = $roundNo > 0 ? $roundNo : (int) $matchKeyParts[1];
            $matchNo = $matchNo > 0 ? $matchNo : (int) $matchKeyParts[2];
        }

        $explicit = $laneSettings['matches'] ?? $laneSettings['match_lanes'] ?? [];
        if (is_array($explicit) && $matchKey !== '') {
            $value = $explicit[$matchKey] ?? $explicit[strtolower($matchKey)] ?? $explicit[$roundNo . '-' . $matchNo] ?? null;
            if (is_array($value)) {
                $from = (int) ($value['from'] ?? $value['start'] ?? 0);
                $to = (int) ($value['to'] ?? $value['end'] ?? 0);
                if ($from > 0 && $to > 0) {
                    return $from . '-' . $to . 'L';
                }
            } elseif (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        $rounds = $laneSettings['rounds'] ?? $laneSettings['round_start_lanes'] ?? [];
        if (!is_array($rounds) || $roundNo <= 0 || $matchNo <= 0) {
            return '';
        }

        $setting = $rounds[(string) $roundNo] ?? $rounds[$roundNo] ?? null;
        if ($setting === null) {
            return '';
        }

        if (is_array($setting)) {
            $start = (int) ($setting['start_lane'] ?? $setting['from'] ?? $setting['start'] ?? 0);
            $step = (int) ($setting['step'] ?? 2);
            $width = (int) ($setting['width'] ?? 2);
        } else {
            $start = (int) $setting;
            $step = 2;
            $width = 2;
        }

        if ($start <= 0) {
            return '';
        }

        $laneFrom = $start + (($matchNo - 1) * max(1, $step));
        $laneTo = $laneFrom + max(1, $width) - 1;

        return $laneFrom === $laneTo ? ($laneFrom . 'L') : ($laneFrom . '-' . $laneTo . 'L');
    };

    $textFit = static function (string $text, int $width = 24): string {
        $text = trim($text);
        if ($text === '') {
            return '—';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $width, '…', 'UTF-8');
        }

        return strlen($text) > $width ? substr($text, 0, max(0, $width - 3)) . '...' : $text;
    };

    $slotSourceKey = static function (array $slot): string {
        return trim((string) ($slot['source_match_key'] ?? $slot['advanced_from_match_key'] ?? ''));
    };

    $hiddenSlotFields = [
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
    ];

    $layoutType = ($qualifierCount > 24 || $bracketSize > 32) ? 'wide_split' : 'compact';

    /*
     * Phase 1.7:
     * 表示座標は「ラウンドごとの均等配置」ではなく、決勝から逆算したツリー構造で作る。
     * これにより、
     * - 1回戦の選手枠は均等間隔で並ぶ
     * - 途中登場seedは、1回戦選手と同じ外側列へ置き、公式PDF風に揃える
     * - 準決勝 / 決勝側も、前ラウンドの勝者位置と次ラウンド入力枠が同じY座標で接続される
     * - 勝ち上がり線が斜めに飛ばず、すべて直角線でつながる
     */
    $slotW = $layoutType === 'wide_split' ? 132 : 156;
    $slotH = $layoutType === 'wide_split' ? 29 : 32;
    $slotGap = $layoutType === 'wide_split' ? 18 : 20;
    $leafStep = $slotH + $slotGap;
    $joinGap = $layoutType === 'wide_split' ? 18 : 22;
    $roundGap = $layoutType === 'wide_split' ? 92 : 104;
    $outerX = 28;
    $topY = $layoutType === 'wide_split' ? 82 : 84;
    $bottomPad = 72;
    $colStride = $slotW + $roundGap;
    $roundCount = max(1, (int) $roundCount);

    $firstRoundMatches = array_values((array) ($rounds[0]['matches'] ?? []));
    $firstRoundCount = max(1, count($firstRoundMatches));

    if ($layoutType === 'wide_split') {
        $sideRoundCount = max(1, $roundCount - 1);
        $centerX = $outerX + ($sideRoundCount * $colStride) + 190;
        $finalX = $centerX - (int) floor($slotW / 2);
        $rightOuterX = $centerX + 190 + (($sideRoundCount - 1) * $colStride);
        $canvasW = $rightOuterX + $slotW + $outerX;
    } else {
        $canvasW = $outerX + ($roundCount * $colStride) + 128;
        $centerX = null;
        $finalX = null;
        $rightOuterX = null;
    }

    $layoutMatches = [];
    $layoutSlots = [];
    $matchLines = [];
    $advanceLines = [];
    $matchByKey = [];
    $roundNoByMatch = [];

    foreach ($allMatches as $match) {
        $match = (array) $match;
        $key = (string) ($match['match_key'] ?? '');
        if ($key !== '') {
            $matchByKey[$key] = $match;
            $roundNoByMatch[$key] = (int) ($match['round_no'] ?? 0);
        }
    }

    $slotEdgeX = static function (array $slotLayout, string $side, bool $outgoing) use ($slotW): int {
        $x = (int) ($slotLayout['x'] ?? 0);

        if ($side === 'right') {
            return $outgoing ? $x : ($x + $slotW);
        }

        return $outgoing ? ($x + $slotW) : $x;
    };

    $matchJunctionX = static function (array $matchLayout) use ($slotW, $joinGap): int {
        $x = (int) ($matchLayout['x'] ?? 0);
        $side = (string) ($matchLayout['side'] ?? 'left');

        if ($side === 'right') {
            return $x - $joinGap;
        }

        return $x + $slotW + $joinGap;
    };

    $matchOutputX = $matchJunctionX;

    $sourceMatchKeyForSlot = static function (array $slot) use ($slotSourceKey): string {
        return $slotSourceKey($slot);
    };

    $matchSeedMin = static function (array $match): int {
        $slotA = (array) ($match['slot_a'] ?? []);
        $slotB = (array) ($match['slot_b'] ?? []);

        return min(
            (int) ($slotA['min_seed'] ?? $slotA['seed'] ?? 999999),
            (int) ($slotB['min_seed'] ?? $slotB['seed'] ?? 999999)
        );
    };

    $matchSeedMax = static function (array $match): int {
        $slotA = (array) ($match['slot_a'] ?? []);
        $slotB = (array) ($match['slot_b'] ?? []);

        return max(
            (int) ($slotA['max_seed'] ?? $slotA['seed'] ?? 999999),
            (int) ($slotB['max_seed'] ?? $slotB['seed'] ?? 999999)
        );
    };

    $getMatchX = static function (int $roundNo, string $side) use ($layoutType, $outerX, $colStride, $rightOuterX, $finalX): int {
        if ($layoutType === 'wide_split') {
            if ($side === 'right') {
                return (int) ($rightOuterX - (($roundNo - 1) * $colStride));
            }

            if ($side === 'center') {
                return (int) $finalX;
            }
        }

        return (int) ($outerX + (($roundNo - 1) * $colStride));
    };

    $getLeafSlotX = static function (string $side) use ($layoutType, $outerX, $rightOuterX): int {
        if ($layoutType === 'wide_split' && $side === 'right') {
            return (int) $rightOuterX;
        }

        return (int) $outerX;
    };

    $leafCounters = [
        'compact' => 0,
        'left' => 0,
        'right' => 0,
        'center' => 0,
    ];

    $assignLeafY = static function (string $side) use (&$leafCounters, $layoutType, $topY, $leafStep): int {
        $counterKey = $layoutType === 'wide_split'
            ? (in_array($side, ['left', 'right'], true) ? $side : 'left')
            : 'compact';

        $index = (int) ($leafCounters[$counterKey] ?? 0);
        $leafCounters[$counterKey] = $index + 1;

        return (int) round($topY + ($index * $leafStep));
    };

    $finalMatchKey = '';
    if (!empty($rounds)) {
        $finalRound = (array) $rounds[count($rounds) - 1];
        $finalRoundMatches = array_values((array) ($finalRound['matches'] ?? []));
        if (!empty($finalRoundMatches)) {
            $finalMatch = (array) $finalRoundMatches[count($finalRoundMatches) - 1];
            $finalMatchKey = (string) ($finalMatch['match_key'] ?? '');
        }
    }

    $layoutMatchRecursive = null;
    $layoutMatchRecursive = static function (string $matchKey, string $branchSide = 'left') use (
        &$layoutMatchRecursive,
        &$layoutMatches,
        &$layoutSlots,
        &$matchByKey,
        $roundCount,
        $layoutType,
        $sourceMatchKeyForSlot,
        $assignLeafY,
        $getMatchX,
        $getLeafSlotX,
        $slotW,
        $slotH,
        $leafStep,
        $finalMatchKey
    ): int {
        if (isset($layoutMatches[$matchKey])) {
            return (int) ($layoutMatches[$matchKey]['center_y'] ?? 0);
        }

        $match = (array) ($matchByKey[$matchKey] ?? []);
        if (empty($match)) {
            return $assignLeafY($branchSide);
        }

        $roundNo = (int) ($match['round_no'] ?? 1);
        $side = 'left';
        if ($layoutType === 'wide_split') {
            $side = $matchKey === $finalMatchKey || $roundNo >= $roundCount
                ? 'center'
                : ($branchSide === 'right' ? 'right' : 'left');
        }

        $x = $getMatchX($roundNo, $side);
        $slotCenters = [];
        $slotSourceKeys = [];

        foreach (['A' => (array) ($match['slot_a'] ?? []), 'B' => (array) ($match['slot_b'] ?? [])] as $slotCode => $slot) {
            $sourceKey = $sourceMatchKeyForSlot($slot);
            $slotSourceKeys[$slotCode] = $sourceKey;

            if ($sourceKey !== '' && isset($matchByKey[$sourceKey])) {
                $childBranch = $side;
                if ($layoutType === 'wide_split' && ($side === 'center' || $matchKey === $finalMatchKey)) {
                    $childBranch = $slotCode === 'A' ? 'left' : 'right';
                }

                $slotCenters[$slotCode] = $layoutMatchRecursive($sourceKey, $childBranch);
            } else {
                $leafSide = $side === 'center' ? ($slotCode === 'A' ? 'left' : 'right') : $side;
                $slotCenters[$slotCode] = $assignLeafY($leafSide);
            }
        }

        if (!isset($slotCenters['A'])) {
            $slotCenters['A'] = $assignLeafY($side);
        }
        if (!isset($slotCenters['B'])) {
            $slotCenters['B'] = $slotCenters['A'] + $leafStep;
        }

        if (abs((int) $slotCenters['A'] - (int) $slotCenters['B']) < ($slotH + 8)) {
            if ((int) $slotCenters['A'] <= (int) $slotCenters['B']) {
                $slotCenters['B'] = (int) $slotCenters['A'] + $leafStep;
            } else {
                $slotCenters['A'] = (int) $slotCenters['B'] + $leafStep;
            }
        }

        $centerY = (int) round(((int) $slotCenters['A'] + (int) $slotCenters['B']) / 2);

        $layoutMatches[$matchKey] = [
            'x' => $x,
            'center_y' => $centerY,
            'side' => $side,
            'match' => $match,
            'source_keys' => $slotSourceKeys,
        ];

        foreach (['A', 'B'] as $slotCode) {
            $cy = (int) ($slotCenters[$slotCode] ?? $centerY);
            $slot = $slotCode === 'A' ? (array) ($match['slot_a'] ?? []) : (array) ($match['slot_b'] ?? []);
            $slotType = (string) ($slot['type'] ?? '');
            $hasSourceMatch = ($slotSourceKeys[$slotCode] ?? '') !== '';
            $slotX = $x;

            // 途中登場seedは、公式トーナメント表のように1回戦選手と同じ外側列へ置く。
            // ここをラウンド列のままにすると、BYE/seed選手だけ内側へ浮いて見える。
            if (!$hasSourceMatch && $slotType === 'seed' && $roundNo > 1 && $side !== 'center') {
                $slotX = $getLeafSlotX($side);
            }

            $layoutSlots[$matchKey . ':' . $slotCode] = [
                'x' => $slotX,
                'y' => (int) round($cy - ($slotH / 2)),
                'w' => $slotW,
                'h' => $slotH,
                'center_y' => $cy,
                'side' => $side,
                'match_x' => $x,
                'has_source_match' => $hasSourceMatch,
            ];
        }

        return $centerY;
    };

    if ($finalMatchKey !== '') {
        $layoutMatchRecursive($finalMatchKey, 'left');
    }

    // 念のため、決勝ツリーから辿れなかった試合があればseed順で補完する。
    $matchesForFallback = array_values($matchByKey);
    usort($matchesForFallback, function (array $a, array $b) use ($matchSeedMin, $matchSeedMax): int {
        $roundCompare = ((int) ($a['round_no'] ?? 0)) <=> ((int) ($b['round_no'] ?? 0));
        if ($roundCompare !== 0) {
            return $roundCompare;
        }

        $minCompare = $matchSeedMin($a) <=> $matchSeedMin($b);
        if ($minCompare !== 0) {
            return $minCompare;
        }

        return $matchSeedMax($a) <=> $matchSeedMax($b);
    });

    foreach ($matchesForFallback as $match) {
        $matchKey = (string) ($match['match_key'] ?? '');
        if ($matchKey !== '' && !isset($layoutMatches[$matchKey])) {
            $layoutMatchRecursive($matchKey, 'left');
        }
    }

    /*
     * すべての座標を計算したあと、全体の上下余白だけを調整する。
     * 個別移動は線ずれの原因になるため、必ず全体移動に限定する。
     */
    $minY = null;
    $maxY = null;
    foreach ($layoutSlots as $slotLayout) {
        $y = (int) ($slotLayout['y'] ?? 0);
        $h = (int) ($slotLayout['h'] ?? $slotH);
        $minY = $minY === null ? $y : min($minY, $y);
        $maxY = $maxY === null ? ($y + $h) : max($maxY, $y + $h);
    }

    $shiftY = $minY !== null && $minY < 66 ? (66 - $minY) : 0;
    if ($shiftY > 0) {
        foreach ($layoutSlots as &$slotLayout) {
            $slotLayout['y'] = (int) $slotLayout['y'] + $shiftY;
            $slotLayout['center_y'] = (int) $slotLayout['center_y'] + $shiftY;
        }
        unset($slotLayout);

        foreach ($layoutMatches as &$matchLayout) {
            $matchLayout['center_y'] = (int) $matchLayout['center_y'] + $shiftY;
        }
        unset($matchLayout);

        $maxY = $maxY !== null ? $maxY + $shiftY : null;
    }

    $canvasH = max(
        $layoutType === 'wide_split' ? 760 : 620,
        ($maxY !== null ? $maxY + $bottomPad : 0)
    );

    $championBox = null;
    $championLine = null;
    if ($finalMatchKey !== '' && isset($layoutMatches[$finalMatchKey])) {
        $finalLayout = (array) $layoutMatches[$finalMatchKey];
        $finalMatch = (array) ($finalLayout['match'] ?? []);
        $championW = $layoutType === 'wide_split' ? 132 : 156;
        $championH = $slotH + 8;
        $finalOutputX = $matchOutputX($finalLayout);
        $championX = $finalOutputX + ($layoutType === 'wide_split' ? 78 : 92);
        $championY = (int) round(((int) ($finalLayout['center_y'] ?? 0)) - ($championH / 2));
        $championIsComplete = !empty($finalMatch['is_complete']) && !empty($finalMatch['winner_node']);

        $championBox = [
            'x' => (int) $championX,
            'y' => (int) $championY,
            'w' => (int) $championW,
            'h' => (int) $championH,
            'center_y' => (int) ($finalLayout['center_y'] ?? 0),
            'is_complete' => $championIsComplete,
            'name' => $championName,
        ];

        $championLine = [
            'points' => $finalOutputX . ',' . (int) ($finalLayout['center_y'] ?? 0) . ' ' . (int) $championX . ',' . (int) ($finalLayout['center_y'] ?? 0),
            'is_win' => $championIsComplete,
            'is_tied' => !empty($finalMatch['is_tied']),
        ];

        $canvasW = max($canvasW, (int) ($championX + $championW + $outerX));
    }

    $findNextSlot = static function (string $sourceMatchKey) use ($rounds, $slotSourceKey): ?array {
        foreach ($rounds as $round) {
            $round = (array) $round;
            foreach (array_values((array) ($round['matches'] ?? [])) as $match) {
                $match = (array) $match;
                $matchKey = (string) ($match['match_key'] ?? '');
                foreach (['A' => (array) ($match['slot_a'] ?? []), 'B' => (array) ($match['slot_b'] ?? [])] as $slotCode => $slot) {
                    if ($slotSourceKey($slot) === $sourceMatchKey) {
                        return [$matchKey, $slotCode];
                    }
                }
            }
        }

        return null;
    };

    foreach ($allMatches as $match) {
        $match = (array) $match;
        $matchKey = (string) ($match['match_key'] ?? '');
        if ($matchKey === '' || !isset($layoutMatches[$matchKey])) {
            continue;
        }

        $layoutMatch = $layoutMatches[$matchKey];
        $side = (string) ($layoutMatch['side'] ?? 'left');
        $junctionX = $matchJunctionX($layoutMatch);
        $junctionY = (int) ($layoutMatch['center_y'] ?? 0);

        foreach (['A' => (array) ($match['slot_a'] ?? []), 'B' => (array) ($match['slot_b'] ?? [])] as $slotCode => $slot) {
            $slotLayout = $layoutSlots[$matchKey . ':' . $slotCode] ?? null;
            if (!$slotLayout) {
                continue;
            }

            $slotType = (string) ($slot['type'] ?? '');
            if ($slotType === 'bye') {
                continue;
            }

            $slotY = (int) ($slotLayout['center_y'] ?? 0);
            $slotEdge = $slotEdgeX($slotLayout, $side, true);
            $isWinnerBranch = !empty($match['is_complete']) && (string) ($match['winner_slot'] ?? '') === $slotCode;

            $matchLines[] = [
                'points' => $slotEdge . ',' . $slotY . ' ' . $junctionX . ',' . $slotY . ' ' . $junctionX . ',' . $junctionY,
                'is_win' => $isWinnerBranch,
                'is_tied' => !empty($match['is_tied']),
            ];
        }

        $target = $findNextSlot($matchKey);
        if (!$target) {
            continue;
        }

        [$targetMatchKey, $targetSlotCode] = $target;
        $targetSlot = $layoutSlots[$targetMatchKey . ':' . $targetSlotCode] ?? null;
        if (!$targetSlot) {
            continue;
        }

        $targetSide = (string) ($targetSlot['side'] ?? 'left');
        $targetEdge = $slotEdgeX($targetSlot, $targetSide, false);
        $targetY = (int) ($targetSlot['center_y'] ?? 0);
        $outputX = $matchOutputX($layoutMatch);

        // 前試合の出力Yと次ラウンド入力枠Yを同一にしているため、基本は水平線。
        // もし設定変更等でY差が出ても、必ず直角線で接続する。
        if ($junctionY === $targetY) {
            $points = $outputX . ',' . $junctionY . ' ' . $targetEdge . ',' . $targetY;
        } else {
            $midX = (int) round(($outputX + $targetEdge) / 2);
            $points = $outputX . ',' . $junctionY . ' ' . $midX . ',' . $junctionY . ' ' . $midX . ',' . $targetY . ' ' . $targetEdge . ',' . $targetY;
        }

        $advanceLines[] = [
            'points' => $points,
            'is_win' => !empty($match['is_complete']) && !empty($match['winner_node']),
            'is_tied' => !empty($match['is_tied']),
        ];
    }

    $matchStatusClass = static function (array $match): string {
        if (!empty($match['is_tied'])) {
            return 'is-tied';
        }
        if (!empty($match['is_complete']) && !empty($match['winner_node'])) {
            return 'is-complete';
        }
        if (!empty($match['is_bye'])) {
            return 'is-bye';
        }

        return 'is-pending';
    };
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
    .se-page {
        max-width: 1500px;
        margin: 0 auto;
    }

    .se-hero {
        border: 1px solid #dbeafe;
        border-radius: 18px;
        padding: 1.15rem 1.25rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 55%, #f8fafc 100%);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
    }

    .se-eyebrow {
        font-size: .82rem;
        color: #2563eb;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .se-title {
        font-size: clamp(1.45rem, 3vw, 2.15rem);
        font-weight: 900;
        line-height: 1.15;
        margin-top: .2rem;
        color: #0f172a;
    }

    .se-sub {
        color: #475569;
        font-size: .92rem;
        margin-top: .35rem;
        line-height: 1.6;
    }

    .se-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .9rem;
    }

    .se-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .48rem .78rem;
        border-radius: .65rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        font-weight: 800;
        text-decoration: none;
        font-size: .88rem;
    }

    .se-btn:hover {
        color: #1d4ed8;
        border-color: #93c5fd;
        text-decoration: none;
    }

    .se-btn-primary {
        color: #1d4ed8;
        border-color: #60a5fa;
        background: #eff6ff;
    }

    .se-summary {
        display: grid;
        grid-template-columns: repeat(6, minmax(125px, 1fr));
        gap: .7rem;
        margin-top: 1rem;
    }

    .se-summary-box {
        border: 1px solid #e2e8f0;
        border-radius: .8rem;
        background: rgba(255,255,255,.86);
        padding: .62rem .75rem;
    }

    .se-summary-label {
        color: #64748b;
        font-size: .76rem;
        font-weight: 800;
    }

    .se-summary-value {
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 900;
        margin-top: .15rem;
    }

    .se-progress {
        height: .52rem;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        margin-top: .95rem;
    }

    .se-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #2563eb, #22c55e);
        border-radius: 999px;
    }

    .se-alert {
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #9a3412;
        border-radius: .9rem;
        padding: .9rem 1rem;
        margin-bottom: 1rem;
        font-weight: 800;
    }

    .se-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 310px;
        gap: 1rem;
        align-items: start;
    }

    .se-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: .95rem;
        box-shadow: 0 12px 24px rgba(15, 23, 42, .05);
    }

    .se-panel-title {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .8rem;
        color: #0f172a;
        font-weight: 900;
    }

    .se-panel-sub {
        color: #64748b;
        font-size: .78rem;
        font-weight: 800;
    }

    .se-bracket-stage {
        border: 1px solid #cbd5e1;
        border-radius: .85rem;
        background: #ffffff;
        overflow: hidden;
    }

    .se-bracket-head {
        padding: .75rem .9rem;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
    }

    .se-bracket-head-title {
        font-weight: 900;
        color: #0f172a;
    }

    .se-bracket-head-meta {
        font-size: .78rem;
        color: #64748b;
        font-weight: 800;
    }

    .se-bracket-scroll {
        overflow: auto;
        padding: .75rem;
        background:
            linear-gradient(90deg, rgba(248,250,252,.95), rgba(255,255,255,.95)),
            repeating-linear-gradient(0deg, #f1f5f9 0, #f1f5f9 1px, transparent 1px, transparent 34px);
    }

    .se-svg {
        display: block;
        min-width: 100%;
        background: #fff;
        border-radius: .6rem;
    }

    .se-svg-title {
        font-size: 18px;
        font-weight: 900;
        fill: #0f172a;
    }

    .se-svg-subtitle {
        font-size: 11px;
        font-weight: 800;
        fill: #475569;
    }

    .se-line {
        fill: none;
        stroke: #1f2937;
        stroke-width: 1.3;
        stroke-linecap: square;
        stroke-linejoin: miter;
    }

    .se-line-win {
        stroke: #0f172a;
        stroke-width: 4.2;
    }

    .se-line-tied {
        stroke: #f97316;
        stroke-width: 3;
        stroke-dasharray: 5 4;
    }

    .se-slot-rect {
        fill: #ffffff;
        stroke: #111827;
        stroke-width: 1.2;
    }

    .se-slot-rect.is-winner {
        stroke: #0f172a;
        stroke-width: 3.2;
        fill: #f8fafc;
    }

    .se-slot-rect.is-loser {
        fill: #f9fafb;
        stroke: #94a3b8;
    }

    .se-slot-rect.is-bye,
    .se-slot-rect.is-pending-slot {
        fill: #f1f5f9;
        stroke: #cbd5e1;
        stroke-dasharray: 4 3;
    }

    .se-svg-seed {
        font-size: 8.5px;
        font-weight: 900;
        fill: #475569;
    }

    .se-svg-name {
        font-size: 11.5px;
        font-weight: 900;
        fill: #0f172a;
    }

    .se-svg-sub {
        font-size: 7.5px;
        font-weight: 700;
        fill: #64748b;
    }

    .se-svg-score {
        font-size: 12px;
        font-weight: 900;
        fill: #334155;
    }

    .se-svg-score.is-winner {
        font-size: 13px;
        fill: #dc2626;
    }

    .se-svg-round {
        font-size: 10px;
        font-weight: 900;
        fill: #334155;
    }

    .se-svg-match-key {
        font-size: 7.5px;
        fill: #94a3b8;
        font-weight: 800;
    }

    .se-lane-badge {
        fill: #ffffff;
        stroke: #475569;
        stroke-width: .7;
    }

    .se-svg-lane {
        font-size: 8.8px;
        fill: #111827;
        font-weight: 900;
    }

    .se-input-panel {
        margin-top: 1rem;
        border-top: 1px dashed #cbd5e1;
        padding-top: 1rem;
    }

    .se-input-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: .75rem;
    }

    .se-match-card {
        border: 1px solid #e2e8f0;
        border-radius: .85rem;
        padding: .7rem;
        background: #fff;
    }

    .se-match-card.is-complete {
        border-color: #86efac;
        background: #f0fdf4;
    }

    .se-match-card.is-tied {
        border-color: #fdba74;
        background: #fff7ed;
    }

    .se-match-card.is-bye {
        opacity: .76;
    }

    .se-match-head {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        margin-bottom: .55rem;
        font-size: .85rem;
        font-weight: 900;
        color: #0f172a;
    }

    .se-match-key {
        color: #64748b;
        font-size: .75rem;
        flex: 0 0 auto;
    }

    .se-card-slot {
        border: 1px solid #e2e8f0;
        border-radius: .55rem;
        padding: .45rem .5rem;
        margin-bottom: .35rem;
        background: #ffffff;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 58px;
        gap: .4rem;
        align-items: center;
    }

    .se-card-slot.is-winner {
        border-color: #16a34a;
        border-width: 2px;
    }

    .se-card-slot.is-loser {
        opacity: .72;
    }

    .se-card-slot.is-bye,
    .se-card-slot.is-pending-slot {
        background: #f8fafc;
        color: #64748b;
    }

    .se-card-name {
        font-weight: 900;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .se-mini {
        color: #64748b;
        font-size: .74rem;
        font-weight: 700;
        line-height: 1.45;
    }

    .se-card-score {
        font-size: 1rem;
        font-weight: 900;
        text-align: right;
        color: #0f172a;
    }

    .se-card-slot.is-winner .se-card-score {
        color: #dc2626;
    }

    .se-score-form {
        margin-top: .6rem;
        padding-top: .6rem;
        border-top: 1px dashed #cbd5e1;
    }

    .se-score-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 88px;
        gap: .5rem;
        align-items: center;
        margin-bottom: .4rem;
    }

    .se-score-row label {
        margin: 0;
        font-size: .82rem;
        color: #475569;
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .se-score-row input[type="number"] {
        width: 88px;
        text-align: right;
    }

    .se-winner {
        margin-top: .5rem;
        padding: .42rem .55rem;
        border-radius: .58rem;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
        font-weight: 900;
        font-size: .9rem;
    }

    .se-tie-warning {
        margin-top: .5rem;
        padding: .42rem .55rem;
        border-radius: .58rem;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        font-weight: 900;
        font-size: .9rem;
    }

    .se-side-list {
        display: grid;
        gap: .55rem;
    }

    .se-seed-card {
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        padding: .55rem .65rem;
        background: #fff;
    }

    .se-seed-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .se-seed {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #e0f2fe;
        color: #0369a1;
        font-size: .73rem;
        font-weight: 900;
        padding: .12rem .42rem;
        flex: 0 0 auto;
    }

    .se-seed-name {
        font-weight: 900;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .se-seed-rank {
        font-size: .78rem;
        color: #64748b;
        font-weight: 800;
    }

    .se-empty {
        border: 1px dashed #cbd5e1;
        border-radius: .9rem;
        padding: 1.2rem;
        color: #64748b;
        background: #f8fafc;
        font-weight: 700;
    }

    .se-note {
        color: #475569;
        font-size: .88rem;
        margin-top: .75rem;
        line-height: 1.6;
    }

    @media (max-width: 1199.98px) {
        .se-layout {
            grid-template-columns: 1fr;
        }

        .se-summary {
            grid-template-columns: repeat(3, minmax(120px, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .se-summary {
            grid-template-columns: repeat(2, minmax(120px, 1fr));
        }
    }
</style>

<div class="se-page">
    <section class="se-hero">
        <div class="se-eyebrow">Single Elimination Live Board</div>
        <div class="se-title">{{ $tournament_name }} / トーナメント速報</div>
        <div class="se-sub">
            進出元：{{ $meta['seed_source_name'] ?? '—' }}
            ／ 進出人数：{{ number_format($qualifierCount) }}名
            ／ ブラケット：{{ number_format($bracketSize) }}枠
            ／ 方式：{{ $meta['seed_policy_name'] ?? '—' }}
            @if(!empty($meta['seed_snapshot_id']))
                ／ seed snapshot: #{{ $meta['seed_snapshot_id'] }}
                @if(!empty($meta['seed_snapshot_gender']))
                    / {{ $meta['seed_snapshot_gender'] }}
                @endif
            @endif
        </div>

        @unless($isPublic)
            <div class="se-toolbar">
                <a class="se-btn" href="{{ url('/scores/input') }}">速報入力ページへ</a>

                @if($snapshotIndexUrl)
                    <a class="se-btn" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
                @endif

                @if($tournamentId > 0)
                    <a class="se-btn" href="{{ route('tournaments.results.index', $tournamentId) }}">大会成績一覧へ</a>
                @endif

                <a class="se-btn se-btn-primary" href="{{ $publicUrl }}">共有URL（公開）</a>
            </div>
        @endunless

        <div class="se-summary">
            <div class="se-summary-box">
                <div class="se-summary-label">優勝者</div>
                <div class="se-summary-value">{{ $championName }}</div>
            </div>
            <div class="se-summary-box">
                <div class="se-summary-label">進行</div>
                <div class="se-summary-value">{{ $completedMatchCount }} / {{ $actualMatchCount }}</div>
            </div>
            <div class="se-summary-box">
                <div class="se-summary-label">未確定</div>
                <div class="se-summary-value">{{ $waitingMatchCount }}</div>
            </div>
            <div class="se-summary-box">
                <div class="se-summary-label">同点要確認</div>
                <div class="se-summary-value">{{ $tiedMatchCount }}</div>
            </div>
            <div class="se-summary-box">
                <div class="se-summary-label">BYE</div>
                <div class="se-summary-value">{{ number_format((int) ($summary['bye_count'] ?? $byeMatchCount)) }}</div>
            </div>
            <div class="se-summary-box">
                <div class="se-summary-label">表示形式</div>
                <div class="se-summary-value">{{ $layoutType === 'wide_split' ? '左右分割' : '標準' }}</div>
            </div>
        </div>

        <div class="se-progress" aria-label="進行率">
            <div class="se-progress-bar" style="width: {{ $progressPercent }}%;"></div>
        </div>
    </section>

    @if($missingSeedSnapshot)
        <div class="se-alert">
            進出元の current snapshot が見つかりません。先に「{{ $meta['seed_source_name'] ?? '進出元成績' }}」を正式成績反映ページで作成してください。
        </div>
    @endif

    <div class="se-layout">
        <section class="se-panel">
            <div class="se-panel-title">
                <span>公式PDF風トーナメント表</span>
                <span class="se-panel-sub">勝者ルートは太線、スコア入力は下部カードで実施</span>
            </div>

            @if(empty($rounds))
                <div class="se-empty">トーナメント表を表示できません。大会のトーナメント設定を確認してください。</div>
            @else
                <div class="se-bracket-stage">
                    <div class="se-bracket-head">
                        <div>
                            <div class="se-bracket-head-title">{{ $tournament_name }} 決勝トーナメント</div>
                            <div class="se-bracket-head-meta">
                                {{ $layoutType === 'wide_split' ? '48名級・64枠対応の左右分割表示' : '24名以下向けの標準表示' }}
                                ／ {{ number_format($qualifierCount) }}名進出
                                ／ {{ number_format($actualMatchCount) }}試合
                            </div>
                        </div>
                        <div class="se-bracket-head-meta">
                            未入力: {{ $waitingMatchCount }} ／ 完了: {{ $completedMatchCount }} ／ BYE: {{ $byeMatchCount }}
                        </div>
                    </div>

                    <div class="se-bracket-scroll">
                        <svg class="se-svg" width="{{ $canvasW }}" height="{{ $canvasH }}" viewBox="0 0 {{ $canvasW }} {{ $canvasH }}" role="img" aria-label="トーナメント表">
                            <text x="{{ (int) floor($canvasW / 2) }}" y="28" text-anchor="middle" class="se-svg-title">{{ $tournament_name }} 決勝トーナメント速報</text>
                            <text x="{{ (int) floor($canvasW / 2) }}" y="47" text-anchor="middle" class="se-svg-subtitle">
                                {{ number_format($qualifierCount) }}名 / {{ number_format($bracketSize) }}枠 / {{ $meta['seed_source_name'] ?? '進出元成績' }}
                            </text>

                            @foreach($matchLines as $line)
                                <polyline
                                    points="{{ $line['points'] }}"
                                    class="se-line {{ $line['is_win'] ? 'se-line-win' : '' }} {{ $line['is_tied'] ? 'se-line-tied' : '' }}"
                                />
                            @endforeach

                            @foreach($advanceLines as $line)
                                <polyline
                                    points="{{ $line['points'] }}"
                                    class="se-line {{ $line['is_win'] ? 'se-line-win' : '' }} {{ $line['is_tied'] ? 'se-line-tied' : '' }}"
                                />
                            @endforeach

                            @if($championLine)
                                <polyline
                                    points="{{ $championLine['points'] }}"
                                    class="se-line {{ $championLine['is_win'] ? 'se-line-win' : '' }} {{ $championLine['is_tied'] ? 'se-line-tied' : '' }}"
                                />
                            @endif

                            @foreach($rounds as $round)
                                @php
                                    $round = (array) $round;
                                    $matches = array_values((array) ($round['matches'] ?? []));
                                @endphp

                                @foreach($matches as $match)
                                    @php
                                        $match = (array) $match;
                                        $matchKey = (string) ($match['match_key'] ?? '');
                                        $layoutMatch = $layoutMatches[$matchKey] ?? null;
                                        if (!$layoutMatch) {
                                            continue;
                                        }
                                        $side = (string) ($layoutMatch['side'] ?? 'compact');
                                        $laneLabel = $laneLabelForMatch($match);

                                        /*
                                         * レーン表示は、ユーザー指定の赤丸位置に固定する。
                                         * 赤丸位置 = 「対戦する2本の入力線の中央」かつ「合流縦線の左側」。
                                         * つまり、上側/下側どちらかの水平線上ではなく、2本の線の間の空白に置く。
                                         */
                                        $laneBadge = null;
                                        if ($laneLabel !== '') {
                                            $laneLabelWidth = max(34, (int) (mb_strwidth($laneLabel, 'UTF-8') * 5.2) + 10);
                                            $laneBadgeHeight = 12;

                                            $side = (string) ($layoutMatch['side'] ?? 'left');
                                            $junctionX = $matchJunctionX($layoutMatch);
                                            $matchCenterY = (int) round((float) ($layoutMatch['center_y'] ?? 0));

                                            /*
                                             * 左側/標準表示：
                                             *   合流縦線の左側に、右端が縦線へ寄るように配置。
                                             *   Yは試合中心。これで選手枠にも水平線にも被らない。
                                             *
                                             * 右側表示：
                                             *   左右分割レイアウト用に反転し、合流縦線の右側へ配置。
                                             */
                                            if ($side === 'right') {
                                                $laneX = (int) round($junctionX + 6);
                                            } else {
                                                $laneX = (int) round($junctionX - $laneLabelWidth - 6);
                                            }

                                            $laneY = (int) round($matchCenterY - ($laneBadgeHeight / 2));

                                            // SVG外へはみ出さないための保険。
                                            $laneX = max(4, min((int) ($canvasW - $laneLabelWidth - 4), $laneX));
                                            $laneY = max(4, min((int) ($canvasH - $laneBadgeHeight - 4), $laneY));

                                            $laneBadge = [
                                                'x' => $laneX,
                                                'y' => $laneY,
                                                'w' => $laneLabelWidth,
                                                'h' => $laneBadgeHeight,
                                                'text_x' => (int) round($laneX + ($laneLabelWidth / 2)),
                                                'text_y' => (int) round($laneY + 9),
                                            ];
                                        }
                                    @endphp

                                    @if($laneBadge)
                                        <rect
                                            x="{{ $laneBadge['x'] }}"
                                            y="{{ $laneBadge['y'] }}"
                                            width="{{ $laneBadge['w'] }}"
                                            height="{{ $laneBadge['h'] }}"
                                            rx="2"
                                            class="se-lane-badge"
                                        />
                                        <text x="{{ $laneBadge['text_x'] }}" y="{{ $laneBadge['text_y'] }}" text-anchor="middle" class="se-svg-lane">{{ $laneLabel }}</text>
                                    @endif

                                    @foreach(['A' => (array) ($match['slot_a'] ?? []), 'B' => (array) ($match['slot_b'] ?? [])] as $slotCode => $slot)
                                        @php
                                            $slotLayout = $layoutSlots[$matchKey . ':' . $slotCode] ?? null;
                                            if (!$slotLayout) {
                                                continue;
                                            }

                                            $type = (string) ($slot['type'] ?? '');
                                            $winnerSlot = (string) ($match['winner_slot'] ?? '');
                                            $isWinner = !empty($match['is_complete']) && $winnerSlot === $slotCode;
                                            $isLoser = !empty($match['is_complete']) && $winnerSlot !== '' && $winnerSlot !== $slotCode && in_array($type, ['seed', 'advanced'], true);
                                            $isByeSlot = $type === 'bye';
                                            $isPendingSlot = $type === 'winner';
                                            $slotClass = $isWinner ? 'is-winner' : ($isLoser ? 'is-loser' : ($isByeSlot ? 'is-bye' : ($isPendingSlot ? 'is-pending-slot' : '')));
                                            $score = $slotCode === 'A' ? ($match['score_a'] ?? null) : ($match['score_b'] ?? null);
                                            $scoreClass = $isWinner ? 'is-winner' : '';
                                            $x = (int) $slotLayout['x'];
                                            $y = (int) $slotLayout['y'];
                                            $nameY = $y + 18;
                                            $subY = $y + 28;
                                            $scoreX = $x + $slotW - 6;
                                            $seedLabel = $slotSeedLabel($slot);
                                            $nameText = $textFit($slotName($slot), $layoutType === 'wide_split' ? 18 : 22);
                                            $subText = $textFit($slotSub($slot), $layoutType === 'wide_split' ? 24 : 28);
                                        @endphp

                                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $slotW }}" height="{{ $slotH }}" rx="2" class="se-slot-rect {{ $slotClass }}" />
                                        <text x="{{ $x + 6 }}" y="{{ $nameY }}" class="se-svg-name">{{ $nameText }}</text>
                                        <text x="{{ $x + 6 }}" y="{{ $subY }}" class="se-svg-sub">{{ $subText }}</text>
                                        <text x="{{ $scoreX }}" y="{{ $nameY }}" text-anchor="end" class="se-svg-score {{ $scoreClass }}">{{ $scoreText($score) }}</text>
                                    @endforeach
                                @endforeach
                            @endforeach

                            @if($championBox)
                                <rect
                                    x="{{ $championBox['x'] }}"
                                    y="{{ $championBox['y'] }}"
                                    width="{{ $championBox['w'] }}"
                                    height="{{ $championBox['h'] }}"
                                    rx="2"
                                    class="se-slot-rect {{ $championBox['is_complete'] ? 'is-winner' : 'is-pending-slot' }}"
                                />
                                <text x="{{ $championBox['x'] + 7 }}" y="{{ $championBox['y'] + 13 }}" class="se-svg-seed">優勝</text>
                                <text x="{{ $championBox['x'] + 8 }}" y="{{ $championBox['y'] + 29 }}" class="se-svg-name">{{ $textFit($championBox['name'], $layoutType === 'wide_split' ? 18 : 22) }}</text>
                            @endif
                        </svg>
                    </div>
                </div>

                <div class="se-note">
                    この図は `game_scores.stage = トーナメント`、`entry_number = SE:Rn-Mn:A/B` の保存済みスコアを読み、勝者ルートを太線で描画しています。24名以下は標準表示、48名級は左右分割表示に自動切替します。優勝者枠も同じSVG内に描画します。レーン設定が保存されている場合は、各試合の合流点付近に、線へ重ならない位置で投球レーンも表示します。PDF出力用のPNG化は次フェーズで接続します。
                </div>

                <div class="se-input-panel">
                    <div class="se-panel-title">
                        <span>試合スコア入力</span>
                        <span class="se-panel-sub">既存の保存方式は変更なし</span>
                    </div>

                    <div class="se-input-grid">
                        @foreach($rounds as $round)
                            @php
                                $round = (array) $round;
                                $matches = array_values((array) ($round['matches'] ?? []));
                            @endphp

                            @foreach($matches as $match)
                                @php
                                    $match = (array) $match;
                                    $matchKey = (string) ($match['match_key'] ?? '');
                                    $slotA = (array) ($match['slot_a'] ?? []);
                                    $slotB = (array) ($match['slot_b'] ?? []);
                                    $scoreA = $match['score_a'] ?? null;
                                    $scoreB = $match['score_b'] ?? null;
                                    $slotAType = (string) ($slotA['type'] ?? '');
                                    $slotBType = (string) ($slotB['type'] ?? '');
                                    $canInputMatch = !$isPublic
                                        && !$missingSeedSnapshot
                                        && $isPlayableSlot($slotA)
                                        && $isPlayableSlot($slotB)
                                        && $slotAType !== 'bye'
                                        && $slotBType !== 'bye';
                                    $winnerNode = (array) ($match['winner_node'] ?? []);
                                    $cardClass = $matchStatusClass($match);
                                @endphp

                                <div class="se-match-card {{ $cardClass }}">
                                    <div class="se-match-head">
                                        <span>{{ $match['label'] ?? '—' }}</span>
                                        <span class="se-match-key">{{ $matchKey }}</span>
                                    </div>

                                    @foreach(['A' => $slotA, 'B' => $slotB] as $slotCode => $slot)
                                        @php
                                            $type = (string) ($slot['type'] ?? '');
                                            $winnerSlot = (string) ($match['winner_slot'] ?? '');
                                            $isWinner = !empty($match['is_complete']) && $winnerSlot === $slotCode;
                                            $isLoser = !empty($match['is_complete']) && $winnerSlot !== '' && $winnerSlot !== $slotCode && in_array($type, ['seed', 'advanced'], true);
                                            $slotClass = $isWinner ? 'is-winner' : ($isLoser ? 'is-loser' : ($type === 'bye' ? 'is-bye' : ($type === 'winner' ? 'is-pending-slot' : '')));
                                            $score = $slotCode === 'A' ? $scoreA : $scoreB;
                                        @endphp

                                        <div class="se-card-slot {{ $slotClass }}">
                                            <div>
                                                <div class="se-card-name">
                                                    @if($slotSeedLabel($slot) !== '')
                                                        <span class="se-seed">{{ $slotSeedLabel($slot) }}</span>
                                                    @endif
                                                    {{ $slotName($slot) }}
                                                </div>
                                                <div class="se-mini">
                                                    {{ $slotStatusText($slot, $match, $slotCode) }}
                                                    @if($slotSub($slot) !== '')
                                                        ／ {{ $slotSub($slot) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="se-card-score">{{ $scoreText($score) }}</div>
                                        </div>
                                    @endforeach

                                    @if($canInputMatch)
                                        <form method="POST" action="{{ route('scores.single_elimination.store') }}" class="se-score-form">
                                            @csrf
                                            <input type="hidden" name="tournament_id" value="{{ $meta['tournament_id'] ?? request('tournament_id') }}">
                                            <input type="hidden" name="round_no" value="{{ $match['round_no'] ?? $round['round_no'] ?? 1 }}">
                                            <input type="hidden" name="match_no" value="{{ $match['match_no'] ?? 1 }}">
                                            <input type="hidden" name="match_key" value="{{ $matchKey }}">
                                            <input type="hidden" name="upto_game" value="{{ $currentUptoGame }}">
                                            <input type="hidden" name="shifts" value="{{ $shiftValue }}">
                                            <input type="hidden" name="gender_filter" value="{{ $genderValue }}">

                                            @foreach(['A' => $slotA, 'B' => $slotB] as $slotCode => $slot)
                                                @foreach($hiddenSlotFields as $field)
                                                    @if(array_key_exists($field, $slot) && !is_array($slot[$field]))
                                                        <input type="hidden" name="slots[{{ $slotCode }}][{{ $field }}]" value="{{ $slot[$field] }}">
                                                    @endif
                                                @endforeach
                                            @endforeach

                                            <div class="se-score-row">
                                                <label>A：{{ $slotName($slotA) }}</label>
                                                <input type="number" name="scores[A]" class="form-control form-control-sm" min="0" max="300" value="{{ $scoreA !== null ? $scoreA : '' }}">
                                            </div>

                                            <div class="se-score-row">
                                                <label>B：{{ $slotName($slotB) }}</label>
                                                <input type="number" name="scores[B]" class="form-control form-control-sm" min="0" max="300" value="{{ $scoreB !== null ? $scoreB : '' }}">
                                            </div>

                                            <button type="submit" class="btn btn-sm btn-primary">この試合を保存</button>
                                        </form>
                                    @else
                                        <div class="se-mini mt-2">
                                            @if($slotAType === 'winner' || $slotBType === 'winner')
                                                前ラウンドの勝者確定後に入力できます。
                                            @elseif($slotAType === 'bye' || $slotBType === 'bye')
                                                BYEにより自動進出します。
                                            @elseif($isPublic)
                                                公開表示中です。
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($match['is_tied']))
                                        <div class="se-tie-warning">
                                            同点です。勝者を確定できません。タイブレーク用のスコアに修正してください。
                                        </div>
                                    @elseif(!empty($match['winner_node']))
                                        <div class="se-winner">
                                            勝者：{{ $winnerNode['display_name'] ?? $winnerNode['label'] ?? '—' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <aside class="se-panel">
            <div class="se-panel-title">
                <span>進出者seed</span>
                <span class="se-panel-sub">{{ number_format(count($seedRows)) }}名</span>
            </div>

            @if(empty($seedRows))
                <div class="se-empty">
                    seed一覧がありません。進出元成績のsnapshotを作成してください。
                </div>
            @else
                <div class="se-side-list">
                    @foreach($seedRows as $row)
                        <div class="se-seed-card">
                            <div class="se-seed-top">
                                <span class="se-seed">{{ $row['seed'] ?? '—' }}位</span>
                                <span class="se-seed-name">{{ $row['display_name'] ?? '—' }}</span>
                            </div>
                            <div class="se-mini">
                                {{ $formatLicenseShort($row['pro_bowler_license_no'] ?? '') ?: '—' }}
                                @if(isset($row['source_ranking']) && $row['source_ranking'] !== null)
                                    ／ 元順位 {{ (int) $row['source_ranking'] }}位
                                @endif
                            </div>
                            <div class="se-seed-rank">
                                @if(isset($row['total_pin']) && $row['total_pin'] !== null)
                                    通算 {{ number_format((int) $row['total_pin']) }} pin
                                @else
                                    通算 —
                                @endif
                                @if(isset($row['games']) && $row['games'] !== null)
                                    ／ {{ (int) $row['games'] }}G
                                @endif
                                @if(isset($row['average']) && $row['average'] !== null)
                                    ／ AVG {{ number_format((float) $row['average'], 2) }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
