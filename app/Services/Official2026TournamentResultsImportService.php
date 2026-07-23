<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\ProBowlerTitle;
use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026TournamentResultsImportService
{
    private const DATASET_PATH = 'data/jpba_official_2026_results.json';

    private const EXPECTED_EVENT_COUNT = 23;

    private const EXPECTED_SNAPSHOT_COUNT = 70;

    private const IMPORT_MARKER = 'jpba_official_2026_results';

    private const PRESERVED_EVENT_KEY = 'stsu_b';

    /**
     * Existing profile titles whose official-site wording differs from the
     * normalized 2026 tournament name. These exact aliases are reconciled
     * before publication so result synchronization cannot create duplicates.
     *
     * @var array<string,array<int,string>>
     */
    private const EXISTING_TITLE_ALIASES = [
        'purefoods_2026' => [
            'KISHIKAGAKU GROUP・ピュアフーズ岸プレゼンツ レディースプロボウリングトーナメント2026',
        ],
        'glico_2026_f' => [
            '「Glicoセブンティーンアイス杯」第13回プロアマボウリングトーナメント',
        ],
        'glico_2026_m' => [
            '「Glicoセブンティーンアイス杯」第13回プロアマボウリングトーナメント',
        ],
        'rookiesw_2026' => [
            'スカイAカップ 2026プロボウリングレディース新人戦',
        ],
        'tokai_2026_f' => [
            '中日杯2026東海オープンボウリングトーナメント',
        ],
    ];

    public function __construct(
        private readonly TournamentResultPublicationCalculator $calculator,
        private readonly TournamentResultPublicationService $publicationService,
        private readonly VenueNameNormalizer $venueNameNormalizer,
    ) {}

    /** @return array<string,mixed> */
    public function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Official 2026 result dataset was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->validateDatasetShape($payload);

        return $payload;
    }

    /** @return array<string,mixed> */
    public function import(bool $write = false, string $adminEmail = 'yamaguchi@jpba.or.jp'): array
    {
        $payload = $this->dataset();
        $admin = User::query()->where('email', $adminEmail)->first();
        $bowlerResolution = $this->resolveBowlers($payload);
        $venueResolution = $this->resolveVenues($payload);
        $datasetAudit = $this->auditDatasetRankings($payload, $bowlerResolution['map']);
        $conflicts = $this->existingDataConflicts($payload);
        $existingPublicationIds = TournamentResultPublication::query()
            ->where('status', 'current')
            ->where('notes', 'like', self::IMPORT_MARKER.':%')
            ->whereHas('tournament', fn ($query) => $query->where('year', 2026))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $errors = [];
        if ($admin === null) {
            $errors[] = "Administrator was not found: {$adminEmail}";
        }
        if ($bowlerResolution['missing'] !== []) {
            $errors[] = 'Professional licenses are missing from the pro bowler master.';
        }
        if ($venueResolution['missing'] !== []) {
            $errors[] = 'Active venues are missing from the venue master.';
        }
        if ($datasetAudit['difference_count'] > 0) {
            $errors[] = 'The normalized event totals do not match the official rankings.';
        }
        if ($conflicts !== []) {
            $errors[] = 'Existing tournament data conflicts with this import.';
        }

        $report = [
            'mode' => $write ? 'write' : 'dry-run',
            'dataset' => $payload['dataset'],
            'source_checked_at' => $payload['source_checked_at'],
            'dataset_sha256' => hash_file('sha256', database_path(self::DATASET_PATH)),
            'event_count' => count($payload['events']),
            'snapshot_count' => array_sum(array_map(
                fn (array $event): int => count($event['snapshots']),
                $payload['events'],
            )),
            'snapshot_row_count' => array_sum(array_map(
                fn (array $event): int => array_sum(array_map(
                    fn (array $snapshot): int => count($snapshot['rows']),
                    $event['snapshots'],
                )),
                $payload['events'],
            )),
            'admin_id' => $admin?->id,
            'missing_bowlers' => $bowlerResolution['missing'],
            'missing_venues' => $venueResolution['missing'],
            'conflicts' => $conflicts,
            'dataset_ranking_audit' => $datasetAudit,
            'planned_tournaments' => $this->tournamentPlan($payload),
            'existing_publication_count' => count($existingPublicationIds),
            'errors' => $errors,
            'tournaments' => [],
            'ranking_snapshots' => [],
            'database_ranking_audit' => count($existingPublicationIds) === self::EXPECTED_EVENT_COUNT
                ? $this->auditPublishedRankings($payload, $existingPublicationIds)
                : null,
        ];

        if (! $write || $errors !== []) {
            return $report;
        }

        return DB::transaction(function () use (
            $payload,
            $admin,
            $bowlerResolution,
            $venueResolution,
            $report,
        ): array {
            $publicationIds = [];
            $tournamentIds = [];

            foreach ($payload['events'] as $event) {
                $tournament = $this->persistTournament(
                    $event,
                    $venueResolution['map'],
                );
                $preserved = $this->shouldPreserveExistingResults($event, $tournament);

                $this->syncDistributions($tournament, $event);
                $titleAliasReconciliation = $this->reconcileExistingTitleAlias(
                    $tournament,
                    $event,
                    $bowlerResolution['map'],
                );

                if (! $preserved) {
                    $this->replaceImportedEventData($tournament);
                    $this->insertParticipantsAndEntries($tournament, $event, $bowlerResolution['map']);
                    $finalSnapshot = $this->insertSnapshots(
                        $tournament,
                        $event,
                        $bowlerResolution['map'],
                        (int) $admin->id,
                    );
                } else {
                    $finalSnapshot = TournamentResultSnapshot::query()
                        ->where('tournament_id', $tournament->id)
                        ->where('is_current', true)
                        ->where('is_final', true)
                        ->orderByDesc('id')
                        ->firstOrFail();
                }

                $preview = $this->publicationService->preview($tournament->fresh(), $finalSnapshot);
                if (! $preview['can_publish']) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $preview['errors']),
                    );
                }

                $publication = $this->publicationService->publish(
                    $tournament->fresh(),
                    $finalSnapshot->fresh(),
                    (int) $admin->id,
                    (string) $preview['result_checksum'],
                    self::IMPORT_MARKER.':'.$event['key'],
                );

                $publicationIds[] = (int) $publication->id;
                $tournamentIds[] = (int) $tournament->id;
                $report['tournaments'][] = [
                    'key' => $event['key'],
                    'tournament_id' => (int) $tournament->id,
                    'name' => $tournament->name,
                    'preserved_existing_scores' => $preserved,
                    'publication_id' => (int) $publication->id,
                    'row_count' => (int) $publication->row_count,
                    'pro_count' => (int) $publication->pro_count,
                    'amateur_count' => (int) $publication->amateur_count,
                    'total_points' => (int) $publication->total_points,
                    'total_prize_money' => (int) $publication->total_prize_money,
                    'title_alias_reconciliation' => $titleAliasReconciliation,
                    'title_sync' => $publication->title_sync_summary,
                ];
            }

            $report['ranking_snapshots'] = $this->persistOfficialRankings(
                $payload,
                $bowlerResolution['map'],
            );
            $report['database_ranking_audit'] = $this->auditPublishedRankings(
                $payload,
                $publicationIds,
            );

            if ($report['database_ranking_audit']['difference_count'] > 0) {
                throw new RuntimeException('Published points or prize money do not match the official rankings.');
            }

            $report['published_tournament_ids'] = $tournamentIds;
            $report['published_publication_ids'] = $publicationIds;

            return $report;
        });
    }

    /** @param array<string,mixed> $payload */
    private function validateDatasetShape(array $payload): void
    {
        foreach (['dataset', 'source_checked_at', 'events', 'official_rankings', 'source_sha256'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new RuntimeException("Official result dataset is missing {$key}.");
            }
        }
        if (count($payload['events']) !== self::EXPECTED_EVENT_COUNT) {
            throw new RuntimeException('Official result dataset must contain 23 completed event publications.');
        }

        $snapshotCount = 0;
        $eventKeys = [];
        foreach ($payload['events'] as $event) {
            foreach (['key', 'point_distributions', 'prize_distributions', 'snapshots'] as $key) {
                if (! array_key_exists($key, $event)) {
                    throw new RuntimeException("Event dataset is missing {$key}.");
                }
            }
            if (isset($eventKeys[$event['key']])) {
                throw new RuntimeException("Duplicate event key: {$event['key']}");
            }
            $eventKeys[$event['key']] = true;
            $snapshotCount += count($event['snapshots']);
            if (count(array_filter($event['snapshots'], fn (array $snapshot): bool => (bool) $snapshot['is_final'])) !== 1) {
                throw new RuntimeException("Event must contain one final snapshot: {$event['key']}");
            }
        }
        if ($snapshotCount !== self::EXPECTED_SNAPSHOT_COUNT) {
            throw new RuntimeException('Official result dataset must contain 70 snapshots.');
        }

        $rankingCounts = [];
        foreach ($payload['official_rankings'] as $ranking) {
            $rankingCounts[$ranking['gender']] = count($ranking['rows']);
        }
        if (($rankingCounts['M'] ?? 0) !== 327 || ($rankingCounts['F'] ?? 0) !== 212) {
            throw new RuntimeException('Official ranking row counts are invalid.');
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{map:array<string,ProBowler>,missing:array<int,array<string,string>>}
     */
    private function resolveBowlers(array $payload): array
    {
        $licenses = [];
        foreach ($payload['events'] as $event) {
            foreach ($event['snapshots'] as $snapshot) {
                foreach ($snapshot['rows'] as $row) {
                    if (! $row['is_amateur']) {
                        $licenses[$row['license_no']] = $row['display_name'];
                    }
                }
            }
        }
        foreach ($payload['official_rankings'] as $ranking) {
            foreach ($ranking['rows'] as $row) {
                $licenses[$row['license_no']] = $row['name_kanji'];
            }
        }

        $map = ProBowler::query()
            ->whereIn('license_no', array_keys($licenses))
            ->get()
            ->keyBy(fn (ProBowler $bowler): string => strtoupper((string) $bowler->license_no))
            ->all();
        $missing = [];
        foreach ($licenses as $license => $name) {
            if (! isset($map[strtoupper($license)])) {
                $missing[] = ['license_no' => $license, 'display_name' => $name];
            }
        }

        return ['map' => $map, 'missing' => $missing];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{map:array<string,Venue>,missing:array<int,string>}
     */
    private function resolveVenues(array $payload): array
    {
        $names = [];
        foreach ($payload['events'] as $event) {
            if (isset($event['tournament'])) {
                $names[$event['tournament']['venue_name']] = true;
            }
        }

        $venues = Venue::query()->where('is_active', true)->get();
        $map = [];
        $missing = [];
        foreach (array_keys($names) as $name) {
            $key = $this->venueNameNormalizer->normalize($name);
            $venue = $venues->first(function (Venue $candidate) use ($key): bool {
                return collect([$candidate->name, ...($candidate->aliases ?? [])])
                    ->contains(fn ($value): bool => $this->venueNameNormalizer->normalize($value) === $key);
            });
            if ($venue === null) {
                $missing[] = $name;
            } else {
                $map[$name] = $venue;
            }
        }

        return ['map' => $map, 'missing' => $missing];
    }

    /** @param array<string,mixed> $payload */
    private function auditDatasetRankings(array $payload, array $bowlerMap): array
    {
        $aggregate = [];
        $events = [];

        foreach ($payload['events'] as $event) {
            $groups = [];
            $eventGender = $this->eventGender($event);
            foreach ($event['snapshots'] as $index => $snapshot) {
                $groups[] = [
                    'snapshot_id' => $index + 1,
                    'result_code' => $snapshot['result_code'],
                    'rows' => array_map(
                        fn (array $row): array => $this->calculatorRow($row, $bowlerMap, $index + 1, $eventGender),
                        $snapshot['rows'],
                    ),
                ];
            }

            $isSeasonTrial = $this->isSeasonTrialEvent($event);
            $rows = $this->calculator->build(
                $groups,
                collect($event['point_distributions'])->pluck('points', 'rank')->mapWithKeys(
                    fn ($points, $rank): array => [(int) $rank => (int) $points],
                )->all(),
                collect($event['prize_distributions'])->pluck('amount', 'rank')->mapWithKeys(
                    fn ($amount, $rank): array => [(int) $rank => (int) $amount],
                )->all(),
                [
                    'is_season_trial' => $isSeasonTrial,
                    'counts_for_points' => $isSeasonTrial || (bool) ($event['tournament']['counts_for_official_points'] ?? false),
                    'counts_for_prize' => $isSeasonTrial || (bool) ($event['tournament']['counts_for_prize'] ?? false),
                    'semifinal_qualifier_count' => $this->semifinalCount($event),
                ],
            );

            foreach ($rows as $row) {
                if ((int) ($row['pro_bowler_id'] ?? 0) <= 0) {
                    continue;
                }
                $license = (string) $row['pro_bowler_license_no'];
                $aggregate[$license]['points'] = (int) ($aggregate[$license]['points'] ?? 0) + (int) $row['points'];
                $aggregate[$license]['prize_money'] = (int) ($aggregate[$license]['prize_money'] ?? 0) + (int) $row['prize_money'];
            }
            $events[$event['key']] = [
                'row_count' => count($rows),
                'total_points' => array_sum(array_column($rows, 'points')),
                'total_prize_money' => array_sum(array_column($rows, 'prize_money')),
            ];
        }

        return $this->compareRankingAggregate($payload, $aggregate) + ['events' => $events];
    }

    /** @param array<string,mixed> $row */
    private function calculatorRow(array $row, array $bowlerMap, int $id, string $gender): array
    {
        $bowler = $row['is_amateur'] ? null : ($bowlerMap[strtoupper($row['license_no'])] ?? null);
        $breakdown = [
            'official_source_pdf' => $row['source_pdf'] ?? null,
            'official_identity' => $row['identity'],
        ];
        if (array_key_exists('points_eligible', $row)) {
            $breakdown['official_points_eligible'] = (bool) $row['points_eligible'];
            $breakdown['official_points_ineligible_reason'] = $row['points_ineligible_reason'] ?? null;
        }
        if ((int) ($row['special_prize_money'] ?? 0) > 0) {
            $breakdown['official_special_prize_money'] = (int) $row['special_prize_money'];
            $breakdown['official_special_prize_note'] = $row['special_prize_note'] ?? null;
        }

        return [
            'id' => $id,
            'ranking' => (int) $row['ranking'],
            'pro_bowler_id' => $bowler?->id,
            'pro_bowler_license_no' => $bowler?->license_no,
            'amateur_name' => $bowler === null ? $row['display_name'] : null,
            'display_name' => $bowler?->name_kanji ?: $row['display_name'],
            'gender' => $gender,
            'identity_key' => $row['identity'],
            'total_pin' => (int) $row['total_pin'],
            'games' => (int) $row['games'],
            'average' => (float) $row['average'],
            'points' => (int) $row['points'],
            'prize_money' => (int) $row['prize_money'],
            'breakdown' => $breakdown,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function existingDataConflicts(array $payload): array
    {
        $conflicts = [];
        foreach ($payload['events'] as $event) {
            $tournament = $this->findTournament($event);
            if ($tournament === null || $this->shouldPreserveExistingResults($event, $tournament)) {
                continue;
            }

            $snapshotCount = TournamentResultSnapshot::query()->where('tournament_id', $tournament->id)->count();
            if ($snapshotCount === 0) {
                continue;
            }

            $foreignSnapshotCount = TournamentResultSnapshot::query()
                ->where('tournament_id', $tournament->id)
                ->where(function ($query): void {
                    $query->whereNull('notes')->orWhere('notes', 'not like', self::IMPORT_MARKER.'%');
                })
                ->count();
            if ($foreignSnapshotCount > 0) {
                $conflicts[] = [
                    'key' => $event['key'],
                    'tournament_id' => (int) $tournament->id,
                    'reason' => 'Existing snapshots are not owned by this importer.',
                ];
            }
        }

        return $conflicts;
    }

    /** @param array<string,mixed> $payload */
    private function tournamentPlan(array $payload): array
    {
        return array_map(function (array $event): array {
            $tournament = $this->findTournament($event);

            return [
                'key' => $event['key'],
                'action' => $tournament === null ? 'create' : 'update',
                'tournament_id' => $tournament?->id,
                'name' => $event['tournament']['name'] ?? $event['existing_tournament_name'],
                'preserve_existing_scores' => $tournament !== null && $this->shouldPreserveExistingResults($event, $tournament),
            ];
        }, $payload['events']);
    }

    /** @param array<string,mixed> $event */
    private function findTournament(array $event): ?Tournament
    {
        return Tournament::query()
            ->where('year', 2026)
            ->where('name', $event['tournament']['name'] ?? $event['existing_tournament_name'])
            ->first();
    }

    /** @param array<string,mixed> $event */
    private function persistTournament(array $event, array $venueMap): Tournament
    {
        $existing = $this->findTournament($event);
        if (! isset($event['tournament'])) {
            if ($existing === null) {
                throw new RuntimeException("Season trial tournament shell is missing: {$event['key']}");
            }

            return $existing;
        }

        $source = $event['tournament'];
        $venue = $venueMap[$source['venue_name']];
        $roundRobinSnapshot = collect($event['snapshots'] ?? [])
            ->firstWhere('result_code', 'round_robin_total');
        $hasRoundRobin = is_array($roundRobinSnapshot);
        $roundRobinRows = $hasRoundRobin ? (array) ($roundRobinSnapshot['rows'] ?? []) : [];
        $templateSnapshot = [
            '_official_import' => self::IMPORT_MARKER,
            'event_key' => $event['key'],
            'source_url' => $source['source_url'],
            'source_checked_at' => '2026-07-22',
        ];
        if (array_key_exists('pdf_prize_breakdowns', $source)) {
            $templateSnapshot['pdf_prize_breakdowns'] = array_values($source['pdf_prize_breakdowns']);
        }
        if (array_key_exists('pdf_assets', $source)) {
            $templateSnapshot['pdf_assets'] = $source['pdf_assets'];
        }

        $attributes = [
            'name' => $source['name'],
            'setup_status' => 'completed',
            'competition_type' => $source['competition_type'],
            'start_date' => $source['start_date'],
            'end_date' => $source['end_date'],
            'year' => (int) $source['year'],
            'gender' => $source['gender'],
            'official_type' => $source['official_type'],
            'title_category' => $source['title_category'],
            'title_scope' => $source['title_scope'],
            'counts_for_official_points' => (bool) $source['counts_for_official_points'],
            'counts_for_average' => (bool) $source['counts_for_average'],
            'counts_for_prize' => (bool) $source['counts_for_prize'],
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'venue_address' => $venue->address,
            'venue_tel' => $venue->tel,
            'venue_fax' => $venue->fax,
            'host' => $source['host'] ?? 'JPBA',
            'authorized_by' => $source['authorized_by'] ?? 'JPBA',
            'materials' => $source['source_url'],
            'prize' => $source['counts_for_prize']
                ? ($source['prize'] ?? 'See official result publication.')
                : null,
            'result_flow_type' => $hasRoundRobin ? 'prelim_to_rr_to_final' : 'legacy_standard',
            'round_robin_qualifier_count' => $hasRoundRobin ? max(4, count($roundRobinRows)) : null,
            'round_robin_win_bonus' => $hasRoundRobin ? 30 : null,
            'round_robin_tie_bonus' => $hasRoundRobin ? 15 : null,
            'round_robin_position_round_enabled' => $hasRoundRobin,
            'template_snapshot' => $templateSnapshot,
        ];

        foreach (['special_sponsor', 'support', 'sponsor', 'supervisor'] as $optionalField) {
            if (array_key_exists($optionalField, $source)) {
                $attributes[$optionalField] = $source[$optionalField];
            }
        }
        if (array_key_exists('award_highlights', $source)) {
            $attributes['award_highlights'] = array_values($source['award_highlights']);
        }

        $tournament = $existing ?? new Tournament;
        $tournament->fill($attributes)->save();

        return $tournament->fresh();
    }

    /** @param array<string,mixed> $event */
    private function shouldPreserveExistingResults(array $event, Tournament $tournament): bool
    {
        return $event['key'] === self::PRESERVED_EVENT_KEY
            && TournamentResultSnapshot::query()->where('tournament_id', $tournament->id)->exists();
    }

    /**
     * @param  array<string,mixed>  $event
     * @param  array<string,ProBowler>  $bowlerMap
     * @return array<string,mixed>|null
     */
    private function reconcileExistingTitleAlias(Tournament $tournament, array $event, array $bowlerMap): ?array
    {
        $aliases = self::EXISTING_TITLE_ALIASES[$event['key']] ?? null;
        if ($aliases === null) {
            return null;
        }

        $finalSnapshot = collect($event['snapshots'])->first(
            fn (array $snapshot): bool => (bool) $snapshot['is_final'],
        );
        $winnerRows = collect($finalSnapshot['rows'] ?? [])->filter(
            fn (array $row): bool => (int) $row['ranking'] === 1 && ! (bool) $row['is_amateur'],
        )->values();
        if ($winnerRows->count() !== 1) {
            throw new RuntimeException("Expected one professional title winner for alias reconciliation: {$event['key']}");
        }

        $winner = $winnerRows->first();
        $license = strtoupper((string) $winner['license_no']);
        $bowler = $bowlerMap[$license] ?? null;
        if ($bowler === null) {
            throw new RuntimeException("Title winner is missing from the pro bowler master: {$event['key']} {$license}");
        }

        $aliasTitles = ProBowlerTitle::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('year', 2026)
            ->where(function ($query) use ($aliases): void {
                $query->whereIn('title_name', $aliases)
                    ->orWhereIn('tournament_name', $aliases);
            })
            ->get();
        if ($aliasTitles->count() !== 1) {
            throw new RuntimeException(
                "Expected one exact existing title alias for {$event['key']}; found {$aliasTitles->count()}.",
            );
        }

        $aliasTitle = $aliasTitles->first();
        if ($aliasTitle->tournament_id !== null && (int) $aliasTitle->tournament_id !== (int) $tournament->id) {
            throw new RuntimeException("Existing title alias is linked to another tournament: {$event['key']}");
        }

        $generatedTitles = ProBowlerTitle::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('tournament_id', $tournament->id)
            ->where('id', '!=', $aliasTitle->id)
            ->get();
        foreach ($generatedTitles as $generatedTitle) {
            if ((string) $generatedTitle->source !== 'sync_from_results' || (int) $generatedTitle->year !== 2026) {
                throw new RuntimeException("Refusing to replace a non-imported linked title: {$event['key']}");
            }
            $generatedTitle->delete();
        }

        $aliasTitle->forceFill(['tournament_id' => $tournament->id])->save();

        return [
            'title_id' => (int) $aliasTitle->id,
            'pro_bowler_id' => (int) $bowler->id,
            'removed_generated_duplicates' => $generatedTitles->count(),
            'linked_to_tournament' => true,
        ];
    }

    /** @param array<string,mixed> $event */
    private function syncDistributions(Tournament $tournament, array $event): void
    {
        DB::table('point_distributions')->where('tournament_id', $tournament->id)->delete();
        DB::table('prize_distributions')->where('tournament_id', $tournament->id)->delete();
        $now = now();

        $points = array_map(fn (array $row): array => [
            'tournament_id' => $tournament->id,
            'rank' => (int) $row['rank'],
            'points' => (int) $row['points'],
            'pattern_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $event['point_distributions']);
        $prizes = array_map(fn (array $row): array => [
            'tournament_id' => $tournament->id,
            'rank' => (int) $row['rank'],
            'amount' => (int) $row['amount'],
            'pattern_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $event['prize_distributions']);

        foreach (array_chunk($points, 500) as $chunk) {
            DB::table('point_distributions')->insert($chunk);
        }
        foreach (array_chunk($prizes, 500) as $chunk) {
            DB::table('prize_distributions')->insert($chunk);
        }
    }

    private function replaceImportedEventData(Tournament $tournament): void
    {
        TournamentResultPublication::query()->where('tournament_id', $tournament->id)->delete();
        TournamentResultSnapshot::query()->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_results')->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_entries')->where('tournament_id', $tournament->id)->delete();
        DB::table('tournament_participants')->where('tournament_id', $tournament->id)->delete();
    }

    /** @param array<string,mixed> $event */
    private function insertParticipantsAndEntries(Tournament $tournament, array $event, array $bowlerMap): void
    {
        $participants = [];
        foreach ($event['snapshots'] as $snapshot) {
            foreach ($snapshot['rows'] as $row) {
                $participants[$row['identity']] ??= $row;
            }
        }

        $now = now();
        $sortOrder = 0;
        $eventGender = $this->eventGender($event);
        foreach ($participants as $row) {
            $sortOrder++;
            $bowler = $row['is_amateur'] ? null : ($bowlerMap[strtoupper($row['license_no'])] ?? null);
            DB::table('tournament_participants')->insert([
                'tournament_id' => $tournament->id,
                'pro_bowler_license_no' => $bowler?->license_no ?? $row['license_no'],
                'pro_bowler_id' => $bowler?->id,
                'participant_type' => $bowler === null ? 'amateur' : 'pro',
                'display_name' => $bowler?->name_kanji ?: $row['display_name'],
                'display_license_no' => $bowler?->license_no,
                'gender' => $eventGender,
                'sort_order' => $sortOrder,
                'source_note' => self::IMPORT_MARKER.':'.$event['key'],
                'is_temporary' => $bowler === null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($bowler !== null) {
                DB::table('tournament_entries')->insert([
                    'pro_bowler_id' => $bowler->id,
                    'tournament_id' => $tournament->id,
                    'status' => 'entry',
                    'is_paid' => false,
                    'shift_drawn' => false,
                    'lane_drawn' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** @param array<string,mixed> $event */
    private function insertSnapshots(Tournament $tournament, array $event, array $bowlerMap, int $adminId): TournamentResultSnapshot
    {
        $finalSnapshot = null;
        $eventGender = $this->eventGender($event);
        foreach ($event['snapshots'] as $snapshotRow) {
            $snapshot = TournamentResultSnapshot::query()->create([
                'tournament_id' => $tournament->id,
                'result_code' => $snapshotRow['result_code'],
                'result_name' => $snapshotRow['result_name'],
                'result_type' => 'total_pin',
                'stage_name' => $snapshotRow['result_name'],
                'gender' => $eventGender,
                'shift' => null,
                'games_count' => (int) $snapshotRow['games_count'],
                'carry_game_count' => 0,
                'carry_stage_names' => [],
                'calculation_definition' => [
                    'source' => self::IMPORT_MARKER,
                    'event_key' => $event['key'],
                    'source_checked_at' => '2026-07-22',
                ],
                'reflected_at' => now(),
                'reflected_by' => $adminId,
                'is_final' => (bool) $snapshotRow['is_final'],
                'is_published' => false,
                'is_current' => true,
                'notes' => self::IMPORT_MARKER.':'.$event['key'],
            ]);

            $rows = [];
            foreach ($snapshotRow['rows'] as $index => $row) {
                $calculatorRow = $this->calculatorRow($row, $bowlerMap, $index + 1, $eventGender);
                $rows[] = [
                    'snapshot_id' => $snapshot->id,
                    'ranking' => $calculatorRow['ranking'],
                    'subject_type' => 'individual',
                    'pro_bowler_id' => $calculatorRow['pro_bowler_id'],
                    'amateur_bowler_id' => null,
                    'pro_bowler_license_no' => $calculatorRow['pro_bowler_license_no'],
                    'amateur_name' => $calculatorRow['amateur_name'],
                    'display_name' => $calculatorRow['display_name'],
                    'gender' => $calculatorRow['gender'],
                    'shift' => null,
                    'entry_number' => null,
                    'identity_key' => $calculatorRow['identity_key'],
                    'scratch_pin' => $calculatorRow['total_pin'],
                    'carry_pin' => 0,
                    'total_pin' => $calculatorRow['total_pin'],
                    'games' => $calculatorRow['games'],
                    'source_count' => 1,
                    'is_complete' => true,
                    'breakdown' => json_encode($calculatorRow['breakdown'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'average' => $calculatorRow['average'],
                    'tie_break_value' => null,
                    'points' => $calculatorRow['points'],
                    'prize_money' => $calculatorRow['prize_money'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('tournament_result_snapshot_rows')->insert($chunk);
            }

            if ($snapshot->is_final) {
                $finalSnapshot = $snapshot;
            }
        }

        if ($finalSnapshot === null) {
            throw new RuntimeException("Final snapshot was not created: {$event['key']}");
        }

        return $finalSnapshot;
    }

    /** @param array<string,mixed> $payload */
    private function persistOfficialRankings(array $payload, array $bowlerMap): array
    {
        $report = [];
        foreach ($payload['official_rankings'] as $ranking) {
            $snapshot = ProBowlerRankingSnapshot::query()->firstOrNew([
                'ranking_year' => 2026,
                'gender' => $ranking['gender'],
                'ranking_type' => 'points',
                'ranking_scope' => 'official_tournament',
                'as_of_date' => $ranking['as_of_date'],
                'is_final' => false,
            ]);
            $snapshot->fill([
                'source_url' => $ranking['source_url'],
                'notes' => self::IMPORT_MARKER.' validated against published tournament results.',
            ])->save();
            ProBowlerRankingRow::query()->where('ranking_snapshot_id', $snapshot->id)->delete();

            foreach ($ranking['rows'] as $index => $row) {
                $bowler = $bowlerMap[strtoupper($row['license_no'])];
                ProBowlerRankingRow::query()->create([
                    'ranking_snapshot_id' => $snapshot->id,
                    'ranking_rank' => (int) $row['ranking_rank'],
                    'pro_bowler_id' => $bowler->id,
                    'license_no' => $bowler->license_no,
                    'name_kanji' => $bowler->name_kanji ?: $row['name_kanji'],
                    'name_kana' => $bowler->name_kana,
                    'kibetsu' => $row['kibetsu'],
                    'organization_name' => $row['organization_name'],
                    'equipment_contract' => $bowler->equipment_contract,
                    'points' => (int) $row['points'],
                    'games' => (int) $row['games'],
                    'total_pin' => (int) $row['total_pin'],
                    'average' => (float) $row['average'],
                    'prize_money' => (int) $row['prize_money'],
                    'sort_order' => $index + 1,
                ]);
            }

            $report[$ranking['gender']] = [
                'snapshot_id' => (int) $snapshot->id,
                'as_of_date' => $ranking['as_of_date'],
                'row_count' => count($ranking['rows']),
                'source_url' => $ranking['source_url'],
            ];
        }

        return $report;
    }

    /** @param array<string,mixed> $payload */
    private function auditPublishedRankings(array $payload, array $publicationIds): array
    {
        $aggregate = [];
        $rows = DB::table('tournament_result_publication_rows')
            ->whereIn('publication_id', $publicationIds)
            ->whereNotNull('pro_bowler_license_no')
            ->selectRaw('pro_bowler_license_no, SUM(points) AS points, SUM(prize_money) AS prize_money')
            ->groupBy('pro_bowler_license_no')
            ->get();
        foreach ($rows as $row) {
            $aggregate[$row->pro_bowler_license_no] = [
                'points' => (int) $row->points,
                'prize_money' => (int) $row->prize_money,
            ];
        }

        return $this->compareRankingAggregate($payload, $aggregate);
    }

    /** @param array<string,array{points:int,prize_money:int}> $aggregate */
    private function compareRankingAggregate(array $payload, array $aggregate): array
    {
        $differences = [];
        $officialLicenses = [];
        foreach ($payload['official_rankings'] as $ranking) {
            foreach ($ranking['rows'] as $row) {
                $license = $row['license_no'];
                $officialLicenses[$license] = true;
                $actual = $aggregate[$license] ?? ['points' => 0, 'prize_money' => 0];
                if ((int) $actual['points'] !== (int) $row['points']
                    || (int) $actual['prize_money'] !== (int) $row['prize_money']) {
                    $differences[] = [
                        'gender' => $ranking['gender'],
                        'license_no' => $license,
                        'name' => $row['name_kanji'],
                        'official_points' => (int) $row['points'],
                        'actual_points' => (int) $actual['points'],
                        'official_prize_money' => (int) $row['prize_money'],
                        'actual_prize_money' => (int) $actual['prize_money'],
                    ];
                }
            }
        }
        foreach ($aggregate as $license => $actual) {
            if (! isset($officialLicenses[$license]) && ($actual['points'] !== 0 || $actual['prize_money'] !== 0)) {
                $differences[] = [
                    'license_no' => $license,
                    'reason' => 'Published aggregate is absent from the official ranking.',
                    'actual_points' => $actual['points'],
                    'actual_prize_money' => $actual['prize_money'],
                ];
            }
        }

        return [
            'difference_count' => count($differences),
            'differences' => array_slice($differences, 0, 50),
            'official_male_rows' => count($payload['official_rankings'][0]['rows']),
            'official_female_rows' => count($payload['official_rankings'][1]['rows']),
        ];
    }

    /** @param array<string,mixed> $event */
    private function semifinalCount(array $event): int
    {
        foreach ($event['snapshots'] as $snapshot) {
            if ($snapshot['result_code'] === 'semifinal_total') {
                return count($snapshot['rows']);
            }
        }

        return 0;
    }

    /** @param array<string,mixed> $event */
    private function isSeasonTrialEvent(array $event): bool
    {
        return ! isset($event['tournament']) && str_starts_with($event['key'], 'st');
    }

    /** @param array<string,mixed> $event */
    private function eventGender(array $event): string
    {
        return (string) ($event['tournament']['gender'] ?? 'M');
    }
}
