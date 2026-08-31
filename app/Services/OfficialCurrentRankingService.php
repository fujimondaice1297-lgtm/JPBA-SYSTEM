<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class OfficialCurrentRankingService
{
    public const GENDER_LABELS = [
        'M' => '男子',
        'F' => '女子',
    ];

    public const RANKING_TYPE_LABELS = [
        'points' => 'ポイントランキング',
        'prize' => '賞金ランキング',
    ];

    /** @return array<int,int> */
    public function years(): array
    {
        $years = DB::table('tournament_result_publications as publications')
            ->join('tournaments', 'tournaments.id', '=', 'publications.tournament_id')
            ->where('publications.status', 'current')
            ->whereNotNull('tournaments.year')
            ->distinct()
            ->orderByDesc('tournaments.year')
            ->pluck('tournaments.year')
            ->map(fn ($year): int => (int) $year)
            ->all();

        return $years === [] ? [(int) now()->year] : $years;
    }

    /** @return array<string,mixed> */
    public function ranking(int $year, string $gender, string $rankingType): array
    {
        $gender = array_key_exists($gender, self::GENDER_LABELS) ? $gender : 'M';
        $rankingType = array_key_exists($rankingType, self::RANKING_TYPE_LABELS)
            ? $rankingType
            : 'points';

        $sourceRows = DB::table('tournament_result_publication_rows as rows')
            ->join('tournament_result_publications as publications', 'publications.id', '=', 'rows.publication_id')
            ->join('tournaments', 'tournaments.id', '=', 'publications.tournament_id')
            ->join('pro_bowlers as bowlers', 'bowlers.id', '=', 'rows.pro_bowler_id')
            ->where('publications.status', 'current')
            ->where('tournaments.year', $year)
            ->where('bowlers.sex', $gender === 'F' ? 2 : 1)
            ->whereNotNull('rows.pro_bowler_id')
            ->orderBy('tournaments.start_date')
            ->orderBy('tournaments.id')
            ->select([
                'rows.pro_bowler_id',
                'rows.points',
                'rows.prize_money',
                'rows.games',
                'rows.total_pin',
                'publications.published_at',
                'tournaments.id as tournament_id',
                'tournaments.start_date',
                'tournaments.counts_for_official_points',
                'tournaments.counts_for_average',
                'tournaments.counts_for_prize',
                'bowlers.license_no',
                'bowlers.name_kanji',
                'bowlers.kibetsu',
                'bowlers.organization_name',
                'bowlers.equipment_contract',
            ])
            ->get();

        $rows = [];
        $publishedTournamentIds = [];
        $asOfDate = null;

        foreach ($sourceRows as $sourceRow) {
            $bowlerId = (int) $sourceRow->pro_bowler_id;
            $rows[$bowlerId] ??= [
                'rank' => null,
                'pro_bowler_id' => $bowlerId,
                'license_no' => (string) $sourceRow->license_no,
                'name_kanji' => (string) $sourceRow->name_kanji,
                'kibetsu' => $sourceRow->kibetsu === null ? null : (int) $sourceRow->kibetsu,
                'organization_name' => $this->affiliation(
                    $sourceRow->organization_name,
                    $sourceRow->equipment_contract,
                ),
                'tournament_count' => 0,
                'points' => 0,
                'prize_money' => 0,
                'games' => 0,
                'total_pin' => 0,
                'average' => 0.0,
                'tournament_ids' => [],
            ];

            $isRankingOutput = (bool) $sourceRow->counts_for_official_points
                || (bool) $sourceRow->counts_for_average
                || (bool) $sourceRow->counts_for_prize;
            if ($isRankingOutput) {
                $rows[$bowlerId]['tournament_ids'][(int) $sourceRow->tournament_id] = true;
                $publishedTournamentIds[(int) $sourceRow->tournament_id] = true;
            }
            if ((bool) $sourceRow->counts_for_official_points) {
                $rows[$bowlerId]['points'] += (int) $sourceRow->points;
            }
            if ((bool) $sourceRow->counts_for_prize) {
                $rows[$bowlerId]['prize_money'] += (int) $sourceRow->prize_money;
            }
            if ((bool) $sourceRow->counts_for_average) {
                $rows[$bowlerId]['games'] += (int) $sourceRow->games;
                $rows[$bowlerId]['total_pin'] += (int) $sourceRow->total_pin;
            }

            if ($isRankingOutput) {
                $candidateDate = $sourceRow->start_date
                    ?: substr((string) $sourceRow->published_at, 0, 10);
                if ($candidateDate !== null && ($asOfDate === null || $candidateDate > $asOfDate)) {
                    $asOfDate = $candidateDate;
                }
            }
        }

        $rows = array_values(array_filter($rows, static function (array $row): bool {
            return $row['points'] !== 0
                || $row['prize_money'] !== 0
                || $row['games'] > 0;
        }));

        foreach ($rows as &$row) {
            $row['tournament_count'] = count($row['tournament_ids']);
            unset($row['tournament_ids']);
            $row['average'] = $row['games'] > 0
                ? floor((($row['total_pin'] / $row['games']) * 100) + 0.0000001) / 100
                : 0.0;
        }
        unset($row);

        usort($rows, function (array $left, array $right) use ($rankingType): int {
            if ($rankingType === 'prize') {
                return ($right['prize_money'] <=> $left['prize_money'])
                    ?: ($right['points'] <=> $left['points'])
                    ?: ($right['total_pin'] <=> $left['total_pin'])
                    ?: strcmp($left['license_no'], $right['license_no']);
            }

            return ($right['points'] <=> $left['points'])
                ?: ($right['total_pin'] <=> $left['total_pin'])
                ?: ($right['prize_money'] <=> $left['prize_money'])
                ?: strcmp($left['license_no'], $right['license_no']);
        });

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return [
            'year' => $year,
            'gender' => $gender,
            'gender_label' => self::GENDER_LABELS[$gender],
            'ranking_type' => $rankingType,
            'ranking_type_label' => self::RANKING_TYPE_LABELS[$rankingType],
            'as_of_date' => $asOfDate,
            'published_tournament_count' => count($publishedTournamentIds),
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    private function affiliation(mixed $organization, mixed $equipment): string
    {
        return collect([$organization, $equipment])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->implode('/');
    }
}
