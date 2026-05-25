<?php

namespace App\Services;

use App\Models\GameScore;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class ScoreService
{
    /**
     * 段階キャリー集計つきランキング取得
     */
    public function getRankings(array $opt): array
    {
        $tid            = (int)($opt['tournament_id'] ?? 0);
        $stage          = (string)($opt['stage'] ?? '予選');
        $uptoGame       = max(1, (int)($opt['upto_game'] ?? 1));
        $perPoint       = (int)($opt['per_point'] ?? 200);
        $borderType     = (string)($opt['border_type'] ?? 'rank');
        $borderValue    = $opt['border_value'] !== null ? (int)$opt['border_value'] : null;
        $carryPrelim    = (int)($opt['carry_prelim'] ?? 0) === 1;
        $carrySemifinal = (int)($opt['carry_semifinal'] ?? 0) === 1;
        $shiftCSV       = trim((string)($opt['shifts'] ?? ''));
        $shifts         = $shiftCSV !== '' ? array_filter(array_map('trim', explode(',', $shiftCSV))) : [];
        $genderFilter   = (string)($opt['gender_filter'] ?? '');

        $stageSettings = (array)($opt['stage_settings'] ?? []);
        $enabledStages = (array)($opt['enabled_stages'] ?? []);

        $tournament = Tournament::find($tid);

        $sourceSets = $this->normalizeSourceSets((array)($opt['source_sets'] ?? []), $stage, $uptoGame);

        if (!empty($sourceSets)) {
            $includeStages = array_values(array_unique(array_map(fn ($row) => (string) $row['stage'], $sourceSets)));
            $carryStages = array_values(array_filter(
                $includeStages,
                fn ($stageName) => $stageName !== $stage
            ));
            $allStages = $includeStages;
        } else {
            $includeStages = [$stage];
            $carryStages = $this->resolveCarryStages($stage, $carryPrelim, $carrySemifinal, $enabledStages);

            if (!empty($enabledStages)) {
                $includeStages = array_values(array_intersect($includeStages, $enabledStages));
                $carryStages = array_values(array_intersect($carryStages, $enabledStages));
            }

            $allStages = array_values(array_unique(array_merge($includeStages, $carryStages)));
        }

        $q = GameScore::query()
            ->where('tournament_id', $tid)
            ->when(!empty($allStages), fn($x) => $x->whereIn('stage', $allStages));

        if (!empty($shifts)) {
            $q->whereIn('shift', $shifts);
        }

        if ($genderFilter !== '') {
            $q->where(function ($qq) use ($genderFilter) {
                $qq->where('gender', $genderFilter)
                    ->orWhere(function ($q2) use ($genderFilter) {
                        $q2->whereNotNull('license_number')
                            ->where('license_number', 'like', $genderFilter . '%');
                    });
            });
        }

        /** @var Collection $rows */
        $rows = $q->get();

        $players = [];
        foreach ($rows as $r) {
            $stageName = (string)$r->stage;
            $gameNo = (int)$r->game_number;

            if (!empty($sourceSets) && !$this->isScoreIncludedBySourceSets($stageName, $gameNo, $sourceSets)) {
                continue;
            }

            $key = $this->normKey($r);
            if ($key === '') {
                continue;
            }

            if (!isset($players[$key])) {
                $players[$key] = [
                    'id'           => $key,
                    'gender'       => (string)($r->gender ?? ''),
                    'raw_ids'      => [
                        'license' => $this->digitsOnly($r->license_number),
                        'entry'   => $r->entry_number,
                        'name'    => $r->name,
                    ],
                    'breakdown'    => ['予選' => [], '準々決勝' => [], '準決勝' => [], 'ラウンドロビン' => [], '決勝' => []],
                    'stage_totals' => ['予選' => 0, '準々決勝' => 0, '準決勝' => 0, 'ラウンドロビン' => 0, '決勝' => 0],
                ];
            } else {
                if ($players[$key]['gender'] === '' && (string)$r->gender !== '') {
                    $players[$key]['gender'] = (string)$r->gender;
                }
            }

            if (!isset($players[$key]['breakdown'][$stageName])) {
                $players[$key]['breakdown'][$stageName] = [];
                $players[$key]['stage_totals'][$stageName] = 0;
            }

            $score = (int)$r->score;
            $players[$key]['breakdown'][$stageName][$gameNo] = $score;
            $players[$key]['stage_totals'][$stageName] += $score;
        }

        // 現在ステージに1G以上ある選手のみ。
        // 通算表示でも、表示中のステージに参加していない選手はランキングから外す。
        $players = array_values(array_filter($players, function ($p) use ($stage) {
            $arr = $p['breakdown'][$stage] ?? [];
            return count(array_filter($arr, fn($v) => $v !== null && $v !== '' && (int)$v > 0)) > 0;
        }));

        foreach ($players as &$p) {
            $p['total'] = 0;

            if (!empty($sourceSets)) {
                foreach ($sourceSets as $sourceSet) {
                    $sourceStage = (string) $sourceSet['stage'];
                    $p['total'] += $this->sumSourceSetScores($p, $sourceSet);
                }
            } else {
                foreach ($carryStages as $cs) {
                    $p['total'] += ($p['stage_totals'][$cs] ?? 0);
                }
                $p['total'] += ($p['stage_totals'][$stage] ?? 0);
            }

            $countedScores = !empty($sourceSets)
                ? $this->collectCountedScoresFromSourceSets($p, $sourceSets)
                : $this->collectCountedScores($p, $carryStages, $stage, $uptoGame);

            $tieBreakScores = !empty($sourceSets)
                ? $this->collectTieBreakScoresFromSourceSets($p, $sourceSets, $stage, $uptoGame)
                : $this->collectCurrentStageScores($p, $stage, $uptoGame);

            if (count($tieBreakScores) === 0) {
                $tieBreakScores = $countedScores;
            }

            $gamesCount = count($countedScores);

            $p['baseline_points'] = $gamesCount * $perPoint;
            $p['over_under']      = $p['total'] - $p['baseline_points'];

            // 同ピン時は、現在ステージのローハイ（最高点 - 最低点）が少ない方を上位にする。
            // 例：準決勝通算12Gでは、通算合計は予選+準決勝で見るが、同ピン判定は準決勝4Gのローハイで見る。
            $p['tie_break_spread'] = $this->scoreSpread($tieBreakScores);
            $p['tie_break_scores'] = $tieBreakScores;
            $p['counted_scores']   = $countedScores;
            $p['games_counted']    = $gamesCount;
        }
        unset($p);

        usort($players, function ($a, $b) use ($stage) {
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }

            if (($a['tie_break_spread'] ?? PHP_INT_MAX) !== ($b['tie_break_spread'] ?? PHP_INT_MAX)) {
                return ($a['tie_break_spread'] ?? PHP_INT_MAX) <=> ($b['tie_break_spread'] ?? PHP_INT_MAX);
            }

            $aStage = (int)($a['stage_totals'][$stage] ?? 0);
            $bStage = (int)($b['stage_totals'][$stage] ?? 0);
            if ($aStage !== $bStage) {
                return $bStage <=> $aStage;
            }

            return strcmp((string)$a['id'], (string)$b['id']);
        });

        $top = $players[0]['total'] ?? 0;
        foreach ($players as $i => &$p) {
            $p['rank'] = $i + 1;
            $p['diff_from_top'] = $top > 0 ? ($p['total'] - $top) : 0;
            $p['display_license'] = $p['raw_ids']['license'] ?: ($p['raw_ids']['entry'] ?: '');
        }
        unset($p);

        $borderIndex = null;
        if ($borderType === 'rank' && $borderValue && $borderValue > 0) {
            $borderIndex = min(count($players), $borderValue) - 1;
        } elseif ($borderType === 'point' && $borderValue) {
            $cnt = 0;
            foreach ($players as $p) {
                if ($p['total'] >= $borderValue) {
                    $cnt++;
                }
            }
            if ($cnt > 0) {
                $borderIndex = $cnt - 1;
            }
        }

        $carryBaseG = 0;
        if (!empty($sourceSets)) {
            foreach ($sourceSets as $sourceSet) {
                $sourceStage = (string) $sourceSet['stage'];
                if ($sourceStage === $stage) {
                    continue;
                }
                $carryBaseG += max(0, (int) $sourceSet['game_to'] - (int) $sourceSet['game_from'] + 1);
            }
        } else {
            foreach ($carryStages as $cs) {
                $carryBaseG += (int)($stageSettings[$cs] ?? 0);
            }
        }

        $maxCountedGames = 0;
        foreach ($players as $p) {
            $maxCountedGames = max($maxCountedGames, (int)($p['games_counted'] ?? count((array)($p['counted_scores'] ?? []))));
        }

        $headerBaseGames = max($uptoGame + $carryBaseG, $maxCountedGames);
        if ($headerBaseGames <= 0) {
            $headerBaseGames = $uptoGame;
        }

        $currentStageTotalGames = (int)($stageSettings[$stage] ?? 0);
        if ($currentStageTotalGames <= 0) {
            $currentStageTotalGames = $uptoGame;
            foreach ($players as $p) {
                $currentStageTotalGames = max($currentStageTotalGames, count((array)($p['breakdown'][$stage] ?? [])));
            }
        }

        return [
            'meta' => [
                'tournament'                => $tournament,
                'stage'                     => $stage,
                'upto_game'                 => $uptoGame,
                'carry_prelim'              => $carryPrelim ? 1 : 0,
                'carry_semifinal'           => $carrySemifinal ? 1 : 0,
                'per_point'                 => $perPoint,
                'includeStages'             => $includeStages,
                'carryStages'               => $carryStages,
                'source_sets'               => $sourceSets,
                'border_type'               => $borderType,
                'border_value'              => $borderValue,
                'baseline_games'            => $headerBaseGames,
                'current_stage_total_games' => $currentStageTotalGames,
            ],
            'rows'         => $players,
            'border_index' => $borderIndex,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $sourceSets
     * @return array<int,array{stage:string,game_from:int,game_to:int,bucket:string}>
     */
    private function normalizeSourceSets(array $sourceSets, string $stage, int $uptoGame): array
    {
        $normalized = [];

        foreach ($sourceSets as $sourceSet) {
            if (!is_array($sourceSet)) {
                continue;
            }

            $stageName = trim((string) ($sourceSet['stage'] ?? ''));
            if ($stageName === '') {
                continue;
            }

            $from = max(1, (int) ($sourceSet['game_from'] ?? 1));
            $to = (int) ($sourceSet['game_to'] ?? 0);
            if ($to <= 0) {
                $to = $stageName === $stage ? $uptoGame : $from;
            }

            if ($to < $from) {
                continue;
            }

            $normalized[] = [
                'stage' => $stageName,
                'game_from' => $from,
                'game_to' => $to,
                'bucket' => (string) ($sourceSet['bucket'] ?? ($stageName === $stage ? 'scratch' : 'carry')),
            ];
        }

        return $normalized;
    }

    private function isScoreIncludedBySourceSets(string $stageName, int $gameNo, array $sourceSets): bool
    {
        foreach ($sourceSets as $sourceSet) {
            if ((string) $sourceSet['stage'] !== $stageName) {
                continue;
            }

            if ($gameNo >= (int) $sourceSet['game_from'] && $gameNo <= (int) $sourceSet['game_to']) {
                return true;
            }
        }

        return false;
    }

    private function sumSourceSetScores(array $player, array $sourceSet): int
    {
        $stageScores = (array)($player['breakdown'][(string) $sourceSet['stage']] ?? []);
        $sum = 0;

        foreach ($stageScores as $gameNo => $score) {
            if ((int) $gameNo < (int) $sourceSet['game_from'] || (int) $gameNo > (int) $sourceSet['game_to']) {
                continue;
            }

            $sum += (int) $score;
        }

        return $sum;
    }

    private function collectCountedScoresFromSourceSets(array $player, array $sourceSets): array
    {
        $scores = [];

        foreach ($sourceSets as $sourceSet) {
            $stageScores = (array)($player['breakdown'][(string) $sourceSet['stage']] ?? []);
            ksort($stageScores);

            foreach ($stageScores as $gameNo => $score) {
                if ((int) $gameNo < (int) $sourceSet['game_from'] || (int) $gameNo > (int) $sourceSet['game_to']) {
                    continue;
                }

                if ((int) $score > 0) {
                    $scores[] = (int) $score;
                }
            }
        }

        return $scores;
    }

    private function collectTieBreakScoresFromSourceSets(array $player, array $sourceSets, string $stage, int $uptoGame): array
    {
        $scores = [];

        foreach ($sourceSets as $sourceSet) {
            $sourceStage = (string) $sourceSet['stage'];
            $bucket = (string) ($sourceSet['bucket'] ?? '');

            if ($sourceStage !== $stage && $bucket !== 'scratch') {
                continue;
            }

            $stageScores = (array) ($player['breakdown'][$sourceStage] ?? []);
            ksort($stageScores);

            foreach ($stageScores as $gameNo => $score) {
                $gameNo = (int) $gameNo;

                if ($gameNo < (int) $sourceSet['game_from'] || $gameNo > (int) $sourceSet['game_to']) {
                    continue;
                }

                if ($sourceStage === $stage && $gameNo > $uptoGame) {
                    continue;
                }

                if ((int) $score > 0) {
                    $scores[] = (int) $score;
                }
            }
        }

        return $scores;
    }

    private function collectCurrentStageScores(array $player, string $stage, int $uptoGame): array
    {
        $scores = [];
        $stageScores = (array) ($player['breakdown'][$stage] ?? []);
        ksort($stageScores);

        foreach ($stageScores as $gameNo => $score) {
            if ((int) $gameNo > $uptoGame) {
                continue;
            }

            if ((int) $score > 0) {
                $scores[] = (int) $score;
            }
        }

        return $scores;
    }

    private function resolveCarryStages(string $stage, bool $carryPrelim, bool $carrySemifinal, array $enabledStages): array
    {
        $stageOrder = ['予選', '準々決勝', '準決勝', '決勝'];
        $activeStages = !empty($enabledStages)
            ? array_values(array_intersect($stageOrder, $enabledStages))
            : $stageOrder;

        $currentIndex = array_search($stage, $activeStages, true);
        if ($currentIndex === false) {
            return [];
        }

        $carryStages = [];

        if ($stage === '決勝') {
            if ($carryPrelim) {
                foreach (['予選', '準々決勝'] as $carryStage) {
                    $carryIndex = array_search($carryStage, $activeStages, true);
                    if ($carryIndex !== false && $carryIndex < $currentIndex) {
                        $carryStages[] = $carryStage;
                    }
                }
            }

            if ($carrySemifinal) {
                $carryIndex = array_search('準決勝', $activeStages, true);
                if ($carryIndex !== false && $carryIndex < $currentIndex) {
                    $carryStages[] = '準決勝';
                }
            }

            return array_values(array_unique($carryStages));
        }

        if (!$carryPrelim) {
            return [];
        }

        foreach ($activeStages as $carryStage) {
            if ($carryStage === $stage) {
                break;
            }
            $carryStages[] = $carryStage;
        }

        return array_values(array_unique($carryStages));
    }

    private function collectCountedScores(array $player, array $carryStages, string $stage, int $uptoGame): array
    {
        $scores = [];

        foreach ($carryStages as $carryStage) {
            $stageScores = (array)($player['breakdown'][$carryStage] ?? []);
            ksort($stageScores);
            foreach ($stageScores as $score) {
                if ((int)$score > 0) {
                    $scores[] = (int)$score;
                }
            }
        }

        $currentStageScores = (array)($player['breakdown'][$stage] ?? []);
        ksort($currentStageScores);
        foreach ($currentStageScores as $gameNo => $score) {
            if ((int)$gameNo > $uptoGame) {
                continue;
            }
            if ((int)$score > 0) {
                $scores[] = (int)$score;
            }
        }

        return $scores;
    }

    private function scoreSpread(array $scores): int
    {
        if (count($scores) <= 1) {
            return 0;
        }

        return max($scores) - min($scores);
    }

    private function digitsOnly(?string $s): string
    {
        if (!$s) {
            return '';
        }

        return preg_replace('/\D+/', '', $s) ?: '';
    }

    private function normKey(GameScore $r): string
    {
        $g = trim((string)$r->gender);
        $digits = $this->digitsOnly($r->license_number);
        if ($digits !== '') {
            return ($g !== '' ? ($g . '-') : '') . ltrim($digits, '0');
        }

        if ($r->entry_number) {
            return ($g !== '' ? ($g . '-') : '') . trim((string)$r->entry_number);
        }

        if ($r->name) {
            return ($g !== '' ? ($g . '-') : '') . trim((string)$r->name);
        }

        return '';
    }
}
