<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SeasonTrialRankingService
{
    public const CHAMPIONSHIP_CAPACITY = 55;

    public const SEASON_LABELS = [
        'winter' => 'ウィンター',
        'spring' => 'スプリング',
        'summer' => 'サマー',
        'autumn' => 'オータム',
    ];

    /** @return array<int,int> */
    public function years(): array
    {
        $years = DB::table('tournament_result_publications as publications')
            ->join('tournaments as tournaments', 'tournaments.id', '=', 'publications.tournament_id')
            ->where('publications.status', 'current')
            ->where($this->seasonTrialTournamentConstraint('tournaments'))
            ->whereNotNull('tournaments.year')
            ->distinct()
            ->orderByDesc('tournaments.year')
            ->pluck('tournaments.year')
            ->map(fn ($year): int => (int) $year)
            ->all();

        if ($years === []) {
            $years[] = (int) now()->year;
        }

        return $years;
    }

    /** @return array<string,mixed> */
    public function ranking(int $year): array
    {
        $sourceRows = $this->seasonTrialRows($year)
            ->orderBy('tournaments.start_date')
            ->orderBy('tournaments.id')
            ->orderBy('rows.ranking')
            ->get();

        $rankingRows = [];
        $tournamentIds = [];
        $asOfDate = null;

        foreach ($sourceRows as $sourceRow) {
            $bowlerId = (int) $sourceRow->pro_bowler_id;
            if ($bowlerId <= 0) {
                continue;
            }

            $seasonKey = $this->seasonKey(
                (string) ($sourceRow->season_key ?? ''),
                (string) $sourceRow->tournament_name,
            );
            $rankingRows[$bowlerId] ??= [
                'rank' => null,
                'pro_bowler_id' => $bowlerId,
                'license_no' => (string) ($sourceRow->license_no ?: $sourceRow->publication_license_no),
                'name_kanji' => (string) ($sourceRow->name_kanji ?: $sourceRow->display_name),
                'kibetsu' => $sourceRow->kibetsu === null ? null : (int) $sourceRow->kibetsu,
                'organization_name' => $this->affiliation(
                    $sourceRow->organization_name,
                    $sourceRow->equipment_contract,
                ),
                'sex' => (int) $sourceRow->sex,
                'points' => 0,
                'games' => 0,
                'total_pin' => 0,
                'average' => 0.0,
                'season_points' => array_fill_keys(array_keys(self::SEASON_LABELS), 0),
                'tournaments' => [],
            ];

            $points = (int) $sourceRow->points;
            $rankingRows[$bowlerId]['points'] += $points;
            $rankingRows[$bowlerId]['games'] += (int) $sourceRow->games;
            $rankingRows[$bowlerId]['total_pin'] += (int) $sourceRow->total_pin;
            $rankingRows[$bowlerId]['season_points'][$seasonKey] += $points;
            $rankingRows[$bowlerId]['tournaments'][] = [
                'tournament_id' => (int) $sourceRow->tournament_id,
                'name' => (string) $sourceRow->tournament_name,
                'start_date' => $sourceRow->start_date,
                'season_key' => $seasonKey,
                'ranking' => (int) $sourceRow->ranking,
                'points' => $points,
            ];

            $tournamentIds[(int) $sourceRow->tournament_id] = true;
            $candidateDate = $sourceRow->start_date ?: substr((string) $sourceRow->published_at, 0, 10);
            if ($candidateDate !== null && ($asOfDate === null || $candidateDate > $asOfDate)) {
                $asOfDate = $candidateDate;
            }
        }

        $rankingRows = array_values($rankingRows);
        foreach ($rankingRows as &$row) {
            $row['average'] = $row['games'] > 0
                ? floor(($row['total_pin'] / $row['games']) * 100) / 100
                : 0.0;
        }
        unset($row);

        usort($rankingRows, static function (array $left, array $right): int {
            return ($right['points'] <=> $left['points'])
                ?: ($right['total_pin'] <=> $left['total_pin'])
                ?: strcmp((string) $left['license_no'], (string) $right['license_no']);
        });

        foreach ($rankingRows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return [
            'year' => $year,
            'as_of_date' => $asOfDate,
            'published_tournament_count' => count($tournamentIds),
            'row_count' => count($rankingRows),
            'season_labels' => self::SEASON_LABELS,
            'rows' => $rankingRows,
        ];
    }

    /** @return array<string,mixed> */
    public function championshipPriority(int $year): array
    {
        $ranking = $this->ranking($year);
        $rankingByBowler = collect($ranking['rows'])->keyBy('pro_bowler_id');
        $selected = [];
        $seen = [];

        $this->appendPriorityGroup(
            $selected,
            $seen,
            $this->tournamentSeedCandidates($year),
            '①',
            '当該年度トーナメントシードプロ',
            'guaranteed',
            $rankingByBowler,
        );
        $this->appendPriorityGroup(
            $selected,
            $seen,
            $this->currentYearOfficialWinnerCandidates($year),
            '②',
            '当該年度公認トーナメント優勝者シードプロ',
            'guaranteed',
            $rankingByBowler,
        );
        $this->appendPriorityGroup(
            $selected,
            $seen,
            $this->permanentSeedCandidates(),
            '③',
            '永久シードプロ',
            'guaranteed',
            $rankingByBowler,
        );
        $this->appendPriorityGroup(
            $selected,
            $seen,
            $this->seasonTrialWinnerCandidates($year),
            '④',
            'ST各会場優勝者',
            'guaranteed',
            $rankingByBowler,
        );
        $this->appendPriorityGroup(
            $selected,
            $seen,
            $this->sponsorRecommendationCandidates($year),
            '⑤',
            'スポンサー推薦',
            'guaranteed',
            $rankingByBowler,
        );

        $remainingSlots = max(0, self::CHAMPIONSHIP_CAPACITY - count($selected));
        $rankingCandidates = collect($ranking['rows'])
            ->reject(fn (array $row): bool => isset($seen[$this->identityKey($row)]))
            ->take($remainingSlots)
            ->map(fn (array $row): object => (object) $row)
            ->all();
        $this->appendPriorityGroup(
            $selected,
            $seen,
            $rankingCandidates,
            '⑥',
            'ST年間ポイントランキング枠',
            'ranking_slot',
            $rankingByBowler,
        );

        foreach ($selected as $index => &$row) {
            $row['priority_rank'] = $index + 1;
        }
        unset($row);

        return [
            'year' => $year,
            'as_of_date' => $ranking['as_of_date'],
            'capacity' => self::CHAMPIONSHIP_CAPACITY,
            'selected_count' => count($selected),
            'ranking_slot_count' => count(array_filter(
                $selected,
                fn (array $row): bool => $row['status'] === 'ranking_slot',
            )),
            'published_tournament_count' => $ranking['published_tournament_count'],
            'rows' => $selected,
        ];
    }

    private function seasonTrialRows(int $year): Builder
    {
        return DB::table('tournament_result_publication_rows as rows')
            ->join('tournament_result_publications as publications', 'publications.id', '=', 'rows.publication_id')
            ->join('tournaments as tournaments', 'tournaments.id', '=', 'publications.tournament_id')
            ->leftJoin('tournament_editions as editions', 'editions.id', '=', 'tournaments.tournament_edition_id')
            ->leftJoin('pro_bowlers as bowlers', 'bowlers.id', '=', 'rows.pro_bowler_id')
            ->where('publications.status', 'current')
            ->where('tournaments.year', $year)
            ->where($this->seasonTrialTournamentConstraint('tournaments'))
            ->whereNotNull('rows.pro_bowler_id')
            ->where('bowlers.sex', 1)
            ->select([
                'rows.pro_bowler_id',
                'rows.pro_bowler_license_no as publication_license_no',
                'rows.display_name',
                'rows.ranking',
                'rows.points',
                'rows.games',
                'rows.total_pin',
                'publications.published_at',
                'tournaments.id as tournament_id',
                'tournaments.name as tournament_name',
                'tournaments.start_date',
                'editions.season_key',
                'bowlers.license_no',
                'bowlers.name_kanji',
                'bowlers.kibetsu',
                'bowlers.organization_name',
                'bowlers.equipment_contract',
                'bowlers.sex',
            ]);
    }

    private function tournamentSeedCandidates(int $year): array
    {
        return DB::table('pro_bowler_seed_list_players as players')
            ->join('pro_bowler_seed_lists as lists', 'lists.id', '=', 'players.seed_list_id')
            ->join('pro_bowlers as bowlers', 'bowlers.id', '=', 'players.pro_bowler_id')
            ->where('lists.seed_year', $year)
            ->where('lists.gender', 'M')
            ->where('lists.seed_list_type', 'tournament_seed')
            ->where('lists.is_active', true)
            ->where('players.is_active', true)
            ->where('players.seed_category', ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED)
            ->orderByRaw('COALESCE(players.seed_rank, players.ranking_rank, players.priority_order, 999999)')
            ->select($this->bowlerCandidateColumns())
            ->get()
            ->all();
    }

    private function currentYearOfficialWinnerCandidates(int $year): array
    {
        return DB::table('pro_bowler_titles as titles')
            ->join('pro_bowlers as bowlers', 'bowlers.id', '=', 'titles.pro_bowler_id')
            ->leftJoin('tournaments as tournaments', 'tournaments.id', '=', 'titles.tournament_id')
            ->where('titles.year', $year)
            ->where('bowlers.sex', 1)
            ->where(function (Builder $query): void {
                $query->whereNull('tournaments.id')
                    ->orWhere(function (Builder $query): void {
                        $query->where(function (Builder $query): void {
                            $query->whereNull('tournaments.title_scope')
                                ->orWhere('tournaments.title_scope', '!=', 'season_trial');
                        })->where(function (Builder $query): void {
                            $query->whereNull('tournaments.title_category')
                                ->orWhere('tournaments.title_category', '!=', 'season_trial');
                        });
                    });
            })
            ->where('titles.title_name', 'not like', '%シーズントライアル%')
            ->orderBy('titles.won_date')
            ->orderBy('titles.id')
            ->select($this->bowlerCandidateColumns())
            ->get()
            ->all();
    }

    private function permanentSeedCandidates(): array
    {
        return DB::table('pro_bowlers as bowlers')
            ->where('bowlers.sex', 1)
            ->where('bowlers.is_active', true)
            ->whereNotNull('bowlers.permanent_seed_date')
            ->orderBy('bowlers.permanent_seed_date')
            ->orderBy('bowlers.license_no')
            ->select($this->bowlerCandidateColumns())
            ->get()
            ->all();
    }

    private function seasonTrialWinnerCandidates(int $year): array
    {
        return $this->seasonTrialRows($year)
            ->where('rows.ranking', 1)
            ->orderBy('tournaments.start_date')
            ->orderBy('tournaments.id')
            ->get()
            ->all();
    }

    private function sponsorRecommendationCandidates(int $year): array
    {
        return DB::table('tournament_seed_players as seeds')
            ->join('tournaments as tournaments', 'tournaments.id', '=', 'seeds.tournament_id')
            ->join('pro_bowlers as bowlers', 'bowlers.id', '=', 'seeds.pro_bowler_id')
            ->where('tournaments.year', $year)
            ->where('tournaments.name', 'like', '%STチャンピオンズ%')
            ->where('bowlers.sex', 1)
            ->where('seeds.seed_source_type', ProBowlerSeedService::SOURCE_EVENT_SPONSOR_RECOMMENDATION)
            ->where('seeds.is_active', true)
            ->orderBy('seeds.priority_order')
            ->select($this->bowlerCandidateColumns())
            ->get()
            ->all();
    }

    private function appendPriorityGroup(
        array &$selected,
        array &$seen,
        iterable $candidates,
        string $categoryCode,
        string $categoryLabel,
        string $status,
        $rankingByBowler,
    ): void {
        foreach ($candidates as $candidate) {
            $candidateArray = (array) $candidate;
            if (! $this->isMaleCandidate($candidateArray)) {
                continue;
            }

            $key = $this->identityKey($candidateArray);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $bowlerId = (int) ($candidateArray['pro_bowler_id'] ?? 0);
            $rankingRow = $rankingByBowler->get($bowlerId);
            $selected[] = [
                'priority_rank' => null,
                'category_code' => $categoryCode,
                'category_label' => $categoryLabel,
                'status' => $status,
                'pro_bowler_id' => $bowlerId ?: null,
                'license_no' => (string) ($candidateArray['license_no'] ?? ''),
                'name_kanji' => (string) ($candidateArray['name_kanji'] ?? $candidateArray['display_name'] ?? ''),
                'kibetsu' => isset($candidateArray['kibetsu']) ? (int) $candidateArray['kibetsu'] : null,
                'organization_name' => (string) ($candidateArray['organization_name'] ?? ''),
                'st_ranking_rank' => $rankingRow['rank'] ?? null,
                'st_points' => (int) ($rankingRow['points'] ?? 0),
                'st_total_pin' => (int) ($rankingRow['total_pin'] ?? 0),
            ];
            $seen[$key] = true;
        }
    }

    /** @return array<int,string> */
    private function bowlerCandidateColumns(): array
    {
        return [
            'bowlers.id as pro_bowler_id',
            'bowlers.license_no',
            'bowlers.name_kanji',
            'bowlers.kibetsu',
            'bowlers.organization_name',
            'bowlers.sex',
        ];
    }

    private function isMaleCandidate(array $row): bool
    {
        if (array_key_exists('sex', $row)) {
            return (int) $row['sex'] === 1;
        }

        return str_starts_with(mb_strtoupper(trim((string) ($row['license_no'] ?? ''))), 'M');
    }

    private function identityKey(array $row): string
    {
        $bowlerId = (int) ($row['pro_bowler_id'] ?? 0);
        if ($bowlerId > 0) {
            return 'bowler:'.$bowlerId;
        }

        $license = mb_strtoupper(trim((string) ($row['license_no'] ?? '')));

        return $license === '' ? '' : 'license:'.$license;
    }

    private function seasonTrialTournamentConstraint(string $alias): \Closure
    {
        return static function (Builder $query) use ($alias): void {
            $query->where("{$alias}.title_scope", 'season_trial')
                ->orWhere("{$alias}.title_category", 'season_trial')
                ->orWhere("{$alias}.name", 'like', '%シーズントライアル%');
        };
    }

    private function seasonKey(string $seasonKey, string $tournamentName): string
    {
        $seasonKey = mb_strtolower(trim($seasonKey));
        if (array_key_exists($seasonKey, self::SEASON_LABELS)) {
            return $seasonKey;
        }

        foreach (self::SEASON_LABELS as $key => $label) {
            if (str_contains($tournamentName, $label)) {
                return $key;
            }
        }

        return 'winter';
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
