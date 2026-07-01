<?php

namespace App\Services;

use App\Models\TournamentMatchScoreSheet;
use Illuminate\Support\Collection;

class MatchScoreSheetImageService
{
    private string $font;

    public function __construct()
    {
        $this->font = $this->resolveFontPath();
    }

    /**
     * @param \Illuminate\Support\Collection<int, TournamentMatchScoreSheet> $scoreSheets
     * @return array<int,array{sheet_id:int,match_label:string,game_number:int,lane_label:string,player_count:int,image:string}>
     */
    public function generateDataUris(Collection $scoreSheets): array
    {
        $images = [];

        foreach ($scoreSheets as $scoreSheet) {
            $image = $this->generateDataUri($scoreSheet);

            if ($image === null) {
                continue;
            }

            $images[] = [
                'sheet_id' => (int) $scoreSheet->id,
                'match_label' => (string) ($scoreSheet->match_label ?: $scoreSheet->match_code ?: 'スコア表'),
                'game_number' => (int) ($scoreSheet->game_number ?: 1),
                'lane_label' => (string) ($scoreSheet->lane_label ?: ''),
                'player_count' => (int) ($scoreSheet->players instanceof Collection ? $scoreSheet->players->count() : 0),
                'image' => $image,
            ];
        }

        return $images;
    }

