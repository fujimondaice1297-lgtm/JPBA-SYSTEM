<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

final class RoundRobinService
{
    public function build(array $opt): array
    {
        $tournamentId = (int) ($opt['tournament_id'] ?? 0);
        $uptoGame = max(1, (int) ($opt['upto_game'] ?? 1));
        $shift = trim((string) ($opt['shift'] ?? ''));
        $gender = trim((string) ($opt['gender'] ?? ''));

        $tournament = Tournament::findOrFail($tournamentId);
        $flowType = trim((string) ($tournament->result_flow_type ?? 'legacy_standard')) ?: 'legacy_standard';

        $qualifierCount = (int) ($tournament->round_robin_qualifier_count ?? 8);
        $qualifierCount = max(4, $qualifierCount);
        if ($qualifierCount % 2 !== 0) {
            $qualifierCount--;
        }

        $winBonus = (int) ($tournament->round_robin_win_bonus ?? 30);
        $tieBonus = (int) ($tournament->round_robin_tie_bonus ?? 15);

        $detectedMaxGame = $this->detectExistingMaxRoundRobinGame($tournamentId, $gender, $shift);
        $positionRoundEnabled = (bool) ($tournament->round_robin_position_round_enabled ?? false);

        // DB上に8Gが既に存在するなら、ポジションマッチ有効として扱う
        if (!$positionRoundEnabled && $detectedMaxGame >= $qualifierCount) {
            $positionRoundEnabled = true;
        }

        $configuredRoundRobinGames = ($qualifierCount - 1) + ($positionRoundEnabled ? 1 : 0);
        $roundRobinGames = max($configuredRoundRobinGames, $detectedMaxGame, 1);
        $uptoGame = min($uptoGame, $roundRobinGames);

        $carrySnapshotCode = $flowType === 'prelim_to_quarterfinal_to_rr_to_final'
            ? 'quarterfinal_total'
            : 'prelim_total';

        $carryRows = $this->loadCarryRows($tournamentId, $carrySnapshotCode, $gender, $shift, $qualifierCount);
        $players = $this->seedPlayers($carryRows);

        if ($players === []) {
            return [
                'meta' => [
                    'qualifier_count' => $qualifierCount,
                    'win_bonus' => $winBonus,
                    'tie_bonus' => $tieBonus,
                    'position_round_enabled' => $positionRoundEnabled,
                    'round_robin_games' => $roundRobinGames,
                    'upto_game' => $uptoGame,
                    'carry_snapshot_code' => $carrySnapshotCode,
                    'stage_label' => 'ラウンドロビン',
                ],
                'players' => [],
                'matrix' => [],
                'rounds' => [],
                'missing_carry_snapshot' => true,
            ];
        }

        $preRounds = $this->buildCircleRounds(array_keys($players));
        $roundScores = $this->loadRoundRobinScores($tournamentId, $players, $gender, $shift, $uptoGame);

        $interim = $this->applyRounds(
            $players,
            $preRounds,
            $roundScores,
            min($uptoGame, count($preRounds)),
            $winBonus,
            $tieBonus
        );

        $positionRound = [];
        if ($positionRoundEnabled) {
            $positionRound = $this->buildPositionRound($interim['players'], count($preRounds) + 1);

            if ($uptoGame >= count($preRounds) + 1) {
                $interim = $this->applyRounds(
                    $interim['players'],
                    [$positionRound],
                    $roundScores,
                    1,
                    $winBonus,
                    $tieBonus
                );
            }
        }

        $rankHistory = $this->buildRankHistory(
            $players,
            $preRounds,
            $positionRoundEnabled ? [$positionRound] : [],
            $roundScores,
            $winBonus,
            $tieBonus,
            $uptoGame
        );

        $finalPlayers = $this->finalizePlayers($interim['players'], $rankHistory);
        $matrix = $this->buildMatrix(
            $finalPlayers,
            $preRounds,
            $positionRoundEnabled ? [$positionRound] : [],
            $roundScores,
            $winBonus,
            $tieBonus,
            $uptoGame
        );

        return [
            'meta' => [
                'qualifier_count' => $qualifierCount,
                'win_bonus' => $winBonus,
                'tie_bonus' => $tieBonus,
                'position_round_enabled' => $positionRoundEnabled,
                'round_robin_games' => $roundRobinGames,
                'upto_game' => $uptoGame,
                'carry_snapshot_code' => $carrySnapshotCode,
                'stage_label' => 'ラウンドロビン',
            ],
            'players' => $finalPlayers,
            'matrix' => $matrix,
            'rounds' => array_merge($preRounds, $positionRoundEnabled ? [$positionRound] : []),
            'missing_carry_snapshot' => false,
        ];
    }

