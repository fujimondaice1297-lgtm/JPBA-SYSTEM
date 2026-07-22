<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEdition;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use App\Models\Venue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SeasonTrial2026CatalogService
{
    private const EXPECTED_EDITION_COUNT = 3;

    private const EXPECTED_EVENT_COUNT = 12;

    private const EXPECTED_PUBLISHED_RESULT_COUNT = 11;

    public function __construct(
        private readonly TournamentTemplateService $templateService,
        private readonly VenueNameNormalizer $venueNameNormalizer,
    ) {}

    /** @return array<string,mixed> */
    public function catalog(): array
    {
        $path = database_path('data/jpba_season_trial_2026.json');
        if (! is_file($path)) {
            throw new RuntimeException("Season trial catalog was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->validateCatalog($payload);

        return $payload;
    }

    /** @return array<string,mixed> */
    public function import(bool $write = false): array
    {
        $payload = $this->catalog();
        $series = TournamentSeries::query()->where('code', $payload['series_code'])->first();
        if (! $series) {
            throw new RuntimeException('Run jpba:setup-season-trial-template before importing the 2026 catalog.');
        }

        $template = TournamentTemplate::query()->where('code', $payload['template_code'])->first();
        $version = $template?->versions()->where('status', 'published')->orderByDesc('version')->first();
        if (! $template || ! $version) {
            throw new RuntimeException('Published season-trial standard template was not found.');
        }

        $venues = $this->resolveVenues($payload);
        $plan = $this->buildPlan($payload, $series, $version, $venues);
        $protectedBefore = $this->protectedDataFingerprint();

        $report = [
            'mode' => $write ? 'write' : 'dry-run',
            'dataset' => $payload['dataset'],
            'source_checked_at' => $payload['source_checked_at'],
            'series_id' => $series->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'template_version' => $version->version,
            'edition_create_count' => count($plan['edition_creates']),
            'edition_update_count' => count($plan['edition_updates']),
            'tournament_create_count' => count($plan['tournament_creates']),
            'tournament_existing_count' => count($plan['tournament_existing']),
            'conflict_count' => count($plan['conflicts']),
            'published_result_source_count' => self::EXPECTED_PUBLISHED_RESULT_COUNT,
            'pending_result_source_count' => self::EXPECTED_EVENT_COUNT - self::EXPECTED_PUBLISHED_RESULT_COUNT,
            'edition_creates' => $plan['edition_creates'],
            'edition_updates' => $plan['edition_updates'],
            'tournament_creates' => $plan['tournament_creates'],
            'tournament_existing' => $plan['tournament_existing'],
            'conflicts' => $plan['conflicts'],
            'created_tournament_ids' => [],
            'protected_data_unchanged' => null,
            'protected_before' => $protectedBefore,
            'protected_after' => null,
        ];

        if (! $write || $plan['conflicts'] !== []) {
            return $report;
        }

        return DB::transaction(function () use (
            $payload,
            $series,
            $version,
            $venues,
            $protectedBefore,
            $report,
        ): array {
            $series = TournamentSeries::query()->whereKey($series->id)->lockForUpdate()->firstOrFail();
            $editionIds = [];

            foreach ($payload['editions'] as $editionRow) {
                $edition = TournamentEdition::query()->firstOrNew([
                    'tournament_series_id' => $series->id,
                    'year' => (int) $payload['year'],
                    'season_key' => $editionRow['season_key'],
                ]);
                $edition->fill($this->editionAttributes($editionRow));
                if (! $edition->exists || $edition->isDirty()) {
                    $edition->save();
                }
                $editionIds[$editionRow['season_key']] = $edition->id;
            }

            foreach ($payload['editions'] as $editionRow) {
                foreach ($editionRow['events'] as $eventRow) {
                    $name = $this->tournamentName($editionRow, $eventRow);
                    $existing = Tournament::query()
                        ->where('year', (int) $payload['year'])
                        ->where('name', $name)
                        ->first();
                    if ($existing) {
                        continue;
                    }

                    $venue = $venues[$eventRow['venue_name']];
                    $tournament = Tournament::query()->create($this->tournamentAttributes(
                        payload: $payload,
                        editionRow: $editionRow,
                        eventRow: $eventRow,
                        series: $series,
                        editionId: $editionIds[$editionRow['season_key']],
                        version: $version,
                        venue: $venue,
                    ));
                    $this->templateService->applyRelatedSettings($tournament, $version);
                    $report['created_tournament_ids'][] = $tournament->id;
                }
            }

            $protectedAfter = $this->protectedDataFingerprint();
            if (! hash_equals($protectedBefore['checksum'], $protectedAfter['checksum'])) {
                throw new RuntimeException('Protected tournament result data changed. The catalog import was rolled back.');
            }

            $report['protected_data_unchanged'] = true;
            $report['protected_after'] = $protectedAfter;

            return $report;
        });
    }

    /** @param array<string,mixed> $payload */
    private function validateCatalog(array $payload): void
    {
        foreach (['dataset', 'source_checked_at', 'year', 'series_code', 'template_code', 'editions'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new RuntimeException("Season trial catalog is missing {$key}.");
            }
        }

        if ((int) $payload['year'] !== 2026) {
            throw new RuntimeException('Season trial catalog year must be 2026.');
        }
        if (count((array) $payload['editions']) !== self::EXPECTED_EDITION_COUNT) {
            throw new RuntimeException('Season trial catalog must contain winter, spring, and summer editions.');
        }

        $seasonKeys = [];
        $eventKeys = [];
        $publishedResults = 0;

        foreach ($payload['editions'] as $edition) {
            foreach (['season_key', 'name', 'status', 'start_date', 'end_date', 'source_url', 'events'] as $key) {
                if (blank($edition[$key] ?? null)) {
                    throw new RuntimeException("Season trial edition has a blank {$key} value.");
                }
            }

            $seasonKey = (string) $edition['season_key'];
            if (! in_array($seasonKey, ['winter', 'spring', 'summer'], true) || isset($seasonKeys[$seasonKey])) {
                throw new RuntimeException("Invalid or duplicate season key: {$seasonKey}");
            }
            $seasonKeys[$seasonKey] = true;

            if (count((array) $edition['events']) !== 4) {
                throw new RuntimeException("Season {$seasonKey} must contain four venue events.");
            }
            if (! str_starts_with((string) $edition['source_url'], 'https://www.jpba.or.jp/')) {
                throw new RuntimeException("Season {$seasonKey} has an invalid official source URL.");
            }

            foreach ($edition['events'] as $event) {
                foreach (['venue_code', 'date', 'venue_name'] as $key) {
                    if (blank($event[$key] ?? null)) {
                        throw new RuntimeException("Season {$seasonKey} event has a blank {$key} value.");
                    }
                }

                $venueCode = (string) $event['venue_code'];
                if (! in_array($venueCode, ['A', 'B', 'C', 'D'], true)) {
                    throw new RuntimeException("Season {$seasonKey} has an invalid venue code: {$venueCode}");
                }
                $eventKey = $seasonKey.'|'.$venueCode;
                if (isset($eventKeys[$eventKey])) {
                    throw new RuntimeException("Duplicate season trial event: {$eventKey}");
                }
                $eventKeys[$eventKey] = true;

                if ($event['date'] < $edition['start_date'] || $event['date'] > $edition['end_date']) {
                    throw new RuntimeException("Season trial event date is outside its edition: {$eventKey}");
                }

                $resultUrl = $event['final_result_url'] ?? null;
                if ($resultUrl !== null) {
                    if (! str_starts_with($resultUrl, 'https://www.jpba.or.jp/')
                        || ! str_ends_with(strtolower($resultUrl), '.pdf')) {
                        throw new RuntimeException("Season trial event has an invalid final result URL: {$eventKey}");
                    }
                    $publishedResults++;
                }
            }
        }

        if (count($eventKeys) !== self::EXPECTED_EVENT_COUNT
            || $publishedResults !== self::EXPECTED_PUBLISHED_RESULT_COUNT) {
            throw new RuntimeException('Season trial catalog event or published-result count is invalid.');
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,Venue>
     */
    private function resolveVenues(array $payload): array
    {
        $allVenues = Venue::query()->where('is_active', true)->get();
        $resolved = [];

        foreach ($payload['editions'] as $edition) {
            foreach ($edition['events'] as $event) {
                $name = $event['venue_name'];
                if (isset($resolved[$name])) {
                    continue;
                }

                $key = $this->venueNameNormalizer->normalize($name);
                $venue = $allVenues->first(function (Venue $candidate) use ($key): bool {
                    return collect([$candidate->name, ...($candidate->aliases ?? [])])
                        ->contains(fn ($candidateName): bool => $this->venueNameNormalizer->normalize($candidateName) === $key);
                });
                if (! $venue) {
                    throw new RuntimeException("Active venue was not found for season trial event: {$name}");
                }
                $resolved[$name] = $venue;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,Venue>  $venues
     * @return array<string,array<int,mixed>>
     */
    private function buildPlan(
        array $payload,
        TournamentSeries $series,
        TournamentTemplateVersion $version,
        array $venues,
    ): array {
        $plan = [
            'edition_creates' => [],
            'edition_updates' => [],
            'tournament_creates' => [],
            'tournament_existing' => [],
            'conflicts' => [],
        ];

        $editions = [];
        foreach ($payload['editions'] as $editionRow) {
            $edition = TournamentEdition::query()
                ->where('tournament_series_id', $series->id)
                ->where('year', (int) $payload['year'])
                ->where('season_key', $editionRow['season_key'])
                ->first();
            $editions[$editionRow['season_key']] = $edition;

            if (! $edition) {
                $plan['edition_creates'][] = $editionRow['season_key'];
            } else {
                $dirty = $this->dirtyAttributes($edition, $this->editionAttributes($editionRow));
                if ($dirty !== []) {
                    $plan['edition_updates'][] = [
                        'id' => $edition->id,
                        'season_key' => $editionRow['season_key'],
                        'fields' => array_keys($dirty),
                    ];
                }
            }

            foreach ($editionRow['events'] as $eventRow) {
                $name = $this->tournamentName($editionRow, $eventRow);
                $tournament = Tournament::query()
                    ->where('year', (int) $payload['year'])
                    ->where('name', $name)
                    ->first();
                $venue = $venues[$eventRow['venue_name']];

                if (! $tournament) {
                    $plan['tournament_creates'][] = [
                        'season_key' => $editionRow['season_key'],
                        'venue_code' => $eventRow['venue_code'],
                        'date' => $eventRow['date'],
                        'venue_id' => $venue->id,
                        'venue_name' => $venue->name,
                        'name' => $name,
                        'final_result_url' => $eventRow['final_result_url'],
                    ];

                    continue;
                }

                $conflicts = $this->existingTournamentConflicts(
                    tournament: $tournament,
                    series: $series,
                    edition: $editions[$editionRow['season_key']],
                    version: $version,
                    venue: $venue,
                    eventRow: $eventRow,
                );
                if ($conflicts !== []) {
                    $plan['conflicts'][] = [
                        'id' => $tournament->id,
                        'name' => $tournament->name,
                        'fields' => $conflicts,
                    ];

                    continue;
                }

                $plan['tournament_existing'][] = [
                    'id' => $tournament->id,
                    'season_key' => $editionRow['season_key'],
                    'venue_code' => $eventRow['venue_code'],
                    'name' => $tournament->name,
                ];
            }
        }

        return $plan;
    }

    /** @param array<string,mixed> $editionRow */
    private function editionAttributes(array $editionRow): array
    {
        return [
            'name' => $editionRow['name'],
            'status' => $editionRow['status'],
            'start_date' => $editionRow['start_date'],
            'end_date' => $editionRow['end_date'],
            'notes' => "各会場を同一年度開催へ束ねる。選手・スコア・成績は会場別大会に保持する。\n公式ページ: {$editionRow['source_url']}",
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $editionRow
     * @param  array<string,mixed>  $eventRow
     * @return array<string,mixed>
     */
    private function tournamentAttributes(
        array $payload,
        array $editionRow,
        array $eventRow,
        TournamentSeries $series,
        int $editionId,
        TournamentTemplateVersion $version,
        Venue $venue,
    ): array {
        $templateTournament = (array) ($version->settings['tournament'] ?? []);
        unset($templateTournament['season_key']);

        return array_replace($templateTournament, [
            'tournament_series_id' => $series->id,
            'tournament_edition_id' => $editionId,
            'tournament_template_version_id' => $version->id,
            'template_snapshot' => $version->settings,
            'name' => $this->tournamentName($editionRow, $eventRow),
            'setup_status' => 'draft',
            'start_date' => $eventRow['date'],
            'end_date' => $eventRow['date'],
            'year' => (int) $payload['year'],
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'venue_address' => $venue->address,
            'venue_tel' => $venue->tel,
            'venue_fax' => $venue->fax,
            'spectator_policy' => 'paid',
            'admission_fee' => '1,000円（賛助会員・高校生以下無料）',
            'entry_conditions' => '2026 JPBA男子トーナメントプロ（シードプロの参加可）',
        ]);
    }

    /**
     * @param  array<string,mixed>  $eventRow
     * @return array<int,string>
     */
    private function existingTournamentConflicts(
        Tournament $tournament,
        TournamentSeries $series,
        ?TournamentEdition $edition,
        TournamentTemplateVersion $version,
        Venue $venue,
        array $eventRow,
    ): array {
        $expected = [
            'tournament_series_id' => $series->id,
            'tournament_template_version_id' => $version->id,
            'start_date' => $eventRow['date'],
            'end_date' => $eventRow['date'],
            'venue_id' => $venue->id,
            'gender' => 'M',
            'official_type' => 'official',
            'title_category' => 'season_trial',
            'competition_type' => 'singles',
            'include_annual_seeds' => false,
            'counts_for_official_points' => false,
            'counts_for_average' => true,
            'counts_for_prize' => true,
            'title_scope' => 'season_trial',
            'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
        ];
        if ($edition) {
            $expected['tournament_edition_id'] = $edition->id;
        }

        return array_keys($this->dirtyAttributes($tournament, $expected));
    }

    /** @param array<string,mixed> $attributes */
    private function dirtyAttributes($model, array $attributes): array
    {
        $dirty = [];
        foreach ($attributes as $key => $expected) {
            $current = $model->getAttribute($key);
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d');
            }
            if (is_bool($expected)) {
                $current = (bool) $current;
            }
            if ((string) $current !== (string) $expected) {
                $dirty[$key] = ['current' => $current, 'expected' => $expected];
            }
        }

        return $dirty;
    }

    /**
     * @param  array<string,mixed>  $editionRow
     * @param  array<string,mixed>  $eventRow
     */
    private function tournamentName(array $editionRow, array $eventRow): string
    {
        return 'メリーランドカップ '.$editionRow['name'].' '.$eventRow['venue_code'].'会場';
    }

    /** @return array<string,mixed> */
    private function protectedDataFingerprint(): array
    {
        $tables = [];
        foreach ([
            'tournament_entries',
            'tournament_participants',
            'game_scores',
            'tournament_results',
            'tournament_result_snapshots',
            'tournament_result_snapshot_rows',
            'tournament_result_publications',
            'tournament_result_publication_rows',
            'tournament_seed_players',
            'shootout_matches',
            'tournament_match_score_sheets',
            'tournament_round_lane_assignments',
            'pro_bowler_titles',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $tables[$table] = $this->fingerprintRows(DB::table($table));
            }
        }

        return [
            'checksum' => hash('sha256', $this->json($tables)),
            'tables' => $tables,
        ];
    }

    /** @return array{count:int,checksum:string} */
    private function fingerprintRows(Builder $query): array
    {
        $rows = $query->get()
            ->map(function ($row): string {
                $values = (array) $row;
                ksort($values);

                return $this->json($values);
            })
            ->sort()
            ->values()
            ->all();

        return [
            'count' => count($rows),
            'checksum' => hash('sha256', $this->json($rows)),
        ];
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
