<?php

namespace App\Services;

use App\Models\Tournament;
use RuntimeException;

class SingleEliminationBracketImageService
{
    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = $this->resolveFontPath();
    }

    public function generateDataUri(Tournament $tournament, array $singleEliminationPdf): ?string
    {
        $pngBinary = $this->generatePngBinary($tournament, $singleEliminationPdf);

        if ($pngBinary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($pngBinary);
    }

    private function generatePngBinary(Tournament $tournament, array $singleEliminationPdf): string
    {
        if (!extension_loaded('gd') || !function_exists('imagepng') || !function_exists('imagettftext')) {
            throw new RuntimeException('GD拡張、imagepng、imagettftext が有効ではありません。');
        }

        $bracket = (array) ($singleEliminationPdf['bracket'] ?? []);
        $rounds = array_values((array) ($bracket['rounds'] ?? []));
        if (empty($rounds)) {
            return '';
        }

        $summary = (array) ($bracket['summary'] ?? []);
        $qualifierCount = (int) ($summary['qualifier_count'] ?? $bracket['qualifier_count'] ?? 0);
        $bracketSize = (int) ($summary['bracket_size'] ?? $bracket['bracket_size'] ?? 0);
        $roundCount = max(1, (int) ($summary['round_count'] ?? $bracket['round_count'] ?? count($rounds)));
        $layoutType = ($qualifierCount > 24 || $bracketSize > 32) ? 'wide_split' : 'compact';

        $slotW = $layoutType === 'wide_split' ? 178 : 218;
        $slotH = $layoutType === 'wide_split' ? 40 : 44;
        $slotGap = $layoutType === 'wide_split' ? 19 : 23;
        $leafStep = $slotH + $slotGap;
        $joinGap = $layoutType === 'wide_split' ? 27 : 32;
        $roundGap = $layoutType === 'wide_split' ? 150 : 176;
        $outerX = 58;
        $topY = 142;
        $bottomPad = 86;
        $colStride = $slotW + $roundGap;

        $matchesByKey = [];
        $targetSlotBySource = [];
        $finalMatchKey = '';
        $finalMatch = null;

        foreach ($rounds as $round) {
            $round = (array) $round;
            foreach (array_values((array) ($round['matches'] ?? [])) as $match) {
                $match = (array) $match;
                $matchKey = (string) ($match['match_key'] ?? '');
                if ($matchKey === '') {
                    continue;
                }

                $matchesByKey[$matchKey] = $match;
                if ((int) ($match['round_no'] ?? 0) === $roundCount) {
                    $finalMatchKey = $matchKey;
                    $finalMatch = $match;
                }

                foreach (['A', 'B'] as $slotCode) {
                    $slot = (array) ($match['slot_' . strtolower($slotCode)] ?? []);
                    $source = $this->sourceMatchKeyForSlot($slot);
                    if ($source !== '') {
                        $targetSlotBySource[$source] = [
                            'match_key' => $matchKey,
                            'slot_code' => $slotCode,
                        ];
                    }
                }
            }
        }

        if ($finalMatchKey === '' && !empty($matchesByKey)) {
            $finalMatchKey = array_key_last($matchesByKey);
            $finalMatch = $matchesByKey[$finalMatchKey];
        }

        if ($layoutType === 'wide_split') {
            $sideRoundCount = max(1, $roundCount - 1);
            $centerX = $outerX + ($sideRoundCount * $colStride) + 230;
            $finalX = $centerX - (int) floor($slotW / 2);
            $rightOuterX = $centerX + 230 + (($sideRoundCount - 1) * $colStride);
            $canvasW = $rightOuterX + $slotW + $outerX;
        } else {
            $centerX = null;
            $finalX = null;
            $rightOuterX = null;
            $canvasW = $outerX + ($roundCount * $colStride) + $slotW + 260;
        }

        $layoutMatches = [];
        $layoutSlots = [];
        $leafCounters = [
            'compact' => 0,
            'left' => 0,
            'right' => 0,
            'center' => 0,
        ];

        $getMatchX = function (int $roundNo, string $side) use ($layoutType, $outerX, $colStride, $rightOuterX, $finalX): int {
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

        $getLeafSlotX = function (string $side) use ($layoutType, $outerX, $rightOuterX): int {
            if ($layoutType === 'wide_split' && $side === 'right') {
                return (int) $rightOuterX;
            }

            return (int) $outerX;
        };

        $assignLeafY = function (string $side) use (&$leafCounters, $layoutType, $topY, $leafStep): int {
            $counterKey = $layoutType === 'wide_split'
                ? (in_array($side, ['left', 'right'], true) ? $side : 'left')
                : 'compact';

            $index = (int) ($leafCounters[$counterKey] ?? 0);
            $leafCounters[$counterKey] = $index + 1;

            return (int) round($topY + ($index * $leafStep));
        };

        $layoutMatchRecursive = null;
        $layoutMatchRecursive = function (string $matchKey, string $branchSide = 'left') use (
            &$layoutMatchRecursive,
            &$layoutMatches,
            &$layoutSlots,
            $matchesByKey,
            $roundCount,
            $layoutType,
            $assignLeafY,
            $getMatchX,
            $getLeafSlotX,
            $slotW,
            $slotH,
            $leafStep,
            $finalMatchKey
        ): int {
            if (isset($layoutMatches[$matchKey])) {
                return (int) $layoutMatches[$matchKey]['center_y'];
            }

            $match = (array) ($matchesByKey[$matchKey] ?? []);
            if (empty($match)) {
                return $assignLeafY($branchSide);
            }

            $roundNo = max(1, (int) ($match['round_no'] ?? 1));
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
                $sourceKey = $this->sourceMatchKeyForSlot($slot);
                $slotSourceKeys[$slotCode] = $sourceKey;

                if ($sourceKey !== '' && isset($matchesByKey[$sourceKey])) {
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

            if (abs((int) $slotCenters['A'] - (int) $slotCenters['B']) < ($slotH + 10)) {
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
                $slot = $slotCode === 'A' ? (array) ($match['slot_a'] ?? []) : (array) ($match['slot_b'] ?? []);
                $slotType = (string) ($slot['type'] ?? '');
                $hasSourceMatch = ($slotSourceKeys[$slotCode] ?? '') !== '';
                $slotX = $x;

                if (!$hasSourceMatch && $slotType === 'seed' && $roundNo > 1 && $side !== 'center') {
                    $slotX = $getLeafSlotX($side);
                }

                $cy = (int) ($slotCenters[$slotCode] ?? $centerY);
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

        foreach (array_keys($matchesByKey) as $key) {
            if (!isset($layoutMatches[$key])) {
                $layoutMatchRecursive($key, 'left');
            }
        }

        $maxY = $topY;
        foreach ($layoutSlots as $slotLayout) {
            $maxY = max($maxY, (int) $slotLayout['y'] + (int) $slotLayout['h']);
        }

        $championBox = null;
        if ($finalMatchKey !== '' && isset($layoutMatches[$finalMatchKey])) {
            $finalLayout = $layoutMatches[$finalMatchKey];
            $junctionX = $this->matchJunctionX($finalLayout, $slotW, $joinGap);
            $championW = $slotW + 12;
            $championH = $slotH;
            $championX = $layoutType === 'wide_split'
                ? ((int) $junctionX + 95)
                : ((int) $junctionX + 120);

            $championBox = [
                'x' => $championX,
                'y' => (int) round((int) $finalLayout['center_y'] - ($championH / 2)),
                'w' => $championW,
                'h' => $championH,
                'center_y' => (int) $finalLayout['center_y'],
            ];
            $canvasW = max($canvasW, $championX + $championW + 70);
        }

        $canvasH = max(720, $maxY + $bottomPad);
        $image = imagecreatetruecolor($canvasW, $canvasH);
        if (!$image) {
            throw new RuntimeException('トーナメント表画像キャンバスを作成できませんでした。');
        }

        imageantialias($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);
        $gray = imagecolorallocate($image, 80, 92, 112);
        $light = imagecolorallocate($image, 248, 250, 252);
        $border = imagecolorallocate($image, 148, 163, 184);
        $line = imagecolorallocate($image, 71, 85, 105);
        $winLine = imagecolorallocate($image, 15, 23, 42);
        $winnerBg = imagecolorallocate($image, 236, 253, 245);
        $red = imagecolorallocate($image, 185, 28, 28);
        $laneBg = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $canvasW, $canvasH, $white);

        $tournamentName = trim((string) ($tournament->name ?? '')) ?: '大会';
        $seedSourceName = trim((string) ($singleEliminationPdf['meta']['seed_source_name'] ?? '進出成績'));
        $subtitle = sprintf('%d名 / %d枠 / %s', $qualifierCount, $bracketSize, $seedSourceName);

        $this->drawCenteredText($image, $tournamentName . ' 決勝トーナメント表', 0, 24, $canvasW, 34, 26, $black, true);
        $this->drawCenteredText($image, $subtitle, 0, 62, $canvasW, 22, 14, $gray, false);

        foreach ($rounds as $round) {
            $round = (array) $round;
            $roundNo = (int) ($round['round_no'] ?? 0);
            if ($roundNo <= 0) {
                continue;
            }
            foreach (['left', 'right', 'center'] as $side) {
                if ($layoutType !== 'wide_split' && $side !== 'left') {
                    continue;
                }
                if ($layoutType === 'wide_split' && $roundNo >= $roundCount && $side !== 'center') {
                    continue;
                }
                if ($layoutType === 'wide_split' && $roundNo < $roundCount && $side === 'center') {
                    continue;
                }
                $x = $getMatchX($roundNo, $side);
                $this->drawCenteredText($image, (string) ($round['round_name'] ?? ($roundNo . '回戦')), $x, 102, $slotW, 18, 12, $gray, true);
                if ($layoutType !== 'wide_split') {
                    break;
                }
            }
        }

        $drawPolyline = function (array $points, bool $bold = false) use ($image, $line, $winLine): void {
            imagesetthickness($image, $bold ? 6 : 2);
            $color = $bold ? $winLine : $line;
            for ($i = 0; $i < count($points) - 1; $i++) {
                imageline($image, $points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $color);
            }
            imagesetthickness($image, 1);
        };

        foreach ($matchesByKey as $matchKey => $match) {
            $match = (array) $match;
            $layout = $layoutMatches[$matchKey] ?? null;
            if (!is_array($layout)) {
                continue;
            }

            $junctionX = $this->matchJunctionX($layout, $slotW, $joinGap);
            $centerY = (int) $layout['center_y'];
            $slotYs = [];

            foreach (['A', 'B'] as $slotCode) {
                $slotLayout = $layoutSlots[$matchKey . ':' . $slotCode] ?? null;
                if (!is_array($slotLayout)) {
                    continue;
                }
                $side = (string) ($slotLayout['side'] ?? 'left');
                $slotYs[$slotCode] = (int) $slotLayout['center_y'];
                $fromX = $side === 'right' ? (int) $slotLayout['x'] : (int) $slotLayout['x'] + $slotW;
                $isWinnerBranch = !empty($match['is_complete']) && (string) ($match['winner_slot'] ?? '') === $slotCode;
                $drawPolyline([[$fromX, (int) $slotLayout['center_y']], [$junctionX, (int) $slotLayout['center_y']]], $isWinnerBranch);
            }

            if (isset($slotYs['A'], $slotYs['B'])) {
                $drawPolyline([[$junctionX, min($slotYs['A'], $slotYs['B'])], [$junctionX, max($slotYs['A'], $slotYs['B'])]], !empty($match['is_complete']) && !empty($match['winner_node']));
            }

            $target = $targetSlotBySource[$matchKey] ?? null;
            if (is_array($target)) {
                $targetKey = (string) ($target['match_key'] ?? '');
                $targetSlotCode = (string) ($target['slot_code'] ?? '');
                $targetSlot = $layoutSlots[$targetKey . ':' . $targetSlotCode] ?? null;
                if (is_array($targetSlot)) {
                    $targetSide = (string) ($targetSlot['side'] ?? 'left');
                    $targetX = $targetSide === 'right' ? ((int) $targetSlot['x'] + $slotW) : (int) $targetSlot['x'];
                    $targetY = (int) $targetSlot['center_y'];
                    $midX = (int) round(($junctionX + $targetX) / 2);
                    $drawPolyline([[$junctionX, $centerY], [$midX, $centerY], [$midX, $targetY], [$targetX, $targetY]], !empty($match['is_complete']) && !empty($match['winner_node']));
                }
            } elseif ($championBox && $matchKey === $finalMatchKey) {
                $targetX = (int) $championBox['x'];
                $targetY = (int) $championBox['center_y'];
                $drawPolyline([[$junctionX, $centerY], [$targetX, $targetY]], !empty($match['is_complete']) && !empty($match['winner_node']));
            }
        }

        foreach ($matchesByKey as $matchKey => $match) {
            $match = (array) $match;
            $layout = $layoutMatches[$matchKey] ?? null;
            if (!is_array($layout)) {
                continue;
            }

            $laneLabel = $this->laneLabelForMatch($match, (array) ($singleEliminationPdf['lane_settings'] ?? []));
            if ($laneLabel === '') {
                continue;
            }

            $junctionX = $this->matchJunctionX($layout, $slotW, $joinGap);
            $textW = max(48, $this->textWidth($laneLabel, 10) + 14);
            $textH = 18;
            $side = (string) ($layout['side'] ?? 'left');
            $x = $side === 'right' ? $junctionX + 8 : $junctionX - $textW - 8;
            $y = (int) $layout['center_y'] - (int) round($textH / 2);
            $x = max(4, min($canvasW - $textW - 4, $x));
            $y = max(4, min($canvasH - $textH - 4, $y));

            imagefilledrectangle($image, $x, $y, $x + $textW, $y + $textH, $laneBg);
            imagerectangle($image, $x, $y, $x + $textW, $y + $textH, $border);
            $this->drawCenteredText($image, $laneLabel, $x, $y, $textW, $textH, 9, $gray, true);
        }

        foreach ($matchesByKey as $matchKey => $match) {
            $match = (array) $match;
            foreach (['A', 'B'] as $slotCode) {
                $slot = (array) ($match['slot_' . strtolower($slotCode)] ?? []);
                $slotLayout = $layoutSlots[$matchKey . ':' . $slotCode] ?? null;
                if (!is_array($slotLayout)) {
                    continue;
                }

                $winnerSlot = (string) ($match['winner_slot'] ?? '');
                $isWinner = !empty($match['is_complete']) && $winnerSlot === $slotCode;
                $isLoser = !empty($match['is_complete']) && $winnerSlot !== '' && $winnerSlot !== $slotCode && in_array((string) ($slot['type'] ?? ''), ['seed', 'advanced'], true);
                $isBye = (string) ($slot['type'] ?? '') === 'bye';

                $x = (int) $slotLayout['x'];
                $y = (int) $slotLayout['y'];
                $w = (int) $slotLayout['w'];
                $h = (int) $slotLayout['h'];
                $fill = $isWinner ? $winnerBg : $light;
                $outline = $isWinner ? $winLine : $border;

                imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $fill);
                imagesetthickness($image, $isWinner ? 4 : 2);
                imagerectangle($image, $x, $y, $x + $w, $y + $h, $outline);
                imagesetthickness($image, 1);

                if ($isBye) {
                    $this->drawCenteredText($image, 'BYE', $x, $y, $w, $h, 12, $gray, true);
                    continue;
                }

                $name = $this->slotName($slot);
                $sub = $this->slotSub($slot);
                $score = $slotCode === 'A' ? ($match['score_a'] ?? null) : ($match['score_b'] ?? null);
                $scoreText = ($score === null || $score === '') ? '' : (string) (int) $score;
                $scoreW = $scoreText !== '' ? 44 : 0;

                if ($isLoser) {
                    $name = ' ' . $name;
                }

                $this->drawTextFit($image, $name, $x + 7, $y + 4, $w - $scoreW - 16, 17, 11, $black, $isWinner);
                if ($sub !== '') {
                    $this->drawTextFit($image, $sub, $x + 7, $y + 23, $w - 13, 13, 8, $gray, false);
                }
                if ($scoreText !== '') {
                    $this->drawRightText($image, $scoreText, $x + $w - 48, $y + 5, 42, 18, 13, $isWinner ? $red : $black, true);
                }
            }
        }

        if ($championBox) {
            $championName = trim((string) ($singleEliminationPdf['summary']['winner_name'] ?? ''));
            if ($championName === '' && is_array($finalMatch)) {
                $winnerNode = (array) ($finalMatch['winner_node'] ?? []);
                $championName = trim((string) ($winnerNode['display_name'] ?? $winnerNode['label'] ?? ''));
            }
            if ($championName === '') {
                $championName = '未確定';
            }

            $x = (int) $championBox['x'];
            $y = (int) $championBox['y'];
            $w = (int) $championBox['w'];
            $h = (int) $championBox['h'];
            imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $winnerBg);
            imagesetthickness($image, 4);
            imagerectangle($image, $x, $y, $x + $w, $y + $h, $winLine);
            imagesetthickness($image, 1);
            $this->drawText($image, '優勝', $x + 8, $y + 16, 11, $black, true);
            $this->drawTextFit($image, $championName, $x + 44, $y + 8, $w - 52, 24, 14, $black, true);
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return is_string($binary) ? $binary : '';
    }

    private function sourceMatchKeyForSlot(array $slot): string
    {
        return trim((string) ($slot['source_match_key'] ?? $slot['advanced_from_match_key'] ?? ''));
    }

    private function matchJunctionX(array $matchLayout, int $slotW, int $joinGap): int
    {
        $x = (int) ($matchLayout['x'] ?? 0);
        $side = (string) ($matchLayout['side'] ?? 'left');

        if ($side === 'right') {
            return $x - $joinGap;
        }

        return $x + $slotW + $joinGap;
    }

    private function laneLabelForMatch(array $match, array $laneSettings): string
    {
        if (empty($laneSettings)) {
            return '';
        }

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

        $matchKey = trim((string) ($match['match_key'] ?? ''));
        $roundNo = (int) ($match['round_no'] ?? 0);
        $matchNo = (int) ($match['match_no'] ?? 0);

        if (($roundNo <= 0 || $matchNo <= 0) && preg_match('/^R(\d+)-M(\d+)$/i', $matchKey, $m)) {
            $roundNo = (int) $m[1];
            $matchNo = (int) $m[2];
        }

        $explicit = $laneSettings['matches'] ?? $laneSettings['match_lanes'] ?? [];
        if (is_array($explicit) && $matchKey !== '' && isset($explicit[$matchKey])) {
            $value = $explicit[$matchKey];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value)) {
                return $this->buildLaneLabelFromSetting($value, 1);
            }
        }

        $rounds = $laneSettings['rounds'] ?? $laneSettings['round_start_lanes'] ?? [];
        if (!is_array($rounds) || $roundNo <= 0 || $matchNo <= 0) {
            return '';
        }

        $setting = $rounds[$roundNo] ?? $rounds[(string) $roundNo] ?? null;
        if (!is_array($setting)) {
            return '';
        }

        return $this->buildLaneLabelFromSetting($setting, $matchNo);
    }

    private function buildLaneLabelFromSetting(array $setting, int $matchNo): string
    {
        $start = (int) ($setting['start_lane'] ?? $setting['from'] ?? $setting['start'] ?? 0);
        if ($start <= 0) {
            return '';
        }

        $step = (int) ($setting['step'] ?? 2);
        $width = (int) ($setting['width'] ?? 2);
        $from = $start + ((max(1, $matchNo) - 1) * max(1, $step));
        $to = $from + max(1, $width) - 1;

        return $from === $to ? ($from . 'L') : ($from . '-' . $to . 'L');
    }

    private function slotName(array $slot): string
    {
        if (($slot['type'] ?? '') === 'bye') {
            return 'BYE';
        }

        $name = trim((string) ($slot['display_name'] ?? $slot['label'] ?? ''));
        return $name !== '' ? $this->formatPersonName($name) : '—';
    }

    private function slotSub(array $slot): string
    {
        if (($slot['type'] ?? '') === 'bye') {
            return '';
        }

        $parts = [];
        if (isset($slot['seed']) && $slot['seed'] !== null && $slot['seed'] !== '') {
            $parts[] = (int) $slot['seed'] . '位通過';
        }

        $license = trim((string) ($slot['pro_bowler_license_no'] ?? ''));
        if ($license !== '') {
            $short = mb_strlen($license, 'UTF-8') > 4 ? mb_substr($license, -4, null, 'UTF-8') : $license;
            $parts[] = 'Lic.' . preg_replace('/\s+/u', '', $short);
        }

        return implode(' / ', $parts);
    }

    private function formatPersonName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (preg_match('/[\s　]+/u', $name)) {
            $parts = preg_split('/[\s　]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
            return !empty($parts) ? implode('　', $parts) : $name;
        }

        $compact = preg_replace('/\s+/u', '', $name) ?? $name;
        $length = mb_strlen($compact, 'UTF-8');
        if ($length <= 2) {
            return $compact;
        }
        $splitAt = $length >= 5 ? 3 : 2;
        return mb_substr($compact, 0, $splitAt, 'UTF-8') . '　' . mb_substr($compact, $splitAt, null, 'UTF-8');
    }

    private function drawTextFit($image, string $text, int $x, int $y, int $w, int $h, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            return;
        }

        $fontSize = $size;
        while ($fontSize >= 7) {
            $box = @imagettfbbox($fontSize, 0, $this->fontPath, $text);
            $textW = $box !== false ? abs($box[2] - $box[0]) : strlen($text) * $fontSize;
            if ($textW <= $w) {
                break;
            }
            $fontSize--;
        }

        $this->drawText($image, $text, $x, $y + max(12, $h - 3), $fontSize, $color, $bold);
    }

    private function drawCenteredText($image, string $text, int $x, int $y, int $w, int $h, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            return;
        }

        $box = @imagettfbbox($size, 0, $this->fontPath, $text);
        if ($box === false) {
            imagestring($image, 5, $x + 2, $y + 2, $text, $color);
            return;
        }

        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);
        $tx = (int) round($x + (($w - $textW) / 2));
        $ty = (int) round($y + (($h + $textH) / 2) - 2);
        $this->drawText($image, $text, $tx, $ty, $size, $color, $bold);
    }

    private function drawRightText($image, string $text, int $x, int $y, int $w, int $h, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            return;
        }

        $box = @imagettfbbox($size, 0, $this->fontPath, $text);
        if ($box === false) {
            imagestring($image, 5, $x + 2, $y + 2, $text, $color);
            return;
        }

        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);
        $tx = (int) round($x + $w - $textW - 2);
        $ty = (int) round($y + (($h + $textH) / 2) - 2);
        $this->drawText($image, $text, $tx, $ty, $size, $color, $bold);
    }

    private function drawText($image, string $text, int $x, int $baselineY, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);
        if ($text === '') {
            return;
        }

        imagettftext($image, $size, 0, $x, $baselineY, $color, $this->fontPath, $text);
        if ($bold) {
            imagettftext($image, $size, 0, $x + 1, $baselineY, $color, $this->fontPath, $text);
        }
    }

    private function textWidth(string $text, int $size): int
    {
        $text = $this->normalizeText($text);
        $box = @imagettfbbox($size, 0, $this->fontPath, $text);
        if ($box === false) {
            return strlen($text) * $size;
        }

        return abs($box[2] - $box[0]);
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            return mb_convert_encoding($text, 'UTF-8', 'SJIS-win,CP932,EUC-JP,JIS,UTF-8');
        }

        return $text;
    }

    private function resolveFontPath(): string
    {
        $candidates = [
            storage_path('fonts/ipaexg.ttf'),
            base_path('storage/fonts/ipaexg.ttf'),
            public_path('fonts/ipaexg.ttf'),
            'C:/PHP/fonts/ipaexg.ttf',
            'C:/Windows/Fonts/meiryo.ttc',
            'C:/Windows/Fonts/msgothic.ttc',
        ];

        foreach ($candidates as $candidate) {
            $path = str_replace('\\', '/', (string) $candidate);
            if (!is_file($path)) {
                continue;
            }

            if (@imagettfbbox(12, 0, $path, '日本語') !== false) {
                return $path;
            }
        }

        return str_replace('\\', '/', storage_path('fonts/ipaexg.ttf'));
    }
}
