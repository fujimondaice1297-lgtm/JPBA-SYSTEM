<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

final class StepLadderService
{
    public function build(array $opt): array
    {
        $tournamentId = (int) ($opt['tournament_id'] ?? 0);
        $uptoGame = max(1, min(2, (int) ($opt['upto_game'] ?? 1)));
        $shift = trim((string) ($opt['shift'] ?? ''));
        $gender = trim((string) ($opt['gender'] ?? ''));

        $tournament = Tournament::findOrFail($tournamentId);
        $seedSnapshot = $this->findRoundRobinSnapshot($tournamentId, $gender, $shift);

        if (!$seedSnapshot) {
            return [
                'meta' => [
                    'upto_game' => $uptoGame,
                    'games' => 2,
                    'stage_label' => '決勝（ステップラダー）',
                ],
                'missing_seed_snapshot' => true,
                'seeds' => [],
                'semifinal' => null,
                'final' => null,
                'standings' => [],
            ];
        }

        $seedRows = DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $seedSnapshot->id)
            ->orderBy('ranking')
            ->limit(3)
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

        if (count($seedRows) < 3) {
            return [
                'meta' => [
                    'upto_game' => $uptoGame,
                    'games' => 2,
                    'stage_label' => '決勝（ステップラダー）',
                ],
                'missing_seed_snapshot' => true,
                'seeds' => [],
                'semifinal' => null,
                'final' => null,
                'standings' => [],
            ];
        }

        $seeds = [];
        foreach ($seedRows as $index => $row) {
            $rank = $index + 1;
            $name = trim((string) ($row['display_name'] ?? $row['amateur_name'] ?? '')) ?: ('Seed ' . $rank);
            $licenseNo = trim((string) ($row['pro_bowler_license_no'] ?? ''));
            $participantKey = $this->participantKey((int) ($row['pro_bowler_id'] ?? 0), $licenseNo, $name, $rank);

            $seeds[$rank] = [
                'seed' => $rank,
                'participant_key' => $participantKey,
                'aliases' => $this->participantAliases((int) ($row['pro_bowler_id'] ?? 0), $licenseNo, $name, $rank),
                'pro_bowler_id' => (int) ($row['pro_bowler_id'] ?? 0) ?: null,
                'license_no' => $licenseNo !== '' ? $licenseNo : null,
                'display_name' => $name,
                'carry_pin' => (int) ($row['total_pin'] ?? 0),
                'carry_games' => (int) ($row['games'] ?? 0),
                'carry_average' => isset($row['average']) ? (float) $row['average'] : null,
            ];
        }

        $scores = $this->loadFinalScores($tournamentId, $seeds, $gender, $shift, $uptoGame);

        $semifinal = [
            'label' => '1回戦',
            'game_number' => 1,
            'top' => $seeds[2],
            'bottom' => $seeds[3],
            'top_score' => $scores[$seeds[2]['participant_key']][1] ?? null,
            'bottom_score' => $scores[$seeds[3]['participant_key']][1] ?? null,
            'winner' => null,
            'loser' => null,
            'status' => 'pending',
        ];

        if ($semifinal['top_score'] !== null && $semifinal['bottom_score'] !== null) {
            if ($semifinal['top_score'] > $semifinal['bottom_score']) {
                $semifinal['winner'] = $seeds[2];
                $semifinal['loser'] = $seeds[3];
                $semifinal['status'] = 'done';
            } elseif ($semifinal['top_score'] < $semifinal['bottom_score']) {
                $semifinal['winner'] = $seeds[3];
                $semifinal['loser'] = $seeds[2];
                $semifinal['status'] = 'done';
            } else {
                $semifinal['status'] = 'tie';
            }
        }

        $finalChallenger = $semifinal['winner'];
        $final = [
            'label' => '優勝決定戦',
            'game_number' => 2,
            'top' => $seeds[1],
            'bottom' => $finalChallenger,
            'top_score' => $scores[$seeds[1]['participant_key']][2] ?? null,
            'bottom_score' => $finalChallenger ? ($scores[$finalChallenger['participant_key']][2] ?? null) : null,
            'winner' => null,
            'loser' => null,
            'status' => $finalChallenger ? 'pending' : 'waiting',
        ];

        if ($finalChallenger && $final['top_score'] !== null && $final['bottom_score'] !== null) {
            if ($final['top_score'] > $final['bottom_score']) {
                $final['winner'] = $seeds[1];
                $final['loser'] = $finalChallenger;
                $final['status'] = 'done';
            } elseif ($final['top_score'] < $final['bottom_score']) {
                $final['winner'] = $finalChallenger;
                $final['loser'] = $seeds[1];
                $final['status'] = 'done';
            } else {
                $final['status'] = 'tie';
            }
        }

        $standings = $this->buildStandings($seeds, $semifinal, $final);

        return [
            'meta' => [
                'upto_game' => $uptoGame,
                'games' => 2,
                'stage_label' => '決勝（ステップラダー）',
                'seed_snapshot_code' => 'round_robin_total',
                'seed_snapshot_id' => $seedSnapshot->id,
            ],
            'missing_seed_snapshot' => false,
            'seeds' => array_values($seeds),
            'semifinal' => $semifinal,
            'final' => $final,
            'standings' => $standings,
        ];
    }

    private function findRoundRobinSnapshot(int $tournamentId, string $gender, string $shift): ?object
    {
        $gender = $gender !== '' ? $gender : null;
        $shift = $shift !== '' ? $shift : null;

        $candidates = [];
        $push = function (?string $candidateGender, ?string $candidateShift) use (&$candidates): void {
            $key = ($candidateGender ?? '__NULL__') . '|' . ($candidateShift ?? '__NULL__');
            if (!isset($candidates[$key])) {
                $candidates[$key] = ['gender' => $candidateGender, 'shift' => $candidateShift];
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
                ->where('result_code', 'round_robin_total')
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

            $snapshot = $query->orderByDesc('reflected_at')->first();
            if ($snapshot) {
                return $snapshot;
            }
        }

        return null;
    }

    private function loadFinalScores(int $tournamentId, array $seeds, string $gender, string $shift, int $uptoGame): array
    {
        $aliasMap = [];
        foreach ($seeds as $seed) {
            foreach ((array) ($seed['aliases'] ?? []) as $alias) {
                if ($alias !== '' && !isset($aliasMap[$alias])) {
                    $aliasMap[$alias] = $seed['participant_key'];
                }
            }
        }

        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', '決勝')
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

    private function buildStandings(array $seeds, array $semifinal, array $final): array
    {
        if (($final['status'] ?? '') === 'done') {
            return [
                ['rank' => 1, 'player' => $final['winner'], 'reason' => '優勝'],
                ['rank' => 2, 'player' => $final['loser'], 'reason' => '準優勝'],
                ['rank' => 3, 'player' => $semifinal['loser'], 'reason' => 'ステップラダー3位'],
            ];
        }

        if (($semifinal['status'] ?? '') === 'done') {
            return [
                ['rank' => null, 'player' => $seeds[1], 'reason' => '決勝進出'],
                ['rank' => null, 'player' => $semifinal['winner'], 'reason' => '決勝進出'],
                ['rank' => 3, 'player' => $semifinal['loser'], 'reason' => 'ステップラダー3位'],
            ];
        }

        return [
            ['rank' => 1, 'player' => $seeds[1], 'reason' => 'RR1位シード'],
            ['rank' => 2, 'player' => $seeds[2], 'reason' => 'RR2位シード'],
            ['rank' => 3, 'player' => $seeds[3], 'reason' => 'RR3位シード'],
        ];
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
