<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use RuntimeException;

class ShootoutBracketImageService
{
    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = $this->resolveFontPath();
    }

    public function generateDataUri(Tournament $tournament, array $shootoutPdf): ?string
    {
        $pngBinary = $this->generatePngBinary($tournament, $shootoutPdf);

        if ($pngBinary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($pngBinary);
    }

    private function generatePngBinary(Tournament $tournament, array $shootoutPdf): string
    {
        if (!extension_loaded('gd') || !function_exists('imagepng') || !function_exists('imagettftext')) {
            throw new RuntimeException('GD拡張、imagepng、imagettftext が有効ではありません。');
        }

        $templatePath = public_path('images/shootout_tournament_bracket_template.png');
        if (!is_file($templatePath)) {
            throw new RuntimeException('シュートアウト対戦表テンプレート画像が見つかりません: ' . $templatePath);
        }

        $template = imagecreatefrompng($templatePath);
        if (!$template) {
            throw new RuntimeException('シュートアウト対戦表テンプレート画像を読み込めませんでした。');
        }

        $canvasWidth = 1110;
        $canvasHeight = 636;
        $templateLeft = 96;
        $templateTop = 0;
        $templateWidth = 922;
        $templateHeight = 636;

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        if (!$canvas) {
            imagedestroy($template);
            throw new RuntimeException('シュートアウト画像キャンバスを作成できませんでした。');
        }

        imageantialias($canvas, true);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 17, 24, 39);
        $red = imagecolorallocate($canvas, 220, 38, 38);

        imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $white);

        $srcWidth = imagesx($template);
        $srcHeight = imagesy($template);
        imagecopyresampled(
            $canvas,
            $template,
            $templateLeft,
            $templateTop,
            0,
            0,
            $templateWidth,
            $templateHeight,
            $srcWidth,
            $srcHeight
        );
        imagedestroy($template);

        $seedRows = [];
        foreach ((array) ($shootoutPdf['seed_rows'] ?? []) as $row) {
            $row = (array) $row;
            $seed = (int) ($row['seed'] ?? 0);
            if ($seed >= 1 && $seed <= 8) {
                $seedRows[$seed] = $row;
            }
        }

        $matchesByNo = [];
        foreach ((array) ($shootoutPdf['matches'] ?? []) as $index => $match) {
            $match = (array) $match;
            $matchNo = (int) ($match['match_no'] ?? ($index + 1));
            if ($matchNo > 0) {
                $matchesByNo[$matchNo] = $match;
            }
        }

        $scoreForSeedInMatch = function (int $seed, int $matchNo) use ($matchesByNo): ?int {
            $match = (array) ($matchesByNo[$matchNo] ?? []);
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

        $winnerSeedInMatch = function (int $matchNo) use ($matchesByNo): ?int {
            $match = (array) ($matchesByNo[$matchNo] ?? []);
            $winnerNode = (array) ($match['winner_node'] ?? []);

            if (!isset($winnerNode['seed']) || $winnerNode['seed'] === null) {
                return null;
            }

            return (int) $winnerNode['seed'];
        };

        $seedName = function (int $seed) use ($seedRows): string {
            $row = (array) ($seedRows[$seed] ?? []);
            $name = trim((string) ($row['display_name'] ?? $row['name'] ?? ''));

            return $name !== '' ? $name : '—';
        };

        $seedTermLabel = function (int $seed) use ($seedRows): string {
            $row = (array) ($seedRows[$seed] ?? []);
            $label = trim((string) ($row['term_label'] ?? ''));

            if ($label === '') {
                return '';
            }

            return str_contains($label, '期') ? $label : $label . '期生';
        };

        $seedKana = function (int $seed) use ($seedRows): string {
            $row = (array) ($seedRows[$seed] ?? []);

            foreach ([
                'display_name_kana',
                'name_kana',
                'kana',
                'furigana',
                'player_kana',
                'bowler_kana',
            ] as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            $proBowlerId = (int) ($row['pro_bowler_id'] ?? 0);
            if ($proBowlerId > 0) {
                $kana = ProBowler::query()->where('id', $proBowlerId)->value('name_kana');
                if ($kana) {
                    return trim((string) $kana);
                }
            }

            $license = strtoupper(trim((string) ($row['pro_bowler_license_no'] ?? '')));
            if ($license !== '') {
                $kana = ProBowler::query()->whereRaw('upper(license_no) = ?', [$license])->value('name_kana');
                if ($kana) {
                    return trim((string) $kana);
                }
            }

            return '';
        };

        $firstWinnerSeed = $winnerSeedInMatch(1);
        $secondWinnerSeed = $winnerSeedInMatch(2);
        $finalWinnerSeed = $winnerSeedInMatch(3);

        $championName = trim((string) ($shootoutPdf['summary']['winner_name'] ?? ''));
        if ($championName === '' && $finalWinnerSeed !== null) {
            $championName = $seedName((int) $finalWinnerSeed);
        }

        $finalRankBySeed = (array) ($shootoutPdf['final_rank_by_seed'] ?? []);
        if (empty($finalRankBySeed)) {
            $finalRankBySeed = $this->buildFinalRankBySeed($firstWinnerSeed, $secondWinnerSeed, $finalWinnerSeed);
        }

        $finalRankLabel = function (int $seed) use ($finalRankBySeed): string {
            $rank = (int) ($finalRankBySeed[$seed] ?? 0);
            if ($rank <= 0) {
                return '（未定）';
            }

            return $rank === 1 ? '（優　勝）' : '（第' . $rank . '位）';
        };

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
        $templateWinnerX = $templateLeft + 598;
        $templateFirstOutY = $templateTop + 464;
        $templateSecondOutY = $templateTop + 360;
        $winnerOutputLineY = $templateTop + 243;

        $horizontalLineOffsetBySeed = [
            1 => 3,
            2 => 3,
            3 => -1,
            4 => -2,
            5 => -3,
            6 => -5,
            7 => -5,
            8 => -6,
        ];

        $lineY = function (int $seed) use ($templateRowCenter, $horizontalLineOffsetBySeed): int {
            return $templateRowCenter($seed) + (int) ($horizontalLineOffsetBySeed[$seed] ?? 0);
        };

        $drawHorizontalPath = function (int $x1, int $x2, int $y, bool $seedLine = false) use ($canvas, $black): void {
            if ($x1 === $x2) {
                return;
            }

            $left = min($x1, $x2) - ($seedLine ? 7 : 3);
            $width = abs($x2 - $x1) + ($seedLine ? 7 : 3);
            imagefilledrectangle($canvas, $left, $y - 3, $left + $width, $y + 3, $black);
        };

        $drawVerticalPath = function (int $x, int $y1, int $y2) use ($canvas, $black): void {
            if ($y1 === $y2) {
                return;
            }

            $top = min($y1, $y2) - 3;
            $height = abs($y2 - $y1) + 6;
            imagefilledrectangle($canvas, $x - 3, $top, $x + 3, $top + $height, $black);
        };

        if ($firstWinnerSeed !== null && in_array((int) $firstWinnerSeed, [5, 6, 7, 8], true)) {
            $drawHorizontalPath($templateBoxRight, $templateFirstX, $lineY((int) $firstWinnerSeed), true);
            $drawVerticalPath($templateFirstX, $lineY((int) $firstWinnerSeed), $templateFirstOutY);
            $drawHorizontalPath($templateFirstX, $templateSecondX, $templateFirstOutY);
        }

        if ($secondWinnerSeed !== null) {
            $secondWinnerSeed = (int) $secondWinnerSeed;
            if (in_array($secondWinnerSeed, [2, 3, 4], true)) {
                $drawHorizontalPath($templateBoxRight, $templateSecondX, $lineY($secondWinnerSeed), true);
                $drawVerticalPath($templateSecondX, $lineY($secondWinnerSeed), $templateSecondOutY);
                $drawHorizontalPath($templateSecondX, $templateFinalX, $templateSecondOutY);
            } elseif (
                $firstWinnerSeed !== null
                && $secondWinnerSeed === (int) $firstWinnerSeed
                && in_array($secondWinnerSeed, [5, 6, 7, 8], true)
            ) {
                $drawVerticalPath($templateSecondX, $templateFirstOutY, $templateSecondOutY);
                $drawHorizontalPath($templateSecondX, $templateFinalX, $templateSecondOutY);
            }
        }

        if ($finalWinnerSeed !== null) {
            $finalWinnerSeed = (int) $finalWinnerSeed;
            if ($finalWinnerSeed === 1) {
                $drawHorizontalPath($templateBoxRight, $templateFinalX, $lineY(1), true);
                $drawVerticalPath($templateFinalX, $lineY(1), $winnerOutputLineY);
                $drawHorizontalPath($templateFinalX, $templateWinnerX, $winnerOutputLineY);
            } elseif ($secondWinnerSeed !== null && $finalWinnerSeed === (int) $secondWinnerSeed) {
                $drawVerticalPath($templateFinalX, $templateSecondOutY, $winnerOutputLineY);
                $drawHorizontalPath($templateFinalX, $templateWinnerX, $winnerOutputLineY);
            }
        }

        $this->drawTextBox($canvas, '最終順位', 0, ($templateRows[1] ?? 37) - 28, 98, 30, 17, $black, 'right', true);

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

        // PNG生成ではテンプレート画像そのものへ描画するため、
        // Web表示用に必要だった行ごとの細かい上下補正は使いません。
        // ここを行ごとに動かすとPDF上で選手名・期表示が枠からはみ出しやすくなります。
        $termTopOffsetBySeed = [];

        $playerNameTopOffsetBySeed = [];

        foreach (range(1, 8) as $seed) {
            $rowTop = (int) ($templateRows[$seed] ?? 0);
            $finalRankTopOffset = (int) ($finalRankTopOffsetBySeed[$seed] ?? 0);
            $termTopOffset = (int) ($termTopOffsetBySeed[$seed] ?? 0);
            $playerNameTopOffset = (int) ($playerNameTopOffsetBySeed[$seed] ?? 0);

            $this->drawTextBox($canvas, $finalRankLabel($seed), 0, $rowTop + 12 + $finalRankTopOffset, 98, 34, 17, $black, 'right', true);

            $termLabel = $seedTermLabel($seed);
            if ($termLabel !== '') {
                // 「1位通過」の右側に収める。フォントを少し小さくし、枠内からのはみ出しを防ぐ。
                $this->drawTextBox($canvas, '（' . $termLabel . '）', $templateLeft + 120, $rowTop + 6 + $termTopOffset, 104, 20, 11, $black, 'left', true);
            }

            // 選手名は枠内の下段中央。PDF貼り付け時に下へはみ出さないよう少し小さめに固定。
            $this->drawTextBox($canvas, $seedName($seed), $templateLeft + 16, $rowTop + 31 + $playerNameTopOffset, 250, 24, 18, $black, 'center', true);
        }

        $scorePositions = [];
        foreach ([5, 6, 7, 8] as $seed) {
            // 8位通過側の勝者スコアは太線にかぶりやすいため、少しだけ上に逃がす。
            $scoreTopOffsetBySeed = [8 => -14];
            $scorePositions[] = [
                'seed' => $seed,
                'match_no' => 1,
                'left' => $templateLeft + 288,
                'top' => $templateRowCenter($seed) - 34 + (int) ($scoreTopOffsetBySeed[$seed] ?? 0),
                'winner' => $firstWinnerSeed !== null && (int) $firstWinnerSeed === (int) $seed,
            ];
        }

        foreach ([2, 3, 4] as $seed) {
            $scoreTopOffsetBySeed = [3 => -5, 4 => -5];
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
                'top' => $templateFirstOutY - 25,
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

        foreach ($scorePositions as $scorePosition) {
            $seed = (int) ($scorePosition['seed'] ?? 0);
            $matchNo = (int) ($scorePosition['match_no'] ?? 0);
            $score = $seed > 0 && $matchNo > 0 ? $scoreForSeedInMatch($seed, $matchNo) : null;

            if ($score === null) {
                continue;
            }

            $isWinner = !empty($scorePosition['winner']);
            $this->drawTextBox(
                $canvas,
                (string) $score,
                (int) $scorePosition['left'],
                (int) $scorePosition['top'],
                50,
                24,
                $isWinner ? 15 : 14,
                $isWinner ? $red : $black,
                'center',
                $isWinner
            );
        }

        $championKana = $finalWinnerSeed !== null ? $seedKana((int) $finalWinnerSeed) : '';
        if ($championKana !== '') {
            $this->drawTextBox($canvas, $championKana, 712, 219, 262, 18, 10, $black, 'center', true);
        }

        $this->drawTextBox($canvas, $championName !== '' ? $championName : '—', 712, 240, 262, 36, 21, $black, 'center', true);

        ob_start();
        imagepng($canvas);
        $pngBinary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $pngBinary;
    }

    private function buildFinalRankBySeed(?int $firstWinnerSeed, ?int $secondWinnerSeed, ?int $finalWinnerSeed): array
    {
        $finalRankBySeed = [];

        if ($finalWinnerSeed !== null) {
            $finalRankBySeed[$finalWinnerSeed] = 1;
        }

        if ($secondWinnerSeed !== null && $finalWinnerSeed !== null) {
            $finalLoserSeed = (int) $finalWinnerSeed === 1 ? $secondWinnerSeed : 1;
            if ($finalLoserSeed > 0) {
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
            $rank = 6;
            foreach ([5, 6, 7, 8] as $seed) {
                if ((int) $seed === (int) $firstWinnerSeed) {
                    continue;
                }
                $finalRankBySeed[$seed] = $rank;
                $rank++;
            }
        }

        return $finalRankBySeed;
    }

    private function drawTextBox($image, string $text, int $left, int $top, int $width, int $height, int $size, int $color, string $align = 'left', bool $bold = false): void
    {
        $text = $this->normalizeUtf8(trim($text));
        if ($text === '') {
            return;
        }

        $fontSize = $this->fitFontSize($text, $size, max(1, $width - 4));
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

        $x = match ($align) {
            'center' => $left + (int) round(($width - $textWidth) / 2) - $minX,
            'right' => $left + $width - $textWidth - 4 - $minX,
            default => $left + 4 - $minX,
        };

        $y = $top + (int) round(($height - $textHeight) / 2) - $minY;

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
        // Windows の GD/FreeType は、日本語やスペースを含むパス上の TTF を
        // imagettfbbox()/imagettftext() で開けないことがあります。
        // そのため、まず C:/PHP/fonts のような短く安全なパスを優先し、
        // 次に Windows 標準フォント、最後に Laravel 配下の storage/fonts を見ます。
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

            // 実在していても GD が開けないパスがあるため、ここで実描画前に検証します。
            // @ で候補検証時の警告だけ抑制し、使えるフォントだけ採用します。
            $testBox = @imagettfbbox(12, 0, $normalizedPath, '日本語');
            if ($testBox !== false) {
                return $normalizedPath;
            }
        }

        throw new RuntimeException('日本語描画用のTrueTypeフォントをGDで開けません。C:/PHP/fonts/ipaexg.ttf を配置するか、環境変数 JPBA_PDF_FONT_PATH に使用するフォントの絶対パスを指定してください。');
    }
}
