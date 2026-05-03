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
     * @return array<int,array{sheet_id:int,match_label:string,game_number:int,lane_label:string,image:string}>
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

        $width = 1400;
        $top = 28;
        $blockHeight = 164;
        $height = $top + ($players->count() * $blockHeight) + 28;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $dark = imagecolorallocate($image, 30, 30, 30);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $tableLeft = 140;
        $tableWidth = 1130;
        $frameW = 102;
        $tenthW = 212;
        $headerH = 30;
        $markH = 44;
        $totalH = 34;
        $tableH = $headerH + $markH + $totalH;

        foreach ($players->values() as $index => $player) {
            $blockTop = $top + ($index * $blockHeight);
            $tableTop = $blockTop + 54;
            $frames = $player->frames instanceof Collection ? $player->frames->keyBy('frame_no') : collect($player->frames ?? [])->keyBy('frame_no');

            $name = trim((string) ($player->display_name ?? ''));
            $arm = $this->formatArm((string) ($player->dominant_arm ?? ''));
            $winner = !empty($player->is_winner) ? ' / 勝者' : '';
            $title = $name . ($arm !== '' ? '（' . $arm . '）' : '') . $winner;

            $this->drawCenteredText($image, $title, $tableLeft, $blockTop + 6, $tableWidth, 30, 24, $black, true);

            $laneLabel = trim((string) ($player->lane_label ?: $scoreSheet->lane_label ?: ''));
            $this->drawRightText($image, $laneLabel, 20, $tableTop + 44, 108, 34, 20, $black, false);

            $this->drawScoreTableFrame($image, $tableLeft, $tableTop, $frameW, $tenthW, $headerH, $markH, $totalH, $black);

            $x = $tableLeft;
            for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
                $w = $frameNo === 10 ? $tenthW : $frameW;
                $this->drawCenteredText($image, (string) $frameNo, $x, $tableTop + 1, $w, $headerH - 3, 20, $black, true);

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

                $total = $frame && $frame->cumulative_score !== null ? (string) $frame->cumulative_score : '';
                $this->drawCenteredText($image, $total, $x, $tableTop + $headerH + $markH, $w, $totalH, 20, $black, false);

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

        imagesetthickness($image, 3);
        imagerectangle($image, $left, $top, $left + $width, $top + $height, $color);

        imagesetthickness($image, 2);
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
        $mark = strtoupper(trim($mark));
        $mark = str_replace(['×', 'Ｘ', 'ｘ'], 'X', $mark);
        $mark = str_replace(['ー', '－', '―'], '-', $mark);

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

        $this->drawCenteredText($image, $mark, $x, $y, $w, $h, 21, $black, false);
    }

    private function drawStrikeMark($image, int $x, int $y, int $w, int $h, int $black): void
    {
        // JPBA公式PDFのストライク表記は、アルファベットのXではなく、
        // 右側投球欄いっぱいに左右2つの黒三角を向かい合わせた「砂時計型」です。
        // 枠線を潰さないよう、1pxだけ内側に寄せて描画します。
        $left = $x + 1;
        $right = $x + $w - 1;
        $top = $y + 1;
        $bottom = $y + $h - 1;
        $middleX = (int) round($x + ($w / 2));
        $middleY = (int) round($y + ($h / 2));

        // 左三角：左辺全体から中央へ向かう黒塗り
        imagefilledpolygon($image, [
            $left, $top,
            $left, $bottom,
            $middleX, $middleY,
        ], $black);

        // 右三角：右辺全体から中央へ向かう黒塗り
        imagefilledpolygon($image, [
            $right, $top,
            $right, $bottom,
            $middleX, $middleY,
        ], $black);
    }

    private function drawSpareMark($image, int $x, int $y, int $w, int $h, int $black): void
    {
        // 公式PDFのスペアは、右側投球欄を対角線で切る黒三角。
        imagefilledpolygon($image, [
            $x + $w, $y,
            $x + $w, $y + $h,
            $x, $y + $h,
        ], 3, $black);
    }

    private function marksForFrame($frame, int $frameNo): array
    {
        $throw1 = $this->markText($frame, 'throw1');
        $throw2 = $this->markText($frame, 'throw2');
        $throw3 = $this->markText($frame, 'throw3');

        if ($frameNo < 10 && $throw1 === 'X') {
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
        $value = strtoupper(trim((string) $value));
        $value = str_replace(['×', 'Ｘ', 'ｘ'], 'X', $value);
        $value = str_replace(['ー', '－', '―'], '-', $value);

        return $value;
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
        if (in_array($arm, ['right', '右', 'R', 'r'], true)) {
            return '右';
        }
        if (in_array($arm, ['left', '左', 'L', 'l'], true)) {
            return '左';
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
