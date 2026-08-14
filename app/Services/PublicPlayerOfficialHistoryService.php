<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\TournamentResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublicPlayerOfficialHistoryService
{
    /**
     * @return array{
     *   annual_records:\Illuminate\Support\Collection<int,array<string,mixed>>,
     *   tournament_history_by_year:\Illuminate\Support\Collection<int,\Illuminate\Support\Collection<int,array<string,mixed>>>
     * }
     */
    public function build(ProBowler $bowler): array
    {
        if (
            ! Schema::hasTable('pro_bowler_annual_records')
            || ! Schema::hasTable('pro_bowler_tournament_histories')
        ) {
            return [
                'annual_records' => collect(),
                'tournament_history_by_year' => collect(),
            ];
        }

        return [
            'annual_records' => $this->annualRecords($bowler),
            'tournament_history_by_year' => $this->tournamentHistory($bowler),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function annualRecords(ProBowler $bowler): Collection
    {
        $records = $bowler->annualRecords()
            ->get()
            ->map(fn ($record): array => [
                'season_key' => $record->season_key,
                'season_start_year' => $record->season_start_year,
                'season_end_year' => $record->season_end_year,
                'ranking_rank' => $record->ranking_rank,
                'games' => $record->games,
                'total_pin' => $record->total_pin,
                'points' => $record->points,
                'average' => $record->average,
                'prize_money' => $record->prize_money,
                'is_live_ranking' => false,
            ])
            ->keyBy('season_key');

        $gender = match ((int) $bowler->sex) {
            1 => 'M',
            2 => 'F',
            default => null,
        };

        if ($gender !== null && Schema::hasTable('pro_bowler_ranking_snapshots')) {
            $snapshots = ProBowlerRankingSnapshot::query()
                ->where('gender', $gender)
                ->where('ranking_type', 'points')
                ->where('ranking_scope', 'official_tournament')
                ->whereHas('rows', fn ($query) => $query->where(
                    'pro_bowler_id',
                    $bowler->id
                ))
                ->with(['rows' => fn ($query) => $query->where(
                    'pro_bowler_id',
                    $bowler->id
                )])
                ->orderByDesc('as_of_date')
                ->orderByDesc('id')
                ->get()
                ->unique('ranking_year');

            $currentYear = (int) now()->year;
            $overlaidSeasonKeys = [];

            foreach ($snapshots as $snapshot) {
                $rankingYear = (int) $snapshot->ranking_year;
                $rankingRow = $snapshot->rows->first();

                if ($rankingRow === null) {
                    continue;
                }

                $matchingRecord = $records->first(fn (array $record): bool => (
                    $record['season_start_year'] <= $rankingYear
                    && $record['season_end_year'] >= $rankingYear
                ));
                $seasonKey = $matchingRecord['season_key'] ?? (string) $rankingYear;

                if (isset($overlaidSeasonKeys[$seasonKey])) {
                    continue;
                }

                $overlaidSeasonKeys[$seasonKey] = true;
                $records->put($seasonKey, [
                    'season_key' => $seasonKey,
                    'season_start_year' => $matchingRecord['season_start_year'] ?? $rankingYear,
                    'season_end_year' => $matchingRecord['season_end_year'] ?? $rankingYear,
                    'ranking_rank' => $rankingRow->ranking_rank,
                    'games' => $rankingRow->games,
                    'total_pin' => $rankingRow->total_pin,
                    'points' => $rankingRow->points,
                    'average' => $rankingRow->average,
                    'prize_money' => $rankingRow->prize_money,
                    'is_live_ranking' => (
                        $rankingYear === $currentYear
                        && ! (bool) $snapshot->is_final
                    ),
                    'ranking_as_of_date' => $snapshot->as_of_date?->format('Y-m-d'),
                ]);
            }
        }

        return $records
            ->sort(function (array $left, array $right): int {
                return [
                    $right['season_end_year'],
                    $right['season_start_year'],
                ] <=> [
                    $left['season_end_year'],
                    $left['season_start_year'],
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int,\Illuminate\Support\Collection<int,array<string,mixed>>>
     */
    private function tournamentHistory(ProBowler $bowler): Collection
    {
        $legacy = $bowler->tournamentHistories()
            ->get()
            ->map(fn ($record): array => [
                'season_year' => $record->season_year,
                'held_on' => $record->held_on?->format('Y-m-d'),
                'tournament_name' => $record->tournament_name,
                'ranking_rank' => $record->ranking_rank,
                'average' => $record->average,
                'prize_money' => $record->prize_money,
                'source_type' => 'official_profile',
            ]);

        $local = TournamentResult::query()
            ->with('tournament')
            ->where('pro_bowler_id', $bowler->id)
            ->whereHas('tournament', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('counts_for_official_points', true)
                        ->orWhere('title_scope', 'season_trial')
                        ->orWhere('title_category', 'season_trial');
                });
            })
            ->get()
            ->filter(fn ($result) => $result->tournament?->start_date !== null)
            ->map(function ($result): array {
                $tournament = $result->tournament;
                $heldOn = $tournament->start_date->format('Y-m-d');

                return [
                    'season_year' => (int) (
                        $result->ranking_year
                        ?: $tournament->year
                        ?: $tournament->start_date->format('Y')
                    ),
                    'held_on' => $heldOn,
                    'tournament_name' => $tournament->name,
                    'ranking_rank' => $result->ranking,
                    'average' => $result->average,
                    'prize_money' => $result->prize_money,
                    'source_type' => 'tournament_result',
                ];
            });

        $localDates = $local
            ->pluck('held_on')
            ->filter()
            ->flip();

        return $legacy
            ->reject(fn (array $record): bool => $localDates->has($record['held_on']))
            ->concat($local)
            ->sortBy([
                ['season_year', 'desc'],
                ['held_on', 'asc'],
                ['tournament_name', 'asc'],
            ])
            ->groupBy('season_year')
            ->map(fn (Collection $records) => $records->values());
    }
}