    private function detectExistingMaxRoundRobinGame(int $tournamentId, string $gender, string $shift): int
    {
        $maxGame = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'ラウンドロビン')
            ->when($gender !== '', fn ($q) => $q->where(function ($w) use ($gender) {
                $w->where('gender', $gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            }))
            ->when($shift !== '', fn ($q) => $q->where('shift', $shift))
            ->max('game_number');

        return max((int) $maxGame, 0);
    }

    private function loadCarryRows(int $tournamentId, string $resultCode, string $gender, string $shift, int $limit): array
    {
        $snapshot = $this->findCarrySnapshot($tournamentId, $resultCode, $gender, $shift);

        if (!$snapshot) {
            return [];
        }

        return DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshot->id)
            ->orderBy('ranking')
            ->limit($limit)
            ->get([
                'ranking',
                'pro_bowler_id',
                'pro_bowler_license_no',
                'display_name',
                'amateur_name',
                'total_pin',
                'games',
                'average',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function findCarrySnapshot(int $tournamentId, string $resultCode, string $gender, string $shift): ?object
    {
        $gender = trim($gender) !== '' ? trim($gender) : null;
        $shift = trim($shift) !== '' ? trim($shift) : null;

        $candidates = [];

        $push = function (?string $candidateGender, ?string $candidateShift) use (&$candidates): void {
            $key = ($candidateGender ?? '__NULL__') . '|' . ($candidateShift ?? '__NULL__');
            if (!isset($candidates[$key])) {
                $candidates[$key] = [
                    'gender' => $candidateGender,
                    'shift' => $candidateShift,
                ];
            }
        };

        $push($gender, $shift);

        if ($gender !== null && $shift !== null) {
            $push($gender, null);
            $push(null, $shift);
        }

        if ($gender !== null || $shift !== null) {
            $push(null, null);
        }

        foreach (array_values($candidates) as $candidate) {
            $query = DB::table('tournament_result_snapshots')
                ->where('tournament_id', $tournamentId)
                ->where('result_code', $resultCode)
                ->where('is_current', true);

            if ($candidate['gender'] === null) {
                $query->whereNull('gender');
            } else {
                $query->where('gender', $candidate['gender']);
            }

            if ($candidate['shift'] === null) {
                $query->whereNull('shift');
            } else {
                $query->where('shift', $candidate['shift']);
            }

            $snapshot = $query
                ->orderByDesc('reflected_at')
                ->first();

            if ($snapshot) {
                return $snapshot;
            }
        }

        return null;
    }

    private function seedPlayers(array $carryRows): array
    {
        $players = [];

        foreach ($carryRows as $index => $row) {
            $seed = $index + 1;
            $name = trim((string) ($row['display_name'] ?? $row['amateur_name'] ?? '')) ?: ('Seed ' . $seed);
            $license = trim((string) ($row['pro_bowler_license_no'] ?? ''));
            $playerKey = $this->participantKey((int) ($row['pro_bowler_id'] ?? 0), $license, $name, $seed);

            $players[$seed] = [
                'seed' => $seed,
                'participant_key' => $playerKey,
                'aliases' => $this->participantAliases((int) ($row['pro_bowler_id'] ?? 0), $license, $name, $seed),
                'pro_bowler_id' => (int) ($row['pro_bowler_id'] ?? 0) ?: null,
                'license_no' => $license !== '' ? $license : null,
                'display_name' => $name,
                'carry_pin' => (int) ($row['total_pin'] ?? 0),
                'carry_games' => (int) ($row['games'] ?? 0),
                'rr_scores' => [],
                'rr_total_pin' => 0,
                'wins' => 0,
                'losses' => 0,
                'ties' => 0,
                'bonus_points' => 0,
                'rr_total_points' => 0,
                'overall_total_points' => (int) ($row['total_pin'] ?? 0),
            ];
        }

        return $players;
    }

    private function loadRoundRobinScores(int $tournamentId, array $players, string $gender, string $shift, int $uptoGame): array
    {
        $aliasMap = [];

        foreach ($players as $player) {
            foreach ((array) ($player['aliases'] ?? []) as $alias) {
                if ($alias !== '' && !isset($aliasMap[$alias])) {
                    $aliasMap[$alias] = $player['participant_key'];
                }
            }
        }

        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'ラウンドロビン')
            ->where('game_number', '<=', $uptoGame)
            ->when($gender !== '', fn ($q) => $q->where(function ($w) use ($gender) {
                $w->where('gender', $gender)
                    ->orWhereNull('gender')
                    ->orWhere('gender', '');
            }))
            ->when($shift !== '', fn ($q) => $q->where('shift', $shift))
            ->orderBy('game_number')
            ->get([
                'pro_bowler_id',
                'license_number',
                'name',
                'game_number',
                'score',
            ]);

        $scores = [];

        foreach ($rows as $row) {
            $playerKey = null;

            foreach ($this->scoreRowAliases(
                (int) ($row->pro_bowler_id ?? 0),
                trim((string) ($row->license_number ?? '')),
                trim((string) ($row->name ?? ''))
            ) as $alias) {
                if (isset($aliasMap[$alias])) {
                    $playerKey = $aliasMap[$alias];
                    break;
                }
            }

            if (!$playerKey) {
                continue;
            }

            $scores[$playerKey][(int) $row->game_number] = (int) $row->score;
        }

        return $scores;
    }

    private function buildCircleRounds(array $seeds): array
    {
        $slots = $seeds;

        if (count($slots) % 2 !== 0) {
            $slots[] = null;
        }

        $rounds = [];
        $count = count($slots);
        $half = (int) ($count / 2);

        for ($round = 1; $round <= $count - 1; $round++) {
            $pairs = [];

            for ($i = 0; $i < $half; $i++) {
                $a = $slots[$i];
                $b = $slots[$count - 1 - $i];

                if ($a === null || $b === null) {
                    continue;
                }

                $pairs[] = [
                    'game_number' => $round,
                    'left_seed' => (int) $a,
                    'right_seed' => (int) $b,
                    'label' => $round . 'G',
                ];
            }

            $rounds[] = $pairs;

            $fixed = array_shift($slots);
            $moved = array_pop($slots);
            array_unshift($slots, $fixed);
            array_splice($slots, 1, 0, [$moved]);
        }

        return $rounds;
    }

    private function applyRounds(array $players, array $rounds, array $roundScores, int $roundCount, int $winBonus, int $tieBonus): array
    {
        $activeRounds = array_slice($rounds, 0, $roundCount);

        foreach ($activeRounds as $pairs) {
            foreach ($pairs as $pair) {
                $leftSeed = (int) $pair['left_seed'];
                $rightSeed = (int) $pair['right_seed'];
                $gameNumber = (int) ($pair['game_number'] ?? 0);

                if ($gameNumber <= 0) {
                    continue;
                }

                $leftKey = $players[$leftSeed]['participant_key'];
                $rightKey = $players[$rightSeed]['participant_key'];

                $leftScore = $roundScores[$leftKey][$gameNumber] ?? null;
                $rightScore = $roundScores[$rightKey][$gameNumber] ?? null;

                if ($leftScore === null || $rightScore === null) {
                    continue;
                }

                $players[$leftSeed]['rr_scores'][$gameNumber] = $leftScore;
                $players[$rightSeed]['rr_scores'][$gameNumber] = $rightScore;
                $players[$leftSeed]['rr_total_pin'] += $leftScore;
                $players[$rightSeed]['rr_total_pin'] += $rightScore;

                if ($leftScore > $rightScore) {
                    $players[$leftSeed]['wins']++;
                    $players[$rightSeed]['losses']++;
                    $players[$leftSeed]['bonus_points'] += $winBonus;
                } elseif ($leftScore < $rightScore) {
                    $players[$rightSeed]['wins']++;
                    $players[$leftSeed]['losses']++;
                    $players[$rightSeed]['bonus_points'] += $winBonus;
                } else {
                    $players[$leftSeed]['ties']++;
                    $players[$rightSeed]['ties']++;
                    $players[$leftSeed]['bonus_points'] += $tieBonus;
                    $players[$rightSeed]['bonus_points'] += $tieBonus;
                }
            }
        }

        foreach ($players as &$player) {
            $player['rr_total_points'] = $player['rr_total_pin'] + $player['bonus_points'];
            $player['overall_total_points'] = $player['carry_pin'] + $player['rr_total_points'];
        }
        unset($player);

        uasort($players, fn (array $a, array $b) => $this->comparePlayers($a, $b));

        return ['players' => $players];
    }

    private function buildPositionRound(array $players, int $gameNumber): array
    {
        $orderedSeeds = array_values(array_map(fn (array $player) => (int) $player['seed'], $players));
        $pairs = [];

        for ($i = 0; $i < count($orderedSeeds); $i += 2) {
            if (!isset($orderedSeeds[$i + 1])) {
                continue;
            }

            $pairs[] = [
                'game_number' => $gameNumber,
                'left_seed' => $orderedSeeds[$i],
                'right_seed' => $orderedSeeds[$i + 1],
                'label' => 'ポジションマッチ',
            ];
        }

        return $pairs;
    }

    private function buildRankHistory(array $seedPlayers, array $preRounds, array $positionRounds, array $roundScores, int $winBonus, int $tieBonus, int $uptoGame): array
    {
        $history = [];
        $players = $seedPlayers;
        $gameNo = 0;

        foreach ($preRounds as $pairs) {
            $gameNo++;
            if ($gameNo > $uptoGame) {
                break;
            }

            $players = $this->applyRounds(
                $players,
                [$pairs],
                $roundScores,
                1,
                $winBonus,
                $tieBonus
            )['players'];

            $history[$gameNo] = $this->extractRanks($players);
        }

        if ($gameNo < $uptoGame && $positionRounds !== []) {
            $players = $this->applyRounds(
                $players,
                $positionRounds,
                $roundScores,
                1,
                $winBonus,
                $tieBonus
            )['players'];

            $history[count($preRounds) + 1] = $this->extractRanks($players);
        }

        return $history;
    }

    private function finalizePlayers(array $players, array $rankHistory): array
    {
        $final = [];
        $rank = 1;

        foreach ($players as $player) {
            $gamesPlayed = count($player['rr_scores']);
            $player['rank'] = $rank++;
            $player['rr_average'] = $gamesPlayed > 0 ? round($player['rr_total_pin'] / $gamesPlayed, 2) : null;
            $player['record'] = $player['wins'] . '-' . $player['losses'] . '-' . $player['ties'];
            $player['rank_history'] = [];

            foreach ($rankHistory as $gameNumber => $history) {
                $player['rank_history'][$gameNumber] = $history[$player['seed']] ?? null;
            }

            $final[] = $player;
        }

        return $final;
    }

    private function buildMatrix(array $players, array $preRounds, array $positionRounds, array $roundScores, int $winBonus, int $tieBonus, int $uptoGame): array
    {
        $index = [];
        foreach ($players as $player) {
            $index[$player['seed']] = $player;
        }

        $cells = [];
        $allRounds = array_merge($preRounds, $positionRounds);

        foreach ($allRounds as $pairs) {
            foreach ($pairs as $pair) {
                $gameNumber = (int) ($pair['game_number'] ?? 0);

                if ($gameNumber <= 0 || $gameNumber > $uptoGame) {
                    continue;
                }

                $leftSeed = (int) $pair['left_seed'];
                $rightSeed = (int) $pair['right_seed'];
                $leftKey = $index[$leftSeed]['participant_key'] ?? null;
                $rightKey = $index[$rightSeed]['participant_key'] ?? null;

                if (!$leftKey || !$rightKey) {
                    continue;
                }

                $leftScore = $roundScores[$leftKey][$gameNumber] ?? null;
                $rightScore = $roundScores[$rightKey][$gameNumber] ?? null;

                $cells[$leftSeed][$rightSeed][] = $this->buildCellEntry($pair['label'], $leftScore, $rightScore, $winBonus, $tieBonus);
                $cells[$rightSeed][$leftSeed][] = $this->buildCellEntry($pair['label'], $rightScore, $leftScore, $winBonus, $tieBonus);
            }
        }

        return $cells;
    }

    private function buildCellEntry(string $label, ?int $selfScore, ?int $oppScore, int $winBonus, int $tieBonus): array
    {
        if ($selfScore === null || $oppScore === null) {
            return [
                'label' => $label,
                'text' => '未入力',
                'result' => '',
                'bonus' => 0,
            ];
        }

        if ($selfScore > $oppScore) {
            $result = '○';
            $bonus = $winBonus;
        } elseif ($selfScore < $oppScore) {
            $result = '×';
            $bonus = 0;
        } else {
            $result = '△';
            $bonus = $tieBonus;
        }

        return [
            'label' => $label,
            'text' => $selfScore . ' - ' . $oppScore,
            'result' => $result,
            'bonus' => $bonus,
        ];
    }

    private function comparePlayers(array $a, array $b): int
    {
        if ($a['overall_total_points'] !== $b['overall_total_points']) {
            return $b['overall_total_points'] <=> $a['overall_total_points'];
        }
        if ($a['rr_total_points'] !== $b['rr_total_points']) {
            return $b['rr_total_points'] <=> $a['rr_total_points'];
        }
        if ($a['rr_total_pin'] !== $b['rr_total_pin']) {
            return $b['rr_total_pin'] <=> $a['rr_total_pin'];
        }
        if ($a['carry_pin'] !== $b['carry_pin']) {
            return $b['carry_pin'] <=> $a['carry_pin'];
        }

        return $a['seed'] <=> $b['seed'];
    }

    private function extractRanks(array $players): array
    {
        $ranks = [];
        $rank = 1;

        foreach ($players as $player) {
            $ranks[(int) $player['seed']] = $rank++;
        }

        return $ranks;
    }

    private function participantKey(int $proBowlerId, string $licenseNo, string $displayName, int $seed): string
    {
        if ($proBowlerId > 0) {
            return 'pro:' . $proBowlerId;
        }

        $digits = preg_replace('/\D+/', '', $licenseNo);
        if ($digits !== '') {
            return 'licfull:' . $digits;
        }

        $name = preg_replace('/\s+/u', '', $displayName);
        if ($name !== '') {
            return 'name:' . $name;
        }

        return 'seed:' . $seed;
    }

    private function participantAliases(int $proBowlerId, string $licenseNo, string $displayName, int $seed): array
    {
        $aliases = [];
        $digits = preg_replace('/\D+/', '', $licenseNo);
        $last4 = $digits !== ''
            ? substr(str_pad($digits, 4, '0', STR_PAD_LEFT), -4)
            : '';

        if ($proBowlerId > 0) {
            $aliases[] = 'pro:' . $proBowlerId;
        }
        if ($digits !== '') {
            $aliases[] = 'licfull:' . $digits;
            $aliases[] = 'lic:' . $last4;
        }

        $name = preg_replace('/\s+/u', '', $displayName);
        if ($name !== '') {
            $aliases[] = 'name:' . $name;
        }

        if (empty($aliases)) {
            $aliases[] = 'seed:' . $seed;
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    private function scoreRowAliases(int $proBowlerId, string $licenseNo, string $displayName): array
    {
        $aliases = [];
        $digits = preg_replace('/\D+/', '', $licenseNo);
        $last4 = $digits !== ''
            ? substr(str_pad($digits, 4, '0', STR_PAD_LEFT), -4)
            : '';

        if ($proBowlerId > 0) {
            $aliases[] = 'pro:' . $proBowlerId;
        }
        if ($digits !== '') {
            $aliases[] = 'licfull:' . $digits;
            $aliases[] = 'lic:' . $last4;
        }

        $name = preg_replace('/\s+/u', '', $displayName);
        if ($name !== '') {
            $aliases[] = 'name:' . $name;
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}