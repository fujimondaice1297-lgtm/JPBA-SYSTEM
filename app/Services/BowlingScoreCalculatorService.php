<?php

namespace App\Services;

class BowlingScoreCalculatorService
{
    /**
     * @param array<int, array{throw1?: mixed, throw2?: mixed, throw3?: mixed}> $frames
     * @return array{total:int, frames:array<int,array<string,mixed>>, rolls:array<int>}
     */
    public function calculate(array $frames): array
    {
        $normalizedFrames = [];
        $rolls = [];
        $rollIndexByFrame = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $frames[$frameNo] ?? [];
            $t1 = $this->normalizeMark($frame['throw1'] ?? null);
            $t2 = $this->normalizeMark($frame['throw2'] ?? null);
            $t3 = $this->normalizeMark($frame['throw3'] ?? null);

            $rollIndexByFrame[$frameNo] = count($rolls);
            $display = ['throw1' => $t1, 'throw2' => $t2, 'throw3' => $t3];

            if ($frameNo < 10) {
                if ($t1 === '') {
                    $normalizedFrames[$frameNo] = [
                        'frame_no' => $frameNo,
                        'throw1' => null,
                        'throw2' => null,
                        'throw3' => null,
                        'frame_score' => null,
                        'cumulative_score' => null,
                        'display_marks' => $display,
                    ];
                    continue;
                }

                if ($t1 === 'X') {
                    $rolls[] = 10;
                } else {
                    $p1 = $this->pinsForFirstThrow($t1);
                    $p2 = $t2 === '/' ? max(0, 10 - $p1) : $this->pinsForSecondThrow($t2);
                    $rolls[] = $p1;
                    $rolls[] = $p2;
                }
            } else {
                if ($t1 !== '') {
                    $p1 = $this->pinsForFirstThrow($t1);
                    $rolls[] = $p1;

                    if ($t2 !== '') {
                        $p2 = $t2 === '/' ? max(0, 10 - $p1) : $this->pinsForSecondThrow($t2);
                        $rolls[] = $p2;

                        if ($t3 !== '') {
                            $p3 = $t3 === '/' ? max(0, 10 - $p2) : $this->pinsForSecondThrow($t3);
                            $rolls[] = $p3;
                        }
                    }
                }
            }

            $normalizedFrames[$frameNo] = [
                'frame_no' => $frameNo,
                'throw1' => $t1 !== '' ? $t1 : null,
                'throw2' => $t2 !== '' ? $t2 : null,
                'throw3' => $frameNo === 10 && $t3 !== '' ? $t3 : null,
                'frame_score' => null,
                'cumulative_score' => null,
                'display_marks' => $display,
            ];
        }

        $total = 0;

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $normalizedFrames[$frameNo];
            $start = $rollIndexByFrame[$frameNo] ?? count($rolls);
            $frameScore = null;

            if ($frameNo < 10) {
                $throw1 = (string)($frame['throw1'] ?? '');
                $throw2 = (string)($frame['throw2'] ?? '');

                if ($throw1 === '') {
                    $frameScore = null;
                } elseif ($throw1 === 'X') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 1] + $rolls[$start + 2];
                    }
                } elseif ($throw2 === '/') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 2];
                    }
                } elseif ($throw2 !== '') {
                    if (isset($rolls[$start], $rolls[$start + 1])) {
                        $frameScore = $rolls[$start] + $rolls[$start + 1];
                    }
                }
            } else {
                $frameScore = array_sum(array_slice($rolls, $start));
            }

            if ($frameScore !== null) {
                $total += $frameScore;
                $normalizedFrames[$frameNo]['frame_score'] = $frameScore;
                $normalizedFrames[$frameNo]['cumulative_score'] = $total;
            }
        }

        return [
            'total' => $total,
            'frames' => $normalizedFrames,
            'rolls' => $rolls,
        ];
    }

    private function normalizeMark($value): string
    {
        $mark = strtoupper(trim((string)$value));
        $mark = str_replace(['×', 'ｘ', 'Ｘ'], 'X', $mark);
        $mark = str_replace(['ー', '－', '―'], '-', $mark);

        if ($mark === '' || $mark === '.') {
            return '';
        }

        if (in_array($mark, ['X', '/', '-', 'F'], true)) {
            return $mark;
        }

        if (is_numeric($mark)) {
            $pin = max(0, min(9, (int)$mark));
            return (string)$pin;
        }

        return '';
    }

    private function pinsForFirstThrow(string $mark): int
    {
        return match ($mark) {
            'X' => 10,
            '-', 'F', '' => 0,
            default => is_numeric($mark) ? max(0, min(9, (int)$mark)) : 0,
        };
    }

    private function pinsForSecondThrow(string $mark): int
    {
        return match ($mark) {
            'X' => 10,
            '/', '-', 'F', '' => $mark === '/' ? 10 : 0,
            default => is_numeric($mark) ? max(0, min(9, (int)$mark)) : 0,
        };
    }
}
