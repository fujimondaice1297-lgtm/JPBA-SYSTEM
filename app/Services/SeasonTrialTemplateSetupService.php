<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEdition;
use App\Models\TournamentResultOutput;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class SeasonTrialTemplateSetupService
{
    private const SERIES_CODE = 'jpba-season-trial';

    private const TEMPLATE_CODE = 'season-trial-standard';

    private const STANDARD_OUTPUTS = [
        ['output_type' => 'points', 'output_scope' => 'season_trial_championship'],
        ['output_type' => 'qualification', 'output_scope' => 'entry_priority'],
        ['output_type' => 'average', 'output_scope' => 'official'],
        ['output_type' => 'prize', 'output_scope' => 'official'],
        ['output_type' => 'title', 'output_scope' => 'season_trial'],
    ];

    public function __construct(
        private readonly TournamentTemplateService $templateService,
        private readonly TournamentResultCarryService $carryService,
    ) {}

    /** @param array<string,mixed> $options */
    public function setup(int $tournamentId, bool $write, array $options = []): array
    {
        $source = Tournament::query()->findOrFail($tournamentId);
        $this->assertEligibleSource($source);

        $target = $this->targetMetadata($source, $options);
        $protectedBefore = $this->protectedDataFingerprint($source->id);

        if (! $write) {
            return $this->report(
                mode: 'dry-run',
                source: $source,
                target: $target,
                protectedBefore: $protectedBefore,
            );
        }

        return DB::transaction(function () use ($source, $target, $protectedBefore): array {
            $source = Tournament::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();

            $series = TournamentSeries::query()->firstOrNew(['code' => self::SERIES_CODE]);
            $seriesCreated = ! $series->exists;
            $seriesChanged = $this->saveIfDirty($series, [
                'name' => 'JPBAシーズントライアル',
                'recurrence_type' => 'seasonal',
                'description' => '男子プロが季節ごとに1会場へ出場し、STチャンピオンシップポイントと後続大会の出場優先順位を決定するシリーズ。',
                'is_active' => true,
            ]);

            $edition = TournamentEdition::query()->firstOrNew([
                'tournament_series_id' => $series->id,
                'year' => $target['year'],
                'season_key' => $target['season_key'],
            ]);
            $editionCreated = ! $edition->exists;
            $editionChanged = $this->saveIfDirty($edition, [
                'name' => $target['edition_name'],
                'status' => $target['edition_status'],
                'start_date' => $target['edition_start'],
                'end_date' => $target['edition_end'],
                'notes' => '各会場を同一年度開催へ束ねる。選手・スコア・成績は会場別大会に保持する。',
            ]);

            $sourceChanged = $this->saveIfDirty($source, [
                'tournament_series_id' => $series->id,
                'tournament_edition_id' => $edition->id,
                'include_annual_seeds' => false,
                'annual_seed_rank_limit' => null,
                'auto_sync_priority_rules' => true,
                'counts_for_official_points' => false,
                'counts_for_average' => true,
                'counts_for_prize' => true,
                'title_scope' => 'season_trial',
                'title_category' => 'season_trial',
                'result_carry_preset' => 'carry_prelim_semifinal_to_shootout_seed',
                'result_carry_settings' => $this->carryService->presetSettings(
                    'carry_prelim_semifinal_to_shootout_seed'
                ),
            ]);

            $stageChanged = $this->syncStandardStages($source->id);
            $outputsChanged = $this->syncStandardOutputs($source->id);

            $source->refresh();
            $settings = $this->standardizeCapturedSettings($this->templateService->capture($source));

            $template = TournamentTemplate::query()->firstOrNew(['code' => self::TEMPLATE_CODE]);
            $templateCreated = ! $template->exists;
            $templateChanged = $this->saveIfDirty($template, [
                'tournament_series_id' => $series->id,
                'name' => 'シーズントライアル標準（予選8G・準決勝4G・8名シュートアウト）',
                'description' => '会場・参加人数・選手・スコア・成績・賞金配分を含まないシーズントライアル標準設定。',
                'is_active' => true,
            ]);

            $latestVersion = $template->versions()->orderByDesc('version')->first();
            $versionCreated = $latestVersion === null || $latestVersion->settings !== $settings;

            if ($versionCreated) {
                $latestVersion = $template->versions()->create([
                    'version' => ((int) $template->versions()->max('version')) + 1,
                    'status' => 'published',
                    'settings' => $settings,
                    'change_note' => '大会ID'.$source->id.'を基に、会場固有情報と実績データを除外して標準化。',
                    'published_at' => now(),
                ]);
            }

            $templateLinkChanged = $this->saveIfDirty($source, [
                'tournament_template_version_id' => $latestVersion->id,
                'template_snapshot' => $settings,
            ]);

            $protectedAfter = $this->protectedDataFingerprint($source->id);
            if (! hash_equals($protectedBefore['checksum'], $protectedAfter['checksum'])) {
                throw new RuntimeException('Protected tournament data changed while setting up the template. The transaction was rolled back.');
            }

            return $this->report(
                mode: 'write',
                source: $source->fresh(),
                target: $target,
                protectedBefore: $protectedBefore,
                protectedAfter: $protectedAfter,
                entities: [
                    'series_id' => $series->id,
                    'edition_id' => $edition->id,
                    'template_id' => $template->id,
                    'template_version_id' => $latestVersion->id,
                    'template_version' => $latestVersion->version,
                ],
                changes: [
                    'series' => $seriesCreated || $seriesChanged,
                    'edition' => $editionCreated || $editionChanged,
                    'tournament' => $sourceChanged || $templateLinkChanged,
                    'stage_settings' => $stageChanged,
                    'result_outputs' => $outputsChanged,
                    'template' => $templateCreated || $templateChanged,
                    'template_version_created' => $versionCreated,
                ],
            );
        });
    }

    /** @param array<string,mixed> $settings */
    public function standardizeCapturedSettings(array $settings): array
    {
        $tournament = (array) ($settings['tournament'] ?? []);

        foreach ([
            'venue_id',
            'venue_name',
            'venue_address',
            'venue_tel',
            'venue_fax',
            'lane_from',
            'lane_to',
            'extra_venues',
            'sidebar_schedule',
            'award_highlights',
            'result_cards',
            'title_logo_path',
            'special_sponsor',
            'sponsor',
            'support',
            'broadcast',
            'streaming',
            'broadcast_url',
            'streaming_url',
            'spectator_policy',
            'admission_fee',
            'prize',
            'materials',
        ] as $field) {
            $tournament[$field] = null;
        }

        $tournament = array_replace($tournament, [
            'season_key' => '',
            'gender' => 'M',
            'official_type' => 'official',
            'title_category' => 'season_trial',
            'competition_type' => 'singles',
            'include_annual_seeds' => false,
            'annual_seed_rank_limit' => null,
            'auto_sync_priority_rules' => true,
            'counts_for_official_points' => false,
            'counts_for_average' => true,
            'counts_for_prize' => true,
            'title_scope' => 'season_trial',
            'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
            'shootout_qualifier_count' => 8,
            'shootout_seed_source_result_code' => 'semifinal_total',
            'shootout_format' => 'standard_8',
            'shootout_settings' => [
                'stage_progress' => [
                    'prelim_game_count' => 8,
                    'semifinal_game_count' => 4,
                    'semifinal_total_game_count' => 12,
                    'semifinal_qualifier_count' => 8,
                ],
            ],
            'result_carry_preset' => 'carry_prelim_semifinal_to_shootout_seed',
            'result_carry_settings' => $this->carryService->presetSettings(
                'carry_prelim_semifinal_to_shootout_seed'
            ),
        ]);

        $settings['tournament'] = $tournament;
        $settings['organizations'] = [];
        $settings['stage_settings'] = [
            ['stage' => '予選', 'total_games' => 8, 'enabled' => true],
            ['stage' => '準決勝', 'total_games' => 4, 'enabled' => true],
        ];
        $settings['point_distributions'] = [];
        $settings['prize_distributions'] = [];
        $settings['entry_rules'] = [];
        $settings['result_outputs'] = array_map(
            static fn (array $output): array => $output + [
                'distribution_pattern_id' => null,
                'settings' => null,
                'is_active' => true,
            ],
            self::STANDARD_OUTPUTS,
        );

        return $settings;
    }

    private function assertEligibleSource(Tournament $source): void
    {
        if ($source->title_category !== 'season_trial' || strtoupper((string) $source->gender) !== 'M') {
            throw new InvalidArgumentException('The source tournament must be a male season-trial tournament.');
        }

        if ($source->result_flow_type !== 'prelim_to_semifinal_to_shootout_to_final') {
            throw new InvalidArgumentException('The source tournament must use the season-trial shootout result flow.');
        }
    }

    /** @param array<string,mixed> $options */
    private function targetMetadata(Tournament $source, array $options): array
    {
        $year = (int) ($source->year ?: $source->start_date?->year ?: now()->year);
        $seasonKey = trim((string) ($options['season_key'] ?? 'summer')) ?: 'summer';
        $seasonLabel = [
            'spring' => 'スプリング',
            'summer' => 'サマー',
            'autumn' => 'オータム',
            'winter' => 'ウィンター',
        ][$seasonKey] ?? $seasonKey;

        $start = $this->dateValue($options['edition_start'] ?? $source->start_date);
        $end = $this->dateValue($options['edition_end'] ?? $source->end_date ?? $source->start_date);
        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidArgumentException('The edition end date must be on or after the start date.');
        }

        return [
            'year' => $year,
            'season_key' => $seasonKey,
            'edition_name' => trim((string) ($options['edition_name'] ?? ''))
                ?: "JPBAシーズントライアル{$year} {$seasonLabel}シリーズ",
            'edition_status' => trim((string) ($options['edition_status'] ?? 'in_progress')) ?: 'in_progress',
            'edition_start' => $start,
            'edition_end' => $end,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function syncStandardStages(int $tournamentId): bool
    {
        $changed = false;
        foreach ([
            '予選' => 8,
            '準決勝' => 4,
        ] as $stage => $games) {
            $row = DB::table('stage_settings')
                ->where('tournament_id', $tournamentId)
                ->where('stage', $stage)
                ->first();

            if ($row === null) {
                DB::table('stage_settings')->insert([
                    'tournament_id' => $tournamentId,
                    'stage' => $stage,
                    'total_games' => $games,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed = true;

                continue;
            }

            if ((int) $row->total_games !== $games || ! (bool) $row->enabled) {
                DB::table('stage_settings')->where('id', $row->id)->update([
                    'total_games' => $games,
                    'enabled' => true,
                    'updated_at' => now(),
                ]);
                $changed = true;
            }
        }

        return $changed;
    }

    private function syncStandardOutputs(int $tournamentId): bool
    {
        $changed = false;
        $desiredKeys = [];

        foreach (self::STANDARD_OUTPUTS as $output) {
            $desiredKeys[] = $output['output_type'].'|'.$output['output_scope'];
            $row = TournamentResultOutput::query()->firstOrNew([
                'tournament_id' => $tournamentId,
                'output_type' => $output['output_type'],
                'output_scope' => $output['output_scope'],
            ]);

            $wasNew = ! $row->exists;
            $row->fill([
                'distribution_pattern_id' => null,
                'settings' => null,
                'is_active' => true,
            ]);
            if ($wasNew || $row->isDirty()) {
                $row->save();
                $changed = true;
            }
        }

        $existing = TournamentResultOutput::query()->where('tournament_id', $tournamentId)->get();
        foreach ($existing as $row) {
            $key = $row->output_type.'|'.$row->output_scope;
            if (! in_array($key, $desiredKeys, true)) {
                $row->delete();
                $changed = true;
            }
        }

        return $changed;
    }

    private function saveIfDirty($model, array $attributes): bool
    {
        $wasNew = ! $model->exists;
        $model->fill($attributes);

        if ($wasNew || $model->isDirty()) {
            $model->save();

            return true;
        }

        return false;
    }

    private function protectedDataFingerprint(int $tournamentId): array
    {
        $tables = [];
        foreach ([
            'tournament_entries',
            'tournament_participants',
            'game_scores',
            'tournament_results',
            'tournament_result_snapshots',
            'tournament_seed_players',
            'shootout_matches',
            'tournament_match_score_sheets',
            'tournament_round_lane_assignments',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tournament_id')) {
                $tables[$table] = $this->fingerprintRows(
                    DB::table($table)->where('tournament_id', $tournamentId)
                );
            }
        }

        if (Schema::hasTable('tournament_result_snapshot_rows')) {
            $snapshotIds = DB::table('tournament_result_snapshots')
                ->where('tournament_id', $tournamentId)
                ->pluck('id');
            $tables['tournament_result_snapshot_rows'] = $this->fingerprintRows(
                DB::table('tournament_result_snapshot_rows')->whereIn('snapshot_id', $snapshotIds)
            );
        }

        if (Schema::hasTable('tournament_result_publication_rows')) {
            $publicationIds = DB::table('tournament_result_publications')
                ->where('tournament_id', $tournamentId)
                ->pluck('id');
            $tables['tournament_result_publication_rows'] = $this->fingerprintRows(
                DB::table('tournament_result_publication_rows')->whereIn('publication_id', $publicationIds)
            );
        }

        if (Schema::hasTable('pro_bowler_titles')) {
            $titleQuery = DB::table('pro_bowler_titles')->whereRaw('1 = 0');
            if (Schema::hasColumn('pro_bowler_titles', 'source_tournament_id')) {
                $titleQuery->orWhere('source_tournament_id', $tournamentId);
            }
            if (Schema::hasColumn('pro_bowler_titles', 'tournament_id')) {
                $titleQuery->orWhere('tournament_id', $tournamentId);
            }
            $tables['pro_bowler_titles'] = $this->fingerprintRows($titleQuery);
        }

        return [
            'checksum' => hash('sha256', $this->json($tables)),
            'tables' => $tables,
        ];
    }

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

    private function report(
        string $mode,
        Tournament $source,
        array $target,
        array $protectedBefore,
        ?array $protectedAfter = null,
        array $entities = [],
        array $changes = [],
    ): array {
        return [
            'mode' => $mode,
            'tournament_id' => $source->id,
            'tournament_name' => $source->name,
            'target' => $target,
            'entities' => $entities,
            'changes' => $changes,
            'protected_data_unchanged' => $protectedAfter === null
                ? null
                : hash_equals($protectedBefore['checksum'], $protectedAfter['checksum']),
            'protected_before' => $protectedBefore,
            'protected_after' => $protectedAfter,
            'standard' => [
                'annual_seeds_auto_inserted' => false,
                'official_points' => false,
                'season_trial_championship_points' => true,
                'entry_priority' => true,
                'average' => true,
                'prize' => true,
                'title_scope' => 'season_trial',
                'preliminary_games' => 8,
                'semifinal_games' => 4,
                'semifinal_total_games' => 12,
                'shootout_qualifiers' => 8,
                'venue_specific_fields_cleared_from_template' => true,
                'participant_counts_cleared_from_template' => true,
            ],
        ];
    }
}
