<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use RuntimeException;

class StepLadderBracketImageService
{
    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = $this->resolveFontPath();
    }

    public function generateDataUri(Tournament $tournament, array $stepLadder, array $options = []): ?string
    {
        $pngBinary = $this->generatePngBinary($tournament, $stepLadder, $options);

        if ($pngBinary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($pngBinary);
    }

    private function generatePngBinary(Tournament $tournament, array $stepLadder, array $options = []): string
    {
        if (($options['layout'] ?? '') === 'rokko_queens') {
            return $this->generateRokkoQueensPngBinary(
                $tournament,
                (array) ($options['layout_data'] ?? [])
            );
        }

        if (!extension_loaded('gd') || !function_exists('imagepng') || !function_exists('imagettftext')) {
            throw new RuntimeException('GD拡張、imagepng、imagettftext が有効ではありません。');
        }

        $templatePath = public_path('images/step_ladder_tournament_bracket_template.png');
        if (!is_file($templatePath)) {
            throw new RuntimeException('ステップラダー対戦表テンプレート画像が見つかりません: ' . $templatePath);
        }

        $template = imagecreatefrompng($templatePath);
        if (!$template) {
            throw new RuntimeException('ステップラダー対戦表テンプレート画像を読み込めませんでした。');
        }

        $canvasWidth = 1672;
        $canvasHeight = 941;

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        if (!$canvas) {
            imagedestroy($template);
            throw new RuntimeException('ステップラダー画像キャンバスを作成できませんでした。');
        }

        imageantialias($canvas, true);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 17, 17, 17);
        $red = imagecolorallocate($canvas, 239, 17, 17);

        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $white);

        $srcWidth = imagesx($template);
        $srcHeight = imagesy($template);

        imagecopyresampled(
            $canvas,
            $template,
            0,
            0,
            0,
            0,
            $canvasWidth,
            $canvasHeight,
            $srcWidth,
            $srcHeight
        );

        imagedestroy($template);

        $seeds = array_values((array) ($stepLadder['seeds'] ?? []));
        $semifinal = (array) ($stepLadder['semifinal'] ?? []);
        $final = (array) ($stepLadder['final'] ?? []);

        $seed1 = (array) ($seeds[0] ?? []);
        $seed2 = (array) ($seeds[1] ?? []);
        $seed3 = (array) ($seeds[2] ?? []);

        $samePlayer = function ($a, $b): bool {
            if (!$a || !$b) {
                return false;
            }

            $a = (array) $a;
            $b = (array) $b;

            $keyA = trim((string) ($a['participant_key'] ?? ''));
            $keyB = trim((string) ($b['participant_key'] ?? ''));

            if ($keyA !== '' && $keyB !== '') {
                return $keyA === $keyB;
            }

            return trim((string) ($a['display_name'] ?? '')) === trim((string) ($b['display_name'] ?? ''));
        };

        $scoreText = function ($score): string {
            return $score === null || $score === '' ? '' : (string) ((int) $score);
        };

        $displayName = function (array $player): string {
            $name = trim((string) ($player['display_name'] ?? $player['name'] ?? $player['amateur_name'] ?? ''));

            return $name !== '' ? $name : '—';
        };

        $semiWinner = (array) ($semifinal['winner'] ?? []);
        $finalWinner = (array) ($final['winner'] ?? []);
        $finalBottom = (array) ($final['bottom'] ?? []);

        $semiTop = (array) ($semifinal['top'] ?? $seed2);
        $semiBottom = (array) ($semifinal['bottom'] ?? $seed3);
        $finalTop = (array) ($final['top'] ?? $seed1);

        $finalTopName = $displayName($finalTop ?: $seed1);
        $semiTopName = $displayName($semiTop ?: $seed2);
        $semiBottomName = $displayName($semiBottom ?: $seed3);

        $semiTopScoreText = $scoreText($semifinal['top_score'] ?? null);
        $semiBottomScoreText = $scoreText($semifinal['bottom_score'] ?? null);
        $finalTopScoreText = $scoreText($final['top_score'] ?? null);
        $finalBottomScoreText = $scoreText($final['bottom_score'] ?? null);

        $semiDone = (($semifinal['status'] ?? '') === 'done');
        $finalDone = (($final['status'] ?? '') === 'done');

        $isSemiTopWinner = $semiDone && $samePlayer($semiWinner, $semiTop);
        $isSemiBottomWinner = $semiDone && $samePlayer($semiWinner, $semiBottom);
        $isFinalTopWinner = $finalDone && $samePlayer($finalWinner, $finalTop);
        $isFinalBottomWinner = $finalDone && $samePlayer($finalWinner, $finalBottom);

        $championName = $finalDone ? $displayName($finalWinner) : '未確定';

        // 勝者線：1回戦
        // テンプレート枠の端に合わせるため、太線は imageline の丸めではなく矩形で描画する。
        if ($semiDone && $isSemiTopWinner) {
            $this->drawLine($canvas, 854, 506, 1105, 506, $red, 8);
        } elseif ($semiDone && $isSemiBottomWinner) {
            $this->drawLine($canvas, 753, 695, 988, 695, $red, 8);
            $this->drawLine($canvas, 988, 506, 988, 695, $red, 8);
            $this->drawLine($canvas, 988, 506, 1105, 506, $red, 8);
        }

        // 勝者線：優勝決定戦
        // 右側の優勝枠へ赤線が入り込みすぎないよう、優勝枠左端の手前で止める。
        if ($finalDone && $isFinalTopWinner) {
            $this->drawLine($canvas, 959, 330, 1227, 330, $red, 8);
        } elseif ($finalDone && $isFinalBottomWinner) {
            $this->drawLine($canvas, 1105, 506, 1105, 330, $red, 8);
            $this->drawLine($canvas, 1105, 330, 1227, 330, $red, 8);
        }

        // 氏名
        $this->drawCenteredText($canvas, $finalTopName, 815, 331, 320, 46, 38, $black, true);
        $this->drawCenteredText($canvas, $semiTopName, 715, 507, 300, 46, 38, $black, true);
        $this->drawCenteredText($canvas, $semiBottomName, 607, 695, 300, 46, 38, $black, true);

        // スコア
        if ($semiTopScoreText !== '') {
            $this->drawCenteredText($canvas, $semiTopScoreText, 920, 486, 80, 38, 32, $isSemiTopWinner ? $red : $black, true);
        }

        if ($semiBottomScoreText !== '') {
            $this->drawCenteredText($canvas, $semiBottomScoreText, 920, 733, 80, 38, 32, $isSemiBottomWinner ? $red : $black, true);
        }

        if ($finalTopScoreText !== '') {
            $this->drawCenteredText($canvas, $finalTopScoreText, 1088, 286, 80, 38, 32, $isFinalTopWinner ? $red : $black, true);
        }

        if ($finalBottomScoreText !== '') {
            $this->drawCenteredText($canvas, $finalBottomScoreText, 1088, 548, 80, 38, 32, $isFinalBottomWinner ? $red : $black, true);
        }

        // 優勝者名
        $this->drawCenteredText($canvas, $championName, 1419, 362, 340, 52, 42, $black, true);

        ob_start();
        imagepng($canvas);
        $pngBinary = (string) ob_get_clean();

        imagedestroy($canvas);

        return $pngBinary;
    }

    /**
     * 六甲クイーンズ公式PDFの4名ステップラダー専用配置。
     * 既存の標準作図は変更せず、layout=rokko_queens のときだけ使用する。
     */
    private function generateRokkoQueensPngBinary(Tournament $tournament, array $layoutData): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagepng') || ! function_exists('imagettftext')) {
            throw new RuntimeException('六甲クイーンズ作図に必要なGD拡張が利用できません。');
        }

        $width = 1012;
        $height = 328;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('六甲クイーンズのステップラダー画像を作成できませんでした。');
        }

        imageantialias($image, true);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $seeds = array_values((array) ($layoutData['seeds'] ?? []));
        $rankLabels = ['(優　勝)', '(第２位)', '(第３位)', '(第４位)'];
        $seedY = [0, 79, 158, 237];
        $boxLeft = 124;
        $boxWidth = 264;
        $boxHeight = 68;

        foreach ($seedY as $index => $y) {
            $seed = (array) ($seeds[$index] ?? []);
            $name = trim((string) ($seed['display_name'] ?? ''));
            $period = trim((string) ($seed['period'] ?? ''));
            if ($period === '' && ! empty($seed['pro_bowler_id'])) {
                $period = trim((string) ProBowler::query()
                    ->whereKey((int) $seed['pro_bowler_id'])
                    ->value('kibetsu'));
            }

            $this->drawCenteredText($image, $rankLabels[$index], 58, $y + 34, 116, 42, 27, $black, true);

            imagesetthickness($image, $index === 0 ? 5 : 2);
            imagerectangle(
                $image,
                $boxLeft,
                $y,
                $boxLeft + $boxWidth,
                $y + $boxHeight,
                $black
            );
            imagesetthickness($image, 1);

            $seedLabel = ($index + 1).'位通過'
                .($period !== '' ? '　('.$period.'期生)' : '');
            $this->drawCenteredText($image, $seedLabel, $boxLeft + 132, $y + 15, $boxWidth - 8, 26, 22, $black);
            $this->drawCenteredText(
                $image,
                $this->spacedOfficialName($name),
                $boxLeft + 132,
                $y + 47,
                $boxWidth - 8,
                38,
                30,
                $black
            );
        }

        $scores = (array) ($layoutData['scores'] ?? []);
        $score = static fn (string $key): string => isset($scores[$key]) && $scores[$key] !== null
            ? (string) (int) $scores[$key]
            : '';

        $this->drawCenteredText($image, $score('final_top'), 585, 0, 72, 36, 28, $black, true);
        $this->drawCenteredText($image, $score('round2_top'), 498, 79, 72, 36, 28, $black, true);
        $this->drawCenteredText($image, $score('round1_top'), 410, 158, 72, 36, 28, $black, true);
        $this->drawCenteredText($image, $score('round1_bottom'), 410, 286, 72, 36, 28, $black);
        $this->drawCenteredText($image, $score('round2_bottom'), 498, 207, 72, 36, 28, $black);
        $this->drawCenteredText($image, $score('final_bottom'), 585, 128, 72, 36, 28, $black);

        // 4位決定戦
        $this->drawLine($image, 388, 192, 494, 192, $black, 4);
        $this->drawLine($image, 388, 271, 494, 271, $black, 2);
        $this->drawLine($image, 494, 192, 494, 271, $black, 4);

        // 3位決定戦
        $this->drawLine($image, 388, 113, 582, 113, $black, 4);
        $this->drawLine($image, 494, 232, 582, 232, $black, 4);
        $this->drawLine($image, 582, 113, 582, 232, $black, 4);

        // 優勝決定戦
        $this->drawLine($image, 388, 34, 670, 34, $black, 4);
        $this->drawLine($image, 582, 153, 670, 153, $black, 4);
        $this->drawLine($image, 670, 34, 670, 153, $black, 4);
        $this->drawLine($image, 670, 93, 724, 93, $black, 4);

        $champion = (array) ($layoutData['champion'] ?? []);
        $championName = trim((string) ($champion['display_name'] ?? ''));
        $championKana = trim((string) ($champion['name_kana'] ?? ''));
        imagesetthickness($image, 5);
        imagerectangle($image, 724, 36, 988, 120, $black);
        imagesetthickness($image, 1);
        $this->drawCenteredText(
            $image,
            trim('優勝　'.$this->halfWidthKana($championKana)),
            856,
            55,
            252,
            32,
            27,
            $black,
            true
        );
        $this->drawCenteredText(
            $image,
            $this->spacedOfficialName($championName),
            856,
            93,
            252,
            45,
            32,
            $black,
            true
        );
        $this->drawCenteredText(
            $image,
            trim((string) ($layoutData['winner_note'] ?? '')),
            867,
            175,
            286,
            48,
            27,
            $black
        );

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    private function spacedOfficialName(string $name): string
    {
        $name = trim(preg_replace('/[\s　]+/u', '', $name) ?? $name);
        if ($name === '' || str_contains($name, '･') || str_contains($name, '・')) {
            return $name;
        }

        return implode('　', preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name]);
    }

    private function halfWidthKana(string $value): string
    {
        if ($value === '' || ! function_exists('mb_convert_kana')) {
            return $value;
        }

        return mb_convert_kana($value, 'kV', 'UTF-8');
    }

    private function drawLine($image, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
    {
        $half = max(1, (int) floor($thickness / 2));

        if ($y1 === $y2) {
            $left = min($x1, $x2);
            $right = max($x1, $x2);
            imagefilledrectangle($image, $left, $y1 - $half, $right, $y1 + $half, $color);
            return;
        }

        if ($x1 === $x2) {
            $top = min($y1, $y2);
            $bottom = max($y1, $y2);
            imagefilledrectangle($image, $x1 - $half, $top, $x1 + $half, $bottom, $color);
            return;
        }

        imagesetthickness($image, $thickness);
        imageline($image, $x1, $y1, $x2, $y2, $color);
        imagesetthickness($image, 1);
    }

    private function drawCenteredText($image, string $text, int $centerX, int $centerY, int $maxWidth, int $height, int $size, int $color, bool $bold = false): void
    {
        $text = $this->normalizeUtf8(trim($text));

        if ($text === '') {
            return;
        }

        $fontSize = $this->fitFontSize($text, $size, $maxWidth);
        $box = imagettfbbox($fontSize, 0, $this->fontPath, $text);

        if ($box === false) {
            return;
        }

        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        $minY = min($box[1], $box[3], $box[5], $box[7]);
        $maxY = max($box[1], $box[3], $box[5], $box[7]);

        $textWidth = $maxX - $minX;
        $textHeight = $maxY - $minY;

        $x = $centerX - (int) round($textWidth / 2) - $minX;
        $y = $centerY + (int) round($textHeight / 2) - $maxY;

        imagettftext($image, $fontSize, 0, $x, $y, $color, $this->fontPath, $text);

        if ($bold) {
            imagettftext($image, $fontSize, 0, $x + 1, $y, $color, $this->fontPath, $text);
        }
    }

    private function fitFontSize(string $text, int $size, int $maxWidth): int
    {
        $fontSize = $size;

        while ($fontSize > 8) {
            $box = imagettfbbox($fontSize, 0, $this->fontPath, $text);
            if ($box === false) {
                return $fontSize;
            }

            $minX = min($box[0], $box[2], $box[4], $box[6]);
            $maxX = max($box[0], $box[2], $box[4], $box[6]);

            if (($maxX - $minX) <= $maxWidth) {
                return $fontSize;
            }

            $fontSize--;
        }

        return $fontSize;
    }

    private function normalizeUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($text, 'UTF-8', 'SJIS-win,SJIS,EUC-JP,ISO-2022-JP,UTF-8');

            return is_string($converted) ? $converted : $text;
        }

        return $text;
    }

    private function resolveFontPath(): string
    {
        $candidates = array_values(array_filter([
            getenv('JPBA_PDF_FONT_PATH') ?: null,
            'C:/PHP/fonts/ipaexg.ttf',
            'C:/PHP/fonts/ipaexm.ttf',
            'C:/php/fonts/ipaexg.ttf',
            'C:/php/fonts/ipaexm.ttf',
            'C:/Windows/Fonts/meiryob.ttc',
            'C:/Windows/Fonts/meiryo.ttc',
            'C:/Windows/Fonts/YuGothB.ttc',
            'C:/Windows/Fonts/YuGothM.ttc',
            'C:/Windows/Fonts/msgothic.ttc',
            storage_path('fonts/ipaexg.ttf'),
            storage_path('fonts/ipaexm.ttf'),
            public_path('fonts/ipaexg.ttf'),
            public_path('fonts/ipaexm.ttf'),
            base_path('storage/fonts/ipaexg.ttf'),
            base_path('storage/fonts/ipaexm.ttf'),
        ]));

        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }

            $realPath = realpath($path);
            if ($realPath === false) {
                continue;
            }

            $normalizedPath = str_replace('\\', '/', $realPath);
            putenv('GDFONTPATH=' . str_replace('\\', '/', dirname($realPath)));

            $testBox = @imagettfbbox(12, 0, $normalizedPath, '日本語');
            if ($testBox !== false) {
                return $normalizedPath;
            }
        }

        throw new RuntimeException('日本語描画用のTrueTypeフォントをGDで開けません。C:/PHP/fonts/ipaexg.ttf を配置するか、環境変数 JPBA_PDF_FONT_PATH に使用するフォントの絶対パスを指定してください。');
    }
}
