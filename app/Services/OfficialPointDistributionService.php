<?php

namespace App\Services;

final class OfficialPointDistributionService
{
    private const MEN = [
        1 => 1000, 2 => 800, 3 => 700, 4 => 600, 5 => 500, 6 => 450, 7 => 400, 8 => 350,
        9 => 300, 10 => 260, 11 => 230, 12 => 210, 13 => 190, 14 => 180, 15 => 170, 16 => 160,
        17 => 150, 18 => 140, 19 => 130, 20 => 120, 21 => 110, 22 => 105, 23 => 100, 24 => 95,
        25 => 90, 26 => 87, 27 => 84, 28 => 81, 29 => 78, 30 => 75, 31 => 72, 32 => 70,
        33 => 68, 34 => 66, 35 => 64, 36 => 62, 37 => 60, 38 => 59, 39 => 58, 40 => 57,
        41 => 56, 42 => 55, 43 => 54, 44 => 53, 45 => 52, 46 => 51, 47 => 50, 48 => 49,
        49 => 48, 50 => 47, 51 => 46, 52 => 45, 53 => 44, 54 => 43, 55 => 42, 56 => 41,
        57 => 40, 58 => 39, 59 => 38, 60 => 37, 61 => 36, 62 => 35, 63 => 34, 64 => 33,
        65 => 32, 66 => 31, 67 => 30, 68 => 29, 69 => 28, 70 => 27, 71 => 26, 72 => 25,
        73 => 24, 74 => 23, 75 => 22, 76 => 21, 77 => 20, 78 => 19, 79 => 18, 80 => 17,
        81 => 16, 82 => 15, 83 => 14, 84 => 13, 85 => 12, 86 => 11, 87 => 10, 88 => 9,
        89 => 8, 90 => 7, 91 => 6, 92 => 5, 93 => 4, 94 => 3, 95 => 2, 96 => 1,
    ];

    private const WOMEN = [
        1 => 800, 2 => 650, 3 => 560, 4 => 480, 5 => 410, 6 => 360, 7 => 320, 8 => 280,
        9 => 240, 10 => 210, 11 => 185, 12 => 165, 13 => 150, 14 => 140, 15 => 132, 16 => 124,
        17 => 116, 18 => 108, 19 => 100, 20 => 94, 21 => 89, 22 => 85, 23 => 81, 24 => 77,
        25 => 73, 26 => 70, 27 => 67, 28 => 64, 29 => 62, 30 => 60, 31 => 58, 32 => 56,
        33 => 54, 34 => 52, 35 => 50, 36 => 48, 37 => 46, 38 => 44, 39 => 42, 40 => 40,
        41 => 38, 42 => 36, 43 => 34, 44 => 32, 45 => 30, 46 => 28, 47 => 26, 48 => 25,
        49 => 24, 50 => 23, 51 => 22, 52 => 21, 53 => 20, 54 => 19, 55 => 18, 56 => 17,
        57 => 16, 58 => 15, 59 => 14, 60 => 13, 61 => 12, 62 => 11, 63 => 10, 64 => 9,
        65 => 8, 66 => 7, 67 => 6, 68 => 5, 69 => 4, 70 => 3, 71 => 2, 72 => 1,
    ];

    private const SEASON_TRIAL = [
        1 => 50, 2 => 40, 3 => 35, 4 => 30, 5 => 25, 6 => 23, 7 => 20, 8 => 18,
    ];

    /** @return array<string,mixed> */
    public function distribution(): array
    {
        return [
            'men' => $this->rows(self::MEN),
            'men_columns' => $this->columns(self::MEN, 32),
            'women' => $this->rows(self::WOMEN),
            'women_columns' => $this->columns(self::WOMEN, 36),
            'season_trial' => $this->rows(self::SEASON_TRIAL),
        ];
    }

    /** @param array<int,int> $points
     * @return array<int,array{rank:int,points:int}>
     */
    private function rows(array $points): array
    {
        $rows = [];
        foreach ($points as $rank => $point) {
            $rows[] = ['rank' => $rank, 'points' => $point];
        }

        return $rows;
    }

    /** @param array<int,int> $points
     * @return array<int,array<int,array{rank:int,points:int}>>
     */
    private function columns(array $points, int $perColumn): array
    {
        return array_chunk($this->rows($points), $perColumn);
    }
}
