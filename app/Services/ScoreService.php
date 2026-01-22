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
        $tid             = (int)($opt['tournament_id'] ?? 0);
        $stage           = (string)($opt['stage'] ?? '予選');
        $uptoGame        = max(1, (int)($opt['upto_game'] ?? 1));
        $perPoint        = (int)($opt['per_point'] ?? 200);
        $borderType      = (string)($opt['border_type'] ?? 'rank');
        $borderValue     = $opt['border_value'] !== null ? (int)$opt['border_value'] : null;
        $carryPrelim     = (int)($opt['carry_prelim'] ?? 0) === 1;
        $carrySemifinal  = (int)($opt['carry_semifinal'] ?? 0) === 1; // ★準決勝持ち込み
        $shiftCSV        = trim((string)($opt['shifts'] ?? ''));
        $shifts          = $shiftCSV !== '' ? array_filter(array_map('trim', explode(',', $shiftCSV))) : [];
        $genderFilter    = (string)($opt['gender_filter'] ?? '');

        $stageSettings = (array)($opt['stage_settings'] ?? []);
        $enabledStages = (array)($opt['enabled_stages'] ?? []);

        $tournament = Tournament::find($tid);

        // ====== どのステージを持ち込むかを決定（フラグで制御） ======
        $includeStages = [$stage];
        $carryStages   = [];
        if ($stage === '決勝') {
            if ($carrySemifinal) $carryStages[] = '準決勝';
            if ($carryPrelim)    $carryStages[] = '予選';
        } elseif ($stage === '準決勝') {
            if ($carryPrelim)    $carryStages[] = '予選';
        } else {
            if ($carryPrelim && $stage !== '予選') $carryStages[] = '予選';
        }

        if (!empty($enabledStages)) {
            $includeStages = array_values(array_intersect($includeStages, $enabledStages));
            $carryStages   = array_values(array_intersect($carryStages,   $enabledStages));
        }
        $allStages = array_values(array_unique(array_merge($includeStages, $carryStages)));

        $q = GameScore::query()
            ->where('tournament_id', $tid)
            ->when(!empty($allStages), fn($x)=>$x->whereIn('stage', $allStages));

        if (!empty($shifts)) $q->whereIn('shift', $shifts);
        if ($genderFilter !== '') {
            $q->where(function ($qq) use ($genderFilter) {
                $qq->where('gender', $genderFilter)
                   ->orWhere(function ($q2) use ($genderFilter) {
                       $q2->whereNotNull('license_number')
                          ->where('license_number', 'like', $genderFilter.'%');
                   });
            });
        }

        /** @var Collection $rows */
        $rows = $q->get();

        $players = [];
        foreach ($rows as $r) {
            $key = $this->normKey($r); // 性別＋番号（重複回避）
            if ($key === '') continue;

            if (!isset($players[$key])) {
                $players[$key] = [
                    'id'          => $key,
                    'gender'      => (string)($r->gender ?? ''),
                    'raw_ids'     => [
                        'license' => $this->digitsOnly($r->license_number),
                        'entry'   => $r->entry_number,
                        'name'    => $r->name,
                    ],
                    'breakdown'   => ['予選'=>[], '準々決勝'=>[], '準決勝'=>[], '決勝'=>[]],
                    'stage_totals'=> ['予選'=>0,'準々決勝'=>0,'準決勝'=>0,'決勝'=>0],
                ];
            } else {
                if ($players[$key]['gender'] === '' && (string)$r->gender !== '') {
                    $players[$key]['gender'] = (string)$r->gender;
                }
            }

            $stageName = $r->stage;
            if (!isset($players[$key]['breakdown'][$stageName])) {
                $players[$key]['breakdown'][$stageName] = [];
                $players[$key]['stage_totals'][$stageName] = 0;
            }

            if ($stageName === $stage) {
                if ((int)$r->game_number <= $uptoGame) {
                    $players[$key]['breakdown'][$stageName][(int)$r->game_number] = (int)$r->score;
                    $players[$key]['stage_totals'][$stageName] += (int)$r->score;
                }
            } else {
                $players[$key]['breakdown'][$stageName][(int)$r->game_number] = (int)$r->score;
                $players[$key]['stage_totals'][$stageName] += (int)$r->score;
            }
        }

        // 現在ステージに1G以上ある選手のみ
        $players = array_values(array_filter($players, function ($p) use ($stage) {
            $arr = $p['breakdown'][$stage] ?? [];
            return count(array_filter($arr, fn($v)=>$v !== null && $v !== '' && (int)$v > 0)) > 0;
        }));

        // 合計計算（キャリー対象のみ足す）
        foreach ($players as &$p) {
            $p['total'] = 0;
            foreach ($carryStages as $cs) $p['total'] += ($p['stage_totals'][$cs] ?? 0);
            $p['total'] += ($p['stage_totals'][$stage] ?? 0);

            $gamesCount = 0;
            foreach ($carryStages as $cs) $gamesCount += count($p['breakdown'][$cs] ?? []);
            $gamesCount += min($uptoGame, count($p['breakdown'][$stage] ?? []));

            $p['baseline_points'] = $gamesCount * $perPoint;
            $p['over_under']      = $p['total'] - $p['baseline_points'];
        }
        unset($p);

        usort($players, function ($a, $b) {
            if ($a['total'] === $b['total']) return 0;
            return ($a['total'] > $b['total']) ? -1 : 1;
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
            $cnt = 0; foreach ($players as $p) if ($p['total'] >= $borderValue) $cnt++;
            if ($cnt > 0) $borderIndex = $cnt - 1;
        }

        // ヘッダー用「基準G数」
        $carryBaseG = 0;
        foreach ($carryStages as $cs) $carryBaseG += (int)($stageSettings[$cs] ?? 0);
        $headerBaseGames = $uptoGame + $carryBaseG;

        return [
            'meta' => [
                'tournament'      => $tournament,
                'stage'           => $stage,
                'upto_game'       => $uptoGame,
                'carry_prelim'    => $carryPrelim ? 1 : 0,
                'carry_semifinal' => $carrySemifinal ? 1 : 0, // ★追加
                'per_point'       => $perPoint,
                'includeStages'   => $includeStages,
                'carryStages'     => $carryStages,
                'border_type'     => $borderType,
                'border_value'    => $borderValue,
                'baseline_games'  => $headerBaseGames,
            ],
            'rows'         => $players,
            'border_index' => $borderIndex,
        ];
    }

    private function digitsOnly(?string $s): string
    {
        if (!$s) return '';
        if (preg_match('/\d+/', $s, $m)) return $m[0];
        return '';
    }

    private function normKey(GameScore $r): string
    {
        $g = trim((string)$r->gender);
        $digits = $this->digitsOnly($r->license_number);
        if ($digits !== '') return ($g !== '' ? ($g.'-') : '') . $digits;

        if ($r->entry_number)   return ($g !== '' ? ($g.'-') : '') . trim($r->entry_number);
        if ($r->name)           return ($g !== '' ? ($g.'-') : '') . trim($r->name);
        return '';
    }
}