    public function generateDataUri(TournamentMatchScoreSheet $scoreSheet): ?string
    {
        $scoreSheet->loadMissing(['players.frames']);
        $players = $scoreSheet->players instanceof Collection ? $scoreSheet->players : collect($scoreSheet->players ?? []);

        if ($players->isEmpty()) {
            return null;
        }

        $width = 1420;
        $top = 6;
        // 選手名・スコア表・残ピン表示が次の選手名へ被らないよう、
        // 1人分の縦幅を公式PDF寄せの範囲で少し広げる。
        $blockHeight = 150;
        $height = $top + ($players->count() * $blockHeight) + 24;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $dark = imagecolorallocate($image, 30, 30, 30);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $tableLeft = 210;
        $frameW = 102;
        $tenthW = 206;
        $tableWidth = ($frameW * 9) + $tenthW;
        $headerH = 22;
        $markH = 36;
        $totalH = 28;
        $tableH = $headerH + $markH + $totalH;
        $playerCount = $players->count();

        foreach ($players->values() as $index => $player) {
            $blockTop = $top + ($index * $blockHeight);
            $tableTop = $blockTop + 42;
            $frames = $player->frames instanceof Collection
                ? $player->frames->keyBy('frame_no')
                : collect($player->frames ?? [])->keyBy('frame_no');

            $calculatedCumulativeScores = $this->calculateCumulativeScoresForPdf($frames);

            $name = trim((string) ($player->display_name ?? ''));
            $arm = $this->formatArm((string) ($player->dominant_arm ?? ''));
            $winner = !empty($player->is_winner) ? ' / 勝者' : '';
            $title = $name . ($arm !== '' ? '（' . $arm . '投げ）' : '') . $winner;

            $this->drawCenteredText($image, $title, $tableLeft, $blockTop + 0, $tableWidth, 34, 21, $black, true);

            $laneLabel = $this->resolveLaneLabelForPlayer($player, $scoreSheet, $index, $playerCount);
            $this->drawRightText($image, $laneLabel, 102, $tableTop + 40, 88, 28, 18, $black, false);

            $this->drawScoreTableFrame($image, $tableLeft, $tableTop, $frameW, $tenthW, $headerH, $markH, $totalH, $black);

            $x = $tableLeft;
            for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
                $w = $frameNo === 10 ? $tenthW : $frameW;

                $this->drawCenteredText($image, (string) $frameNo, $x, $tableTop + 0, $w, $headerH, 17, $black, true);

                $frame = $frames->get($frameNo);
                $marks = $this->marksForFrame($frame, $frameNo);
                $markTop = $tableTop + $headerH;

                if ($frameNo < 10) {
                    $half = (int) floor($w / 2);
                    $this->drawMarkCell($image, $marks[0] ?? '', $x, $markTop, $half, $markH, $black);
                    $this->drawMarkCell($image, $marks[1] ?? '', $x + $half, $markTop, $w - $half, $markH, $black);
                } else {
                    $third = (int) floor($w / 3);
                    $this->drawMarkCell($image, $marks[0] ?? '', $x, $markTop, $third, $markH, $black);
                    $this->drawMarkCell($image, $marks[1] ?? '', $x + $third, $markTop, $third, $markH, $black);
                    $this->drawMarkCell($image, $marks[2] ?? '', $x + ($third * 2), $markTop, $w - ($third * 2), $markH, $black);
                }

                $total = $calculatedCumulativeScores[$frameNo] ?? null;
                $this->drawCenteredText(
                    $image,
                    $total !== null ? (string) $total : '',
                    $x,
                    $tableTop + $headerH + $markH,
                    $w,
                    $totalH,
                    18,
                    $black,
                    false
                );

                $remainingPinsLabel = $this->remainingPinsLabel($frame?->remaining_pins ?? null);
                if ($remainingPinsLabel !== '') {
                    $this->drawCenteredText($image, $remainingPinsLabel, $x, $tableTop + $tableH + 7, $w, 18, 10, $dark, true);
                }

                $x += $w;
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        if (!is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    private function drawScoreTableFrame($image, int $left, int $top, int $frameW, int $tenthW, int $headerH, int $markH, int $totalH, int $color): void
    {
        $height = $headerH + $markH + $totalH;
        $width = ($frameW * 9) + $tenthW;

        imagesetthickness($image, 2);
        imagerectangle($image, $left, $top, $left + $width, $top + $height, $color);

        imagesetthickness($image, 1);
        imageline($image, $left, $top + $headerH, $left + $width, $top + $headerH, $color);
        imageline($image, $left, $top + $headerH + $markH, $left + $width, $top + $headerH + $markH, $color);

        $x = $left;
        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $w = $frameNo === 10 ? $tenthW : $frameW;

            if ($frameNo > 1) {
                imageline($image, $x, $top, $x, $top + $height, $color);
            }

            $markTop = $top + $headerH;
            if ($frameNo < 10) {
                $half = (int) floor($w / 2);
                imageline($image, $x + $half, $markTop, $x + $half, $markTop + $markH, $color);
            } else {
                $third = (int) floor($w / 3);
                imageline($image, $x + $third, $markTop, $x + $third, $markTop + $markH, $color);
                imageline($image, $x + ($third * 2), $markTop, $x + ($third * 2), $markTop + $markH, $color);
            }

            $x += $w;
        }

        imagesetthickness($image, 1);
    }

    private function drawMarkCell($image, string $mark, int $x, int $y, int $w, int $h, int $black): void
    {
        $mark = $this->normalizeMark($mark);

        if ($mark === '') {
            return;
        }

        if ($mark === 'X') {
            $this->drawStrikeMark($image, $x, $y, $w, $h, $black);
            return;
        }

        if ($mark === '/') {
            $this->drawSpareMark($image, $x, $y, $w, $h, $black);
            return;
        }

        $this->drawCenteredText($image, $mark, $x, $y, $w, $h, 18, $black, false);
    }

    private function drawStrikeMark($image, int $x, int $y, int $w, int $h, int $black): void
    {
        $left = $x + 1;
        $right = $x + $w - 1;
        $top = $y + 1;
        $bottom = $y + $h - 1;
        $middleX = (int) round($x + ($w / 2));
        $middleY = (int) round($y + ($h / 2));

        imagefilledpolygon($image, [
            $left, $top,
            $left, $bottom,
            $middleX, $middleY,
        ], $black);

        imagefilledpolygon($image, [
            $right, $top,
            $right, $bottom,
            $middleX, $middleY,
        ], $black);
    }

    private function drawSpareMark($image, int $x, int $y, int $w, int $h, int $black): void
    {
        imagefilledpolygon($image, [
            $x + $w - 1, $y + 1,
            $x + $w - 1, $y + $h - 1,
            $x + 1, $y + $h - 1,
        ], $black);
    }

    private function calculateCumulativeScoresForPdf(Collection $frames): array
    {
        $rolls = [];
        $frameStartIndexes = [];
        $normalizedFrames = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $frames->get($frameNo);

            $throw1 = $this->markText($frame, 'throw1');
            $throw2 = $this->markText($frame, 'throw2');
            $throw3 = $this->markText($frame, 'throw3');

            if ($frameNo < 10 && ($throw1 === 'X' || $throw2 === 'X')) {
                $throw1 = 'X';
                $throw2 = '';
            }

            $normalizedFrames[$frameNo] = [
                'throw1' => $throw1,
                'throw2' => $throw2,
                'throw3' => $throw3,
            ];

            $frameStartIndexes[$frameNo] = count($rolls);

            if ($frameNo < 10) {
                if ($throw1 === '') {
                    continue;
                }

                if ($throw1 === 'X') {
                    $rolls[] = 10;
                    continue;
                }

                $firstPins = $this->pinsForFirstThrow($throw1);
                $rolls[] = $firstPins;

                if ($throw2 === '') {
                    continue;
                }

                $rolls[] = $throw2 === '/'
                    ? max(0, 10 - $firstPins)
                    : $this->pinsForNormalThrow($throw2);

                continue;
            }

            if ($throw1 === '') {
                continue;
            }

            $firstPins = $this->pinsForFirstThrow($throw1);
            $rolls[] = $firstPins;

            if ($throw2 === '') {
                continue;
            }

            $secondPins = $throw2 === '/'
                ? max(0, 10 - $firstPins)
                : $this->pinsForNormalThrow($throw2);

            $rolls[] = $secondPins;

            if ($throw3 === '') {
                continue;
            }

            $rolls[] = $throw3 === '/'
                ? max(0, 10 - $secondPins)
                : $this->pinsForNormalThrow($throw3);
        }

        $total = 0;
        $cumulative = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $normalizedFrames[$frameNo] ?? [
                'throw1' => '',
                'throw2' => '',
                'throw3' => '',
            ];

            $start = $frameStartIndexes[$frameNo] ?? count($rolls);
            $frameScore = null;

            if ($frameNo < 10) {
                if ($frame['throw1'] === '') {
                    $frameScore = null;
                } elseif ($frame['throw1'] === 'X') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 1] + $rolls[$start + 2];
                    }
                } elseif ($frame['throw2'] === '/') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 2];
                    }
                } elseif ($frame['throw2'] !== '') {
                    if (isset($rolls[$start], $rolls[$start + 1])) {
                        $frameScore = $rolls[$start] + $rolls[$start + 1];
                    }
                }
            } else {
                $requiredRollCount = $this->requiredTenthFrameRollCount($frame);
                $tenthRolls = array_slice($rolls, $start);

                if ($requiredRollCount > 0 && count($tenthRolls) >= $requiredRollCount) {
                    $frameScore = array_sum($tenthRolls);
                }
            }

            if ($frameScore !== null) {
                $total += $frameScore;
                $cumulative[$frameNo] = $total;
            } else {
                $cumulative[$frameNo] = null;
            }
        }

        return $cumulative;
    }

    private function requiredTenthFrameRollCount(array $frame): int
    {
        if (($frame['throw1'] ?? '') === '') {
            return 0;
        }

        if (($frame['throw2'] ?? '') === '') {
            return 1;
        }

        if (($frame['throw1'] ?? '') === 'X' || ($frame['throw2'] ?? '') === '/') {
            return 3;
        }

        return 2;
    }

    private function pinsForFirstThrow(string $mark): int
    {
        if ($mark === 'X') {
            return 10;
        }

        return $this->pinsForNormalThrow($mark);
    }

    private function pinsForNormalThrow(string $mark): int
    {
        if ($mark === 'X') {
            return 10;
        }

        if ($mark === '-' || $mark === 'F' || $mark === '') {
            return 0;
        }

        if (preg_match('/^[0-9]$/', $mark)) {
            return max(0, min(9, (int) $mark));
        }

        return 0;
    }

    private function remainingPinsLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : preg_split('/[^0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            return '';
        }

        $pins = [];

        foreach ($value as $pin) {
            $pinNumber = filter_var($pin, FILTER_VALIDATE_INT);

            if ($pinNumber === false || $pinNumber < 1 || $pinNumber > 10) {
                continue;
            }

            $pins[] = $pinNumber;
        }

        $pins = array_values(array_unique($pins));
        sort($pins, SORT_NUMERIC);

        return implode('.', $pins);
    }

    private function marksForFrame($frame, int $frameNo): array
    {
        $throw1 = $this->markText($frame, 'throw1');
        $throw2 = $this->markText($frame, 'throw2');
        $throw3 = $this->markText($frame, 'throw3');

        if ($frameNo < 10 && ($throw1 === 'X' || $throw2 === 'X')) {
            return ['', 'X', ''];
        }

        return [$throw1, $throw2, $throw3];
    }

    private function markText($frame, string $key): string
    {
        if (!$frame) {
            return '';
        }

        $marks = is_array($frame->display_marks ?? null) ? $frame->display_marks : [];
        $value = $marks[$key] ?? $frame->{$key} ?? '';

        return $this->normalizeMark($value);
    }

    private function normalizeMark(mixed $value): string
    {
        $mark = strtoupper(trim((string) ($value ?? '')));
        $mark = str_replace(['×', 'Ｘ', 'ｘ'], 'X', $mark);
        $mark = str_replace(['ー', '－', '―'], '-', $mark);

        if ($mark === '' || $mark === '.') {
            return '';
        }

        if (in_array($mark, ['X', '/', '-', 'F'], true)) {
            return $mark;
        }

        if (preg_match('/^[0-9]$/', $mark)) {
            return $mark;
        }

        return '';
    }

    private function resolveLaneLabelForPlayer($player, TournamentMatchScoreSheet $scoreSheet, int $playerIndex, int $playerCount): string
    {
        $playerLane = trim((string) ($player->lane_label ?? ''));

        if ($playerLane !== '') {
            return $this->normalizeLaneLabel($playerLane);
        }

        $sheetLane = trim((string) ($scoreSheet->lane_label ?? ''));

        if ($sheetLane === '') {
            return '';
        }

        $lanes = $this->extractLaneNumbers($sheetLane);

        if ($playerCount === 2 && count($lanes) >= 2) {
            return (string) ($playerIndex === 0 ? $lanes[1] : $lanes[0]);
        }

        if (isset($lanes[$playerIndex])) {
            return (string) $lanes[$playerIndex];
        }

        return $this->normalizeLaneLabel($sheetLane);
    }

    /**
     * @return array<int,int>
     */
    private function extractLaneNumbers(string $laneLabel): array
    {
        preg_match_all('/\d+/', $laneLabel, $matches);

        return array_map('intval', $matches[0] ?? []);
    }

    private function normalizeLaneLabel(string $laneLabel): string
    {
        $laneLabel = trim($laneLabel);

        if ($laneLabel === '') {
            return '';
        }

        $laneLabel = str_replace(['Ｌ', 'ｌ'], 'L', $laneLabel);

        if (preg_match('/^\s*(\d+)\s*L?\s*$/i', $laneLabel, $matches)) {
            return (string) (int) $matches[1];
        }

        $lanes = $this->extractLaneNumbers($laneLabel);

        if (count($lanes) === 1) {
            return (string) $lanes[0];
        }

        return $laneLabel;
    }

    private function drawCenteredText($image, string $text, int $x, int $y, int $w, int $h, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);

        if ($text === '') {
            return;
        }

        $font = $this->font;
        $box = @imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            imagestring($image, 5, $x + 2, $y + 2, $text, $color);
            return;
        }

        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);
        $tx = (int) round($x + (($w - $textW) / 2));
        $ty = (int) round($y + (($h + $textH) / 2) - 2);

        imagettftext($image, $size, 0, $tx, $ty, $color, $font, $text);

        if ($bold) {
            imagettftext($image, $size, 0, $tx + 1, $ty, $color, $font, $text);
        }
    }

    private function drawRightText($image, string $text, int $x, int $y, int $w, int $h, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeText($text);

        if ($text === '') {
            return;
        }

        $font = $this->font;
        $box = @imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            imagestring($image, 5, $x + 2, $y + 2, $text, $color);
            return;
        }

        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);
        $tx = (int) round($x + $w - $textW - 2);
        $ty = (int) round($y + (($h + $textH) / 2) - 2);

        imagettftext($image, $size, 0, $tx, $ty, $color, $font, $text);

        if ($bold) {
            imagettftext($image, $size, 0, $tx + 1, $ty, $color, $font, $text);
        }
    }

    private function formatArm(string $arm): string
    {
        $arm = trim($arm);

        if ($arm === '') {
            return '';
        }

        $arm = str_replace(['（', '）', '(', ')'], '', $arm);
        $arm = preg_replace('/\s+/u', '', $arm) ?? $arm;
        $arm = preg_replace('/投げ$/u', '', $arm) ?? $arm;

        if (in_array($arm, ['right', '右', 'R', 'r'], true)) {
            return '右';
        }

        if (in_array($arm, ['left', '左', 'L', 'l'], true)) {
            return '左';
        }

        if (str_contains($arm, '左') && str_contains($arm, '両手')) {
            return '左両手';
        }

        if (str_contains($arm, '右') && str_contains($arm, '両手')) {
            return '右両手';
        }

        if (str_contains($arm, '左') && str_contains($arm, 'サムレス')) {
            return '左サムレス';
        }

        if (str_contains($arm, '右') && str_contains($arm, 'サムレス')) {
            return '右サムレス';
        }

        if (str_contains($arm, '左')) {
            return '左';
        }

        if (str_contains($arm, '右')) {
            return '右';
        }

        return $arm;
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
            'C:/PHP/fonts/ipaexg.ttf',
            storage_path('fonts/ipaexg.ttf'),
            base_path('storage/fonts/ipaexg.ttf'),
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
