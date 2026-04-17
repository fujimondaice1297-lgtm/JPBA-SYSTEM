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

        $includeStages = [$stage];
        $carryStages   = $this->resolveCarryStages($stage, $carryPrelim, $carrySemifinal, $enabledStages);

        if (!empty($enabledStages)) {
            $includeStages = array_values(array_intersect($includeStages, $enabledStages));
            $carryStages   = array_values(array_intersect($carryStages, $enabledStages));
        }

        $allStages = array_values(array_unique(array_merge($includeStages, $carryStages)));

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
                    'breakdown'    => ['予選' => [], '準々決勝' => [], '準決勝' => [], '決勝' => []],
                    'stage_totals' => ['予選' => 0, '準々決勝' => 0, '準決勝' => 0, '決勝' => 0],
                ];
            } else {
                if ($players[$key]['gender'] === '' && (string)$r->gender !== '') {
                    $players[$key]['gender'] = (string)$r->gender;
                }
            }

            $stageName = (string)$r->stage;
            if (!isset($players[$key]['breakdown'][$stageName])) {
                $players[$key]['breakdown'][$stageName] = [];
                $players[$key]['stage_totals'][$stageName] = 0;
            }

            $gameNo = (int)$r->game_number;
            $score  = (int)$r->score;

            $players[$key]['breakdown'][$stageName][$gameNo] = $score;

            if ($stageName === $stage) {
                if ($gameNo <= $uptoGame) {
                    $players[$key]['stage_totals'][$stageName] += $score;
                }
            } else {
                $players[$key]['stage_totals'][$stageName] += $score;
            }
        }

        // 現在ステージに1G以上ある選手のみ
        $players = array_values(array_filter($players, function ($p) use ($stage) {
            $arr = $p['breakdown'][$stage] ?? [];
            return count(array_filter($arr, fn($v) => $v !== null && $v !== '' && (int)$v > 0)) > 0;
        }));

        // 合計とタイブレーク計算
        foreach ($players as &$p) {
            $p['total'] = 0;
            foreach ($carryStages as $cs) {
                $p['total'] += ($p['stage_totals'][$cs] ?? 0);
            }
            $p['total'] += ($p['stage_totals'][$stage] ?? 0);

            $countedScores = $this->collectCountedScores($p, $carryStages, $stage, $uptoGame);
            $gamesCount = count($countedScores);

            $p['baseline_points'] = $gamesCount * $perPoint;
            $p['over_under']      = $p['total'] - $p['baseline_points'];

            // 同点時は差の少ない方を上位
            $p['tie_break_spread'] = $this->scoreSpread($countedScores);
            $p['counted_scores']   = $countedScores;
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
        foreach ($carryStages as $cs) {
            $carryBaseG += (int)($stageSettings[$cs] ?? 0);
        }
        $headerBaseGames = $uptoGame + $carryBaseG;

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
                'border_type'               => $borderType,
                'border_value'              => $borderValue,
                'baseline_games'            => $headerBaseGames,
                'current_stage_total_games' => $currentStageTotalGames,
            ],
            'rows'         => $players,
            'border_index' => $borderIndex,
        ];
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
