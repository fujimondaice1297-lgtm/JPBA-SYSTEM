<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026RankingReconciliationService
{
    private const DATASET_PATH = 'data/jpba_official_2026_results.json';

    /** @return array<string,mixed> */
    public function audit(): array
    {
        $dataset = $this->dataset();
        $publications = DB::table('tournament_result_publications as publications')
            ->join('tournaments', 'tournaments.id', '=', 'publications.tournament_id')
            ->where('publications.status', 'current')
            ->where('tournaments.year', 2026)
            ->where(function ($query): void {
                $query
                    ->where('publications.notes', 'like', 'jpba_official_2026_season_trial_detail:%')
                    ->orWhere('publications.notes', 'like', 'jpba_official_2026_standard_detail:%')
                    ->orWhere('publications.notes', 'like', 'jpba_official_2026_standard_final:%')
                    ->orWhere('publications.notes', 'jpba_official_2026_seahorse_selection');
            })
            ->select([
                'publications.id',
                'publications.tournament_id',
                'publications.notes',
                'tournaments.name',
                'tournaments.counts_for_official_points',
                'tournaments.counts_for_average',
                'tournaments.counts_for_prize',
            ])
            ->get();

        $aggregate = [];
        foreach ($publications as $publication) {
            $rows = DB::table('tournament_result_publication_rows')
                ->where('publication_id', $publication->id)
                ->whereNotNull('pro_bowler_license_no')
                ->get();
            foreach ($rows as $row) {
                $license = strtoupper(trim((string) $row->pro_bowler_license_no));
                $aggregate[$license] ??= [
                    'points' => 0,
                    'prize_money' => 0,
                    'games' => 0,
                    'total_pin' => 0,
                ];
                if ((bool) $publication->counts_for_official_points) {
                    $aggregate[$license]['points'] += (int) $row->points;
                }
                if ((bool) $publication->counts_for_prize) {
                    $aggregate[$license]['prize_money'] += (int) $row->prize_money;
                }
                if ((bool) $publication->counts_for_average) {
                    $aggregate[$license]['games'] += (int) $row->games;
                    $aggregate[$license]['total_pin'] += (int) $row->total_pin;
                }
            }
        }

        $differences = [];
        $officialLicenses = [];
        foreach ($dataset['official_rankings'] as $ranking) {
            foreach ($ranking['rows'] as $official) {
                $license = strtoupper(trim((string) $official['license_no']));
                $officialLicenses[$license] = true;
                $actual = $aggregate[$license] ?? [
                    'points' => 0,
                    'prize_money' => 0,
                    'games' => 0,
                    'total_pin' => 0,
                ];
                $actualAverage = $actual['games'] > 0
                    ? $this->truncateAverage($actual['total_pin'], $actual['games'])
                    : 0.0;
                $expected = [
                    'points' => (int) $official['points'],
                    'prize_money' => (int) $official['prize_money'],
                    'games' => (int) $official['games'],
                    'total_pin' => (int) $official['total_pin'],
                    'average' => round((float) $official['average'], 2),
                ];
                if ($actual['points'] !== $expected['points']
                    || $actual['prize_money'] !== $expected['prize_money']
                    || $actual['games'] !== $expected['games']
                    || $actual['total_pin'] !== $expected['total_pin']
                    || abs($actualAverage - $expected['average']) > 0.01) {
                    $differences[] = [
                        'gender' => $ranking['gender'],
                        'license_no' => $license,
                        'name' => $official['name_kanji'],
                        'expected' => $expected,
                        'actual' => $actual + ['average' => $actualAverage],
                    ];
                }
            }
        }

        foreach ($aggregate as $license => $actual) {
            if (! isset($officialLicenses[$license])
                && array_sum($actual) !== 0) {
                $differences[] = [
                    'gender' => str_starts_with($license, 'F') ? 'F' : 'M',
                    'license_no' => $license,
                    'name' => null,
                    'expected' => null,
                    'actual' => $actual + [
                        'average' => $actual['games'] > 0
                            ? $this->truncateAverage($actual['total_pin'], $actual['games'])
                            : 0.0,
                    ],
                ];
            }
        }

        return [
            'is_complete' => $publications->count() === 24 && $differences === [],
            'publication_count' => $publications->count(),
            'publication_ids' => $publications->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'tournament_ids' => $publications->pluck('tournament_id')->map(fn ($id): int => (int) $id)->all(),
            'aggregated_license_count' => count($aggregate),
            'official_license_count' => count($officialLicenses),
            'difference_count' => count($differences),
            'differences' => $differences,
        ];
    }

    private function truncateAverage(int $totalPin, int $games): float
    {
        return floor((($totalPin / $games) * 100) + 0.0000001) / 100;
    }

    /** @return array<string,mixed> */
    private function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Official ranking dataset was not found: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
