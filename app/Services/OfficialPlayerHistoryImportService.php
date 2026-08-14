<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerAnnualRecord;
use App\Models\ProBowlerOfficialHistoryImport;
use App\Models\ProBowlerTournamentHistory;
use App\Models\ProBowlerTournamentHistorySync;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OfficialPlayerHistoryImportService
{
    public function __construct(
        private readonly JpbaOfficialPlayerProfileService $officialProfiles
    ) {
    }

    /**
     * @param array<string,mixed> $baseProfile
     * @return array<string,mixed>
     */
    public function import(
        ProBowler $bowler,
        array $baseProfile,
        bool $force,
        ?int $yearFrom = null,
        ?int $yearTo = null,
        bool $missingOnly = false,
        int $sleepMs = 250,
        int $historyConcurrency = 1
    ): array {
        if (
            ! Schema::hasTable('pro_bowler_annual_records')
            || ! Schema::hasTable('pro_bowler_tournament_histories')
            || ! Schema::hasTable('pro_bowler_tournament_history_syncs')
            || ! Schema::hasTable('pro_bowler_official_history_imports')
        ) {
            throw new RuntimeException('Official player history tables have not been migrated.');
        }

        $report = [
            'annual_rows_seen' => 0,
            'annual_rows_changed' => 0,
            'tournament_years_seen' => 0,
            'tournament_years_skipped' => 0,
            'tournament_years_completed' => 0,
            'tournament_rows_seen' => 0,
            'tournament_rows_changed' => 0,
            'player_completed' => false,
            'errors' => [],
        ];

        $baseUrl = (string) data_get($baseProfile, 'summary.official_profile_url', '');
        $capturedAt = now();

        foreach ((array) ($baseProfile['annual_records'] ?? []) as $record) {
            $report['annual_rows_seen']++;

            $attributes = [
                'pro_bowler_id' => $bowler->id,
                'season_key' => (string) $record['season_key'],
            ];
            $values = [
                'season_start_year' => (int) $record['season_start_year'],
                'season_end_year' => (int) $record['season_end_year'],
                'ranking_rank' => $record['ranking_rank'],
                'games' => $record['games'],
                'total_pin' => $record['total_pin'],
                'points' => $record['points'],
                'average' => $record['average'],
                'prize_money' => $record['prize_money'],
                'source_type' => 'official_profile',
                'source_url' => $baseUrl !== '' ? $baseUrl : null,
                'captured_at' => $capturedAt,
            ];

            $changed = $this->wouldChangeAnnualRecord($attributes, $values);
            if ($changed) {
                $report['annual_rows_changed']++;
            }

            if ($force && $changed) {
                ProBowlerAnnualRecord::query()->updateOrCreate($attributes, $values);
            }
        }

        $years = collect((array) ($baseProfile['participation_years'] ?? []))
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year) => $year >= 1900 && $year <= 2100)
            ->when($yearFrom !== null, fn ($items) => $items->filter(
                fn (int $year) => $year >= $yearFrom
            ))
            ->when($yearTo !== null, fn ($items) => $items->filter(
                fn (int $year) => $year <= $yearTo
            ))
            ->unique()
            ->sortDesc()
            ->values();

        $baseRecordsByYear = collect((array) ($baseProfile['tournament_records'] ?? []))
            ->groupBy(fn (array $record) => (int) $record['season_year']);
        $yearPayloads = [];
        $yearsToFetch = [];

        foreach ($years as $year) {
            $report['tournament_years_seen']++;

            if (
                $missingOnly
                && ProBowlerTournamentHistorySync::query()
                    ->where('pro_bowler_id', $bowler->id)
                    ->where('season_year', $year)
                    ->exists()
            ) {
                $report['tournament_years_skipped']++;

                continue;
            }

            $records = $baseRecordsByYear->get($year, collect())->values()->all();
            if ($records !== []) {
                $yearPayloads[$year] = [
                    'records' => $records,
                    'source_url' => $baseUrl,
                ];
            } else {
                $yearsToFetch[] = $year;
            }
        }

        if ($yearsToFetch !== []) {
            $fetched = $this->officialProfiles->fetchTournamentYears(
                (string) $bowler->license_no,
                $yearsToFetch,
                $historyConcurrency,
                $sleepMs
            );

            foreach ((array) $fetched['errors'] as $year => $message) {
                $report['errors'][] = [
                    'year' => (int) $year,
                    'message' => (string) $message,
                ];
            }

            foreach ((array) $fetched['profiles'] as $year => $profile) {
                $yearPayloads[(int) $year] = [
                    'records' => array_values(array_filter(
                        (array) ($profile['tournament_records'] ?? []),
                        fn (array $record): bool => (int) $record['season_year'] === (int) $year
                    )),
                    'source_url' => (string) data_get(
                        $profile,
                        'summary.official_profile_url',
                        $baseUrl
                    ),
                ];
            }
        }

        krsort($yearPayloads);

        foreach ($yearPayloads as $year => $payload) {
            $sourceUrl = (string) $payload['source_url'];
            $records = $this->uniqueTournamentRecords(
                $bowler,
                (array) $payload['records']
            );
            foreach ($records as $record) {
                $report['tournament_rows_seen']++;
                $fingerprint = $this->fingerprint($bowler, $record);
                $values = [
                    'pro_bowler_id' => $bowler->id,
                    'season_year' => (int) $record['season_year'],
                    'held_on' => (string) $record['held_on'],
                    'tournament_name' => (string) $record['tournament_name'],
                    'ranking_rank' => $record['ranking_rank'],
                    'average' => $record['average'],
                    'prize_money' => $record['prize_money'],
                    'source_type' => 'official_profile',
                    'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                    'captured_at' => $capturedAt,
                ];

                $model = ProBowlerTournamentHistory::query()
                    ->where('source_fingerprint', $fingerprint)
                    ->first()
                    ?? ProBowlerTournamentHistory::query()
                        ->where(
                            'source_fingerprint',
                            $this->legacyFingerprint($bowler, $record)
                        )
                        ->first()
                    ?? new ProBowlerTournamentHistory();
                $model->fill([
                    'source_fingerprint' => $fingerprint,
                    ...$values,
                ]);
                $comparable = [
                    'source_fingerprint',
                    ...array_values(array_diff(array_keys($values), ['captured_at'])),
                ];
                $changed = ! $model->exists || $model->isDirty($comparable);

                if ($changed) {
                    $report['tournament_rows_changed']++;
                }

                if ($force && $changed) {
                    $model->save();
                }
            }

            if ($force) {
                ProBowlerTournamentHistorySync::query()->updateOrCreate(
                    [
                        'pro_bowler_id' => $bowler->id,
                        'season_year' => (int) $year,
                    ],
                    [
                        'row_count' => count($records),
                        'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                        'captured_at' => $capturedAt,
                    ]
                );
                $report['tournament_years_completed']++;
            }
        }

        if ($force && $report['errors'] === []) {
            $yearValues = $years->all();
            $syncs = $yearValues === []
                ? collect()
                : ProBowlerTournamentHistorySync::query()
                    ->where('pro_bowler_id', $bowler->id)
                    ->whereIn('season_year', $yearValues)
                    ->get();

            if ($syncs->count() === count($yearValues)) {
                ProBowlerOfficialHistoryImport::query()->updateOrCreate(
                    ['pro_bowler_id' => $bowler->id],
                    [
                        'annual_row_count' => count((array) ($baseProfile['annual_records'] ?? [])),
                        'participation_year_count' => count($yearValues),
                        'tournament_row_count' => (int) $syncs->sum('row_count'),
                        'source_url' => $baseUrl !== '' ? $baseUrl : null,
                        'completed_at' => $capturedAt,
                    ]
                );
                $report['player_completed'] = true;
            }
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $attributes
     * @param array<string,mixed> $values
     */
    private function wouldChangeAnnualRecord(array $attributes, array $values): bool
    {
        $model = ProBowlerAnnualRecord::query()->firstOrNew($attributes);
        $model->fill($values);
        $comparable = array_values(array_diff(array_keys($values), ['captured_at']));

        return ! $model->exists || $model->isDirty($comparable);
    }

    /**
     * @param array<string,mixed> $record
     */
    private function fingerprint(ProBowler $bowler, array $record): string
    {
        return hash('sha256', implode('|', [
            $bowler->id,
            (int) $record['season_year'],
            (string) $record['held_on'],
            $this->normalizedTournamentName($record),
            $record['ranking_rank'] === null ? '' : (int) $record['ranking_rank'],
            $record['average'] === null
                ? ''
                : number_format((float) $record['average'], 2, '.', ''),
            $record['prize_money'] === null ? '' : (int) $record['prize_money'],
        ]));
    }

    /**
     * @param array<string,mixed> $record
     */
    private function legacyFingerprint(ProBowler $bowler, array $record): string
    {
        return hash('sha256', implode('|', [
            $bowler->id,
            (int) $record['season_year'],
            (string) $record['held_on'],
            $this->normalizedTournamentName($record),
        ]));
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * @return array<int,array<string,mixed>>
     */
    private function uniqueTournamentRecords(ProBowler $bowler, array $records): array
    {
        $unique = [];

        foreach ($records as $record) {
            $unique[$this->fingerprint($bowler, $record)] = $record;
        }

        return array_values($unique);
    }

    /**
     * @param array<string,mixed> $record
     */
    private function normalizedTournamentName(array $record): string
    {
        $name = mb_convert_kana(
            (string) $record['tournament_name'],
            'asKV',
            'UTF-8'
        );

        return preg_replace('/[\s　・･]+/u', '', mb_strtolower($name)) ?: $name;
    }
}
