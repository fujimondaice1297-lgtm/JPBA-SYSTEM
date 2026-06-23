<?php

namespace App\Services;

use App\Models\Tournament;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentAutomationReadinessService
{
    public function build(Tournament $tournament): array
    {
        $tournamentId = (int) $tournament->id;
        $tournamentYear = $this->resolveTournamentYear($tournament);
        $gender = $this->normalizeGender($tournament->gender ?? null);

        $entries = $this->entrySummary($tournamentId);
        $scores = $this->scoreSummary($tournamentId);
        $snapshots = $this->snapshotSummary($tournamentId);
        $results = $this->resultSummary($tournamentId);
        $awards = $this->awardSummary($tournamentId);
        $titles = $this->titleSummary($tournamentId);
        $seeds = $this->seedSummary($tournamentId, $tournamentYear, $gender);

        return [
            'tournament_year' => $tournamentYear,
            'gender' => $gender,
            'entries' => $entries,
            'scores' => $scores,
            'snapshots' => $snapshots,
            'results' => $results,
            'awards' => $awards,
            'titles' => $titles,
            'seeds' => $seeds,
            'readiness' => $this->readiness($entries, $scores, $snapshots, $results, $awards, $titles, $seeds),
        ];
    }

    private function readiness(
        array $entries,
        array $scores,
        array $snapshots,
        array $results,
        array $awards,
        array $titles,
        array $seeds
    ): array {
        $hasFinalResults = $results['final_results_count'] > 0;
        $hasDistribution = $awards['point_distribution_count'] > 0 || $awards['prize_distribution_count'] > 0;
        $hasAppliedAward = $awards['point_applied_rows'] > 0 || $awards['prize_applied_rows'] > 0;
        $hasSeedSource = $seeds['annual_seed_player_count'] > 0 || $seeds['tournament_seed_player_count'] > 0;

        return [
            'entries' => $entries['entry_count'] > 0 ? 'done' : 'waiting',
            'scores' => $scores['score_count'] > 0 ? 'done' : 'waiting',
            'snapshots' => $snapshots['full_final_snapshot'] !== null
                ? 'done'
                : ($snapshots['current_snapshot_count'] > 0 ? 'warning' : 'waiting'),
            'results' => $hasFinalResults ? 'done' : 'waiting',
            'awards' => ! $hasFinalResults
                ? 'waiting'
                : ($hasDistribution ? ($hasAppliedAward ? 'done' : 'ready') : 'warning'),
            'titles' => ! $hasFinalResults ? 'waiting' : ($titles['title_count'] > 0 ? 'done' : 'ready'),
            'seeds' => $hasSeedSource ? 'done' : 'warning',
            'pdf' => $hasFinalResults ? 'ready' : 'waiting',
        ];
    }

    private function entrySummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_entries')) {
            return [
                'entry_count' => 0,
                'checked_in_count' => 0,
            ];
        }

        $base = DB::table('tournament_entries')->where('tournament_id', $tournamentId);

        return [
            'entry_count' => (int) (clone $base)->where('status', 'entry')->count(),
            'checked_in_count' => Schema::hasColumn('tournament_entries', 'checked_in_at')
                ? (int) (clone $base)->whereNotNull('checked_in_at')->count()
                : 0,
        ];
    }

    private function scoreSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('game_scores')) {
            return [
                'score_count' => 0,
                'scored_player_count' => 0,
                'stage_rows' => [],
            ];
        }

        $base = DB::table('game_scores')->where('tournament_id', $tournamentId);

        $identityRows = (clone $base)
            ->select(['tournament_participant_id', 'pro_bowler_id', 'license_number', 'name'])
            ->get();

        $scoredPlayerCount = $identityRows
            ->map(function ($row): string {
                if (! empty($row->tournament_participant_id)) {
                    return 'participant:' . $row->tournament_participant_id;
                }

                if (! empty($row->pro_bowler_id)) {
                    return 'pro:' . $row->pro_bowler_id;
                }

                $license = trim((string) ($row->license_number ?? ''));
                if ($license !== '') {
                    return 'license:' . strtoupper($license);
                }

                return 'name:' . trim((string) ($row->name ?? ''));
            })
            ->filter(fn (string $key): bool => $key !== 'name:')
            ->unique()
            ->count();

        $stageRows = (clone $base)
            ->select([
                'stage',
                DB::raw('COUNT(*) as rows_count'),
                DB::raw('COUNT(DISTINCT game_number) as games_count'),
                DB::raw('MAX(game_number) as max_game'),
            ])
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn ($row): array => [
                'stage' => trim((string) ($row->stage ?? '')) ?: '未設定',
                'rows_count' => (int) $row->rows_count,
                'games_count' => (int) $row->games_count,
                'max_game' => (int) $row->max_game,
            ])
            ->all();

        return [
            'score_count' => (int) (clone $base)->count(),
            'scored_player_count' => (int) $scoredPlayerCount,
            'stage_rows' => $stageRows,
        ];
    }

    private function snapshotSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_result_snapshots')) {
            return [
                'current_snapshot_count' => 0,
                'current_final_snapshot_count' => 0,
                'full_final_snapshot' => null,
                'full_final_row_count' => 0,
            ];
        }

        $currentBase = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournamentId)
            ->where('is_current', true);

        $fullFinalSnapshot = (clone $currentBase)
            ->where('is_final', true)
            ->whereNull('gender')
            ->whereNull('shift')
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->first();

        $fullFinalRowCount = $fullFinalSnapshot && Schema::hasTable('tournament_result_snapshot_rows')
            ? (int) DB::table('tournament_result_snapshot_rows')
                ->where('snapshot_id', (int) $fullFinalSnapshot->id)
                ->count()
            : 0;

        return [
            'current_snapshot_count' => (int) (clone $currentBase)->count(),
            'current_final_snapshot_count' => (int) (clone $currentBase)->where('is_final', true)->count(),
            'full_final_snapshot' => $fullFinalSnapshot,
            'full_final_row_count' => $fullFinalRowCount,
        ];
    }

    private function resultSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_results')) {
            return [
                'final_results_count' => 0,
                'winner_count' => 0,
            ];
        }

        $base = DB::table('tournament_results')->where('tournament_id', $tournamentId);
        $rankColumn = $this->firstExistingColumn('tournament_results', [
            'ranking',
            'rank',
            'position',
            'placing',
            'result_rank',
            'order_no',
        ]);

        return [
            'final_results_count' => (int) (clone $base)->count(),
            'winner_count' => $rankColumn !== null
                ? (int) (clone $base)->where($rankColumn, 1)->count()
                : 0,
        ];
    }

    private function awardSummary(int $tournamentId): array
    {
        $resultBase = Schema::hasTable('tournament_results')
            ? DB::table('tournament_results')->where('tournament_id', $tournamentId)
            : null;

        $pointColumns = $this->existingColumns('tournament_results', ['points', 'award_points', 'step_points']);
        $prizeColumns = $this->existingColumns('tournament_results', ['prize_money']);

        return [
            'point_distribution_count' => Schema::hasTable('point_distributions')
                ? (int) DB::table('point_distributions')->where('tournament_id', $tournamentId)->count()
                : 0,
            'prize_distribution_count' => Schema::hasTable('prize_distributions')
                ? (int) DB::table('prize_distributions')->where('tournament_id', $tournamentId)->count()
                : 0,
            'point_applied_rows' => $resultBase !== null && $pointColumns !== []
                ? $this->countRowsWithAnyPositiveColumn($resultBase, $pointColumns)
                : 0,
            'prize_applied_rows' => $resultBase !== null && $prizeColumns !== []
                ? $this->countRowsWithAnyPositiveColumn($resultBase, $prizeColumns)
                : 0,
        ];
    }

    private function titleSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('pro_bowler_titles')) {
            return [
                'title_count' => 0,
            ];
        }

        return [
            'title_count' => (int) DB::table('pro_bowler_titles')
                ->where('tournament_id', $tournamentId)
                ->count(),
        ];
    }

    private function seedSummary(int $tournamentId, ?int $tournamentYear, ?string $gender): array
    {
        $tournamentSeedCount = Schema::hasTable('tournament_seed_players')
            ? (int) DB::table('tournament_seed_players')
                ->where('tournament_id', $tournamentId)
                ->where('is_active', true)
                ->count()
            : 0;

        $annualSeedCount = 0;

        if (
            Schema::hasTable('pro_bowler_seed_lists')
            && Schema::hasTable('pro_bowler_seed_list_players')
        ) {
            $annualQuery = DB::table('pro_bowler_seed_list_players as players')
                ->join('pro_bowler_seed_lists as lists', 'lists.id', '=', 'players.seed_list_id')
                ->where('lists.is_active', true)
                ->where('players.is_active', true);

            if ($tournamentYear !== null) {
                $annualQuery->where('lists.seed_year', $tournamentYear);
            }

            if ($gender !== null) {
                $annualQuery->where('lists.gender', $gender);
            }

            $annualSeedCount = (int) $annualQuery->count();
        }

        return [
            'annual_seed_player_count' => $annualSeedCount,
            'tournament_seed_player_count' => $tournamentSeedCount,
            'active_seed_source_count' => $annualSeedCount + $tournamentSeedCount,
        ];
    }

    private function countRowsWithAnyPositiveColumn($baseQuery, array $columns): int
    {
        $query = clone $baseQuery;

        $query->where(function ($inner) use ($columns): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, '>', 0);
            }
        });

        return (int) $query->count();
    }

    private function existingColumns(string $table, array $columns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($this->existingColumns($table, $columns) as $column) {
            return $column;
        }

        return null;
    }

    private function resolveTournamentYear(Tournament $tournament): ?int
    {
        if (! empty($tournament->year)) {
            return (int) $tournament->year;
        }

        $startDate = $tournament->start_date ?? null;
        if ($startDate instanceof DateTimeInterface) {
            return (int) $startDate->format('Y');
        }

        $text = trim((string) $startDate);
        if (preg_match('/^\d{4}/', $text, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function normalizeGender(mixed $gender): ?string
    {
        $value = strtoupper(trim((string) $gender));

        return match ($value) {
            'M', 'MALE', 'MAN', 'MEN', '男子' => 'M',
            'F', 'FEMALE', 'WOMAN', 'WOMEN', '女子' => 'F',
            default => null,
        };
    }
}
