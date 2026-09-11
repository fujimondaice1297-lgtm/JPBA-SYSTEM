<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Models\TournamentEdition;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JapanOpenFormatService
{
    public const SERIES_CODE = 'jpba-japan-open';

    public const TEMPLATE_CODE = 'japan-open-standard';

    /** @return array<string,array<string,mixed>> */
    public function components(): array
    {
        return [
            'overview' => $this->component('大会総合案内', 'X', 'championship', [], false, false, false, false),
            'men_team' => $this->component('男子チーム戦', 'M', 'team', ['チーム戦' => 3], true),
            'men_doubles' => $this->component('男子ダブルス戦', 'M', 'doubles', ['ダブルス戦' => 3], true),
            'men_singles' => $this->component('男子シングルス戦', 'M', 'singles', ['シングルス戦' => 3], true),
            'men_all_events' => $this->component('男子オールエベンツ', 'M', 'all_events', [], false),
            'masters' => $this->component('男子マスターズ', 'M', 'championship', ['予選' => 8, '準決勝' => 6], true, true, true, true),
            'women_team' => $this->component('女子チーム戦', 'F', 'team', ['チーム戦' => 3], true),
            'women_doubles' => $this->component('女子ダブルス戦', 'F', 'doubles', ['ダブルス戦' => 3], true),
            'women_singles' => $this->component('女子シングルス戦', 'F', 'singles', ['シングルス戦' => 3], true),
            'women_all_events' => $this->component('女子オールエベンツ', 'F', 'all_events', [], false),
            'queens' => $this->component('女子クイーンズ', 'F', 'championship', ['予選' => 8, '準決勝' => 6], true, true, true, true),
        ];
    }

    /** @return array<string,mixed> */
    public function blueprint(): array
    {
        return [
            'schema_version' => 1,
            'blueprint_type' => 'japan_open',
            'competition_rules' => [
                'team_member_count' => 4,
                'team_max_professionals' => 2,
                'doubles_member_count' => 2,
                'doubles_max_professionals' => 1,
                'all_events_sources' => ['team', 'doubles', 'singles'],
                'all_events_games_per_player' => 9,
                'masters_field_size' => 125,
                'queens_field_size' => 100,
                'masters_qualifier_selection' => 'per_shift_excluding_direct_seeds',
                'queens_qualifier_selection' => 'overall_excluding_direct_seeds',
                'masters_queens_preliminary_games' => 8,
                'masters_queens_semifinal_games' => 6,
                'masters_queens_semifinal_total_games' => 14,
                'final_format' => 'double_elimination',
            ],
            'accounting_policy' => [
                'category_aggregates_are_official_individual_results' => false,
                'all_events_is_title' => false,
                'official_points_components' => ['masters', 'queens'],
                'official_average_components' => [
                    'men_team', 'men_doubles', 'men_singles', 'masters',
                    'women_team', 'women_doubles', 'women_singles', 'queens',
                ],
                'official_prize_components' => ['masters', 'queens'],
                'official_title_components' => ['masters', 'queens'],
            ],
            'components' => $this->components(),
        ];
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function setup(array $options, bool $write = false): array
    {
        $normalized = $this->normalizeOptions($options);
        $existing = TournamentSeries::query()->where('code', self::SERIES_CODE)->first();
        $existingEdition = $existing?->editions()
            ->where('year', $normalized['year'])
            ->where('season_key', 'annual')
            ->first();

        if (! $write) {
            return [
                'mode' => 'dry-run',
                'options' => $normalized,
                'component_count' => count($this->components()),
                'existing_edition_id' => $existingEdition?->id,
                'would_create_edition' => $existingEdition === null,
            ];
        }

        return DB::transaction(function () use ($normalized): array {
            $series = TournamentSeries::query()->firstOrNew(['code' => self::SERIES_CODE]);
            $series->fill([
                'name' => 'ジャパンオープンボウリング選手権',
                'recurrence_type' => 'annual',
                'description' => '4人チーム戦、ダブルス戦、シングルス戦、9Gオールエベンツ、男子マスターズ・女子クイーンズを一体管理する年次大会。',
                'is_active' => true,
            ]);
            $series->save();

            $edition = TournamentEdition::query()->firstOrNew([
                'tournament_series_id' => $series->id,
                'year' => $normalized['year'],
                'season_key' => 'annual',
            ]);
            $editionWasNew = ! $edition->exists;
            $edition->fill([
                'name' => $normalized['name'],
                'edition_no' => $normalized['edition_no'],
                'status' => 'draft',
                'start_date' => $normalized['start_date'],
                'end_date' => $normalized['end_date'],
                'notes' => '大会総合案内と男女各5競技を同一年度開催として管理する。',
            ]);
            $edition->save();

            [$template, $version, $versionCreated] = $this->ensureTemplate($series);
            $existingComponents = $this->componentTournamentMap($edition);
            $tournaments = [];
            $createdIds = [];
            $updatedIds = [];

            foreach ($this->components() as $code => $component) {
                $tournament = $existingComponents[$code] ?? new Tournament;
                $wasNew = ! $tournament->exists;
                $settings = $this->tournamentSettings($code, $component);

                $tournament->fill([
                    'tournament_series_id' => $series->id,
                    'tournament_edition_id' => $edition->id,
                    'tournament_template_version_id' => $version->id,
                    'name' => $normalized['name'].' '.$component['label'],
                    'setup_status' => $tournament->setup_status ?: 'draft',
                    'competition_type' => $component['competition_type'],
                    'start_date' => $normalized['start_date'],
                    'end_date' => $normalized['end_date'],
                    'year' => $normalized['year'],
                    'venue_name' => $normalized['venue_name'],
                    'venue_address' => $normalized['venue_address'],
                    'gender' => $component['gender'],
                    'official_type' => 'official',
                    'title_category' => $component['counts_for_title'] ? 'normal' : 'excluded',
                    'include_annual_seeds' => in_array($code, ['masters', 'queens'], true),
                    'auto_sync_priority_rules' => false,
                    'counts_for_official_points' => $component['counts_for_points'],
                    'counts_for_average' => $component['counts_for_average'],
                    'counts_for_prize' => $component['counts_for_prize'],
                    'title_scope' => $component['counts_for_title'] ? 'official' : 'none',
                    'inspection_required' => true,
                    'ball_registration_limit' => $normalized['ball_registration_limit'],
                    'result_flow_type' => 'legacy_standard',
                    'template_snapshot' => $settings,
                ]);
                $dirty = $wasNew || $tournament->isDirty();
                $tournament->save();

                $this->syncStages($tournament, $component['stages']);
                $this->syncOutputs($tournament, $component);
                $tournaments[$code] = $tournament;
                if ($wasNew) {
                    $createdIds[] = $tournament->id;
                } elseif ($dirty) {
                    $updatedIds[] = $tournament->id;
                }
            }

            $this->syncAggregateDefinitions($tournaments);

            return [
                'mode' => 'write',
                'series_id' => $series->id,
                'edition_id' => $edition->id,
                'edition_created' => $editionWasNew,
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'template_version' => $version->version,
                'template_version_created' => $versionCreated,
                'component_count' => count($tournaments),
                'component_ids' => collect($tournaments)->map(fn (Tournament $tournament) => $tournament->id)->all(),
                'created_tournament_ids' => $createdIds,
                'updated_tournament_ids' => $updatedIds,
            ];
        });
    }

    /** @return array<string,mixed> */
    private function component(
        string $label,
        string $gender,
        string $competitionType,
        array $stages,
        bool $hasPhysicalScores,
        bool $countsForPoints = false,
        bool $countsForPrize = false,
        bool $countsForTitle = false,
    ): array {
        return [
            'label' => $label,
            'gender' => $gender,
            'competition_type' => $competitionType,
            'stages' => $stages,
            'has_physical_scores' => $hasPhysicalScores,
            'counts_for_points' => $countsForPoints,
            // Team, doubles and singles are real played games. All Events is only
            // a 9G aggregate of those games and must never count them twice.
            'counts_for_average' => $hasPhysicalScores,
            'counts_for_prize' => $countsForPrize,
            'counts_for_title' => $countsForTitle,
        ];
    }

    /** @param array<string,mixed> $options */
    private function normalizeOptions(array $options): array
    {
        $year = (int) ($options['year'] ?? now()->year);
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('年度は2000年から2100年の範囲で指定してください。');
        }

        $startDate = $this->dateValue($options['start_date'] ?? null);
        $endDate = $this->dateValue($options['end_date'] ?? null);
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new InvalidArgumentException('終了日は開始日以後にしてください。');
        }

        $editionNo = isset($options['edition_no']) && $options['edition_no'] !== ''
            ? (int) $options['edition_no']
            : null;
        $defaultName = ($editionNo ? '第'.$editionNo.'回 ' : '').$year.'ジャパンオープンボウリング選手権';

        return [
            'year' => $year,
            'edition_no' => $editionNo,
            'name' => trim((string) ($options['name'] ?? '')) ?: $defaultName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'venue_name' => trim((string) ($options['venue_name'] ?? '')) ?: null,
            'venue_address' => trim((string) ($options['venue_address'] ?? '')) ?: null,
            'ball_registration_limit' => max(1, min(99, (int) ($options['ball_registration_limit'] ?? 12))),
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === ''
            ? null
            : Carbon::parse($value)->toDateString();
    }

    /** @return array{0:TournamentTemplate,1:TournamentTemplateVersion,2:bool} */
    private function ensureTemplate(TournamentSeries $series): array
    {
        $template = TournamentTemplate::query()->firstOrNew(['code' => self::TEMPLATE_CODE]);
        $template->fill([
            'tournament_series_id' => $series->id,
            'name' => 'ジャパンオープン標準（団体・オールエベンツ・男女決勝）',
            'description' => '日付、会場、選手、スコアを含まない、毎年度複製用の11競技構成。',
            'is_active' => true,
        ]);
        $template->save();

        $settings = $this->blueprint();
        $latest = $template->versions()->orderByDesc('version')->first();
        $created = $latest === null || $latest->settings !== $settings;
        if ($created) {
            $latest = $template->versions()->create([
                'version' => ((int) $template->versions()->max('version')) + 1,
                'status' => 'published',
                'settings' => $settings,
                'change_note' => 'ジャパンオープン標準競技、合算規則、二重計上防止規則を保存。',
                'published_at' => now(),
            ]);
        }

        return [$template, $latest, $created];
    }

    /** @return array<string,Tournament> */
    private function componentTournamentMap(TournamentEdition $edition): array
    {
        return $edition->tournaments()->get()->mapWithKeys(function (Tournament $tournament): array {
            $code = trim((string) data_get($tournament->template_snapshot, 'japan_open.component_code'));

            return $code === '' ? [] : [$code => $tournament];
        })->all();
    }

    /** @param array<string,mixed> $component */
    private function tournamentSettings(string $code, array $component): array
    {
        return [
            'schema_version' => 1,
            'blueprint_type' => 'japan_open',
            'japan_open' => [
                'component_code' => $code,
                'component_label' => $component['label'],
                'hidden_from_public_index' => $code !== 'overview',
                'has_physical_scores' => $component['has_physical_scores'],
                'max_pro_per_group' => $component['competition_type'] === 'team'
                    ? 2
                    : ($component['competition_type'] === 'doubles' ? 1 : null),
                'official_accounting' => in_array($code, ['masters', 'queens'], true),
                'final_format' => in_array($code, ['masters', 'queens'], true) ? 'double_elimination' : null,
                'advancement_field_size' => match ($code) {
                    'men_all_events', 'masters' => 125,
                    'women_all_events', 'queens' => 100,
                    default => null,
                },
                'advancement_selection_mode' => match ($code) {
                    'men_all_events', 'masters' => 'per_shift_excluding_direct_seeds',
                    'women_all_events', 'queens' => 'overall_excluding_direct_seeds',
                    default => null,
                },
                'aggregate_results_do_not_publish_to_individual_rankings' => true,
            ],
        ];
    }

    /** @param array<string,int> $stages */
    private function syncStages(Tournament $tournament, array $stages): void
    {
        foreach ($stages as $stage => $totalGames) {
            DB::table('stage_settings')->updateOrInsert(
                ['tournament_id' => $tournament->id, 'stage' => $stage],
                ['total_games' => $totalGames, 'enabled' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /** @param array<string,mixed> $component */
    private function syncOutputs(Tournament $tournament, array $component): void
    {
        $outputs = [];
        if ($component['counts_for_points']) {
            $outputs[] = ['output_type' => 'points', 'output_scope' => 'official'];
        }
        if ($component['counts_for_average']) {
            $outputs[] = ['output_type' => 'average', 'output_scope' => 'official'];
        }
        if ($component['counts_for_prize']) {
            $outputs[] = ['output_type' => 'prize', 'output_scope' => 'official'];
        }
        if ($component['counts_for_title']) {
            $outputs[] = ['output_type' => 'title', 'output_scope' => 'official'];
        }

        foreach ($outputs as $output) {
            DB::table('tournament_result_outputs')->updateOrInsert(
                ['tournament_id' => $tournament->id] + $output,
                ['is_active' => true, 'distribution_pattern_id' => null, 'settings' => null, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /** @param array<string,Tournament> $tournaments */
    private function syncAggregateDefinitions(array $tournaments): void
    {
        foreach ([
            'men_team' => ['code' => 'team_total', 'name' => '男子チーム戦 3G成績', 'stage' => 'チーム戦'],
            'men_doubles' => ['code' => 'doubles_total', 'name' => '男子ダブルス戦 3G成績', 'stage' => 'ダブルス戦'],
            'women_team' => ['code' => 'team_total', 'name' => '女子チーム戦 3G成績', 'stage' => 'チーム戦'],
            'women_doubles' => ['code' => 'doubles_total', 'name' => '女子ダブルス戦 3G成績', 'stage' => 'ダブルス戦'],
        ] as $componentCode => $definitionRow) {
            $tournament = $tournaments[$componentCode];
            $definition = $this->upsertDefinition($tournament, [
                'code' => $definitionRow['code'],
                'name' => $definitionRow['name'],
                'subject_type' => 'group',
                'gender' => $tournament->gender,
                'notes' => '団体順位専用。個人ポイント・賞金・タイトルへは反映しない。',
            ]);
            $this->replaceSources($definition, [[
                'source_tournament_id' => $tournament->id,
                'label' => $definitionRow['name'],
                'stage' => $definitionRow['stage'],
                'game_from' => 1,
                'game_to' => 3,
                'expected_games_per_member' => 3,
            ]]);
        }

        foreach (['men' => 'M', 'women' => 'F'] as $prefix => $gender) {
            $definition = $this->upsertDefinition($tournaments[$prefix.'_all_events'], [
                'code' => 'all_events_9g',
                'name' => ($gender === 'M' ? '男子' : '女子').'オールエベンツ 9G成績',
                'subject_type' => 'individual',
                'gender' => $gender,
                'notes' => 'チーム戦・ダブルス戦・シングルス戦の個人各3Gを一度ずつ合算。公式タイトルへは反映しない。',
            ]);
            $this->replaceSources($definition, [
                $this->source($tournaments[$prefix.'_team'], ($gender === 'M' ? '男子' : '女子').'チーム戦', 'チーム戦'),
                $this->source($tournaments[$prefix.'_doubles'], ($gender === 'M' ? '男子' : '女子').'ダブルス戦', 'ダブルス戦'),
                $this->source($tournaments[$prefix.'_singles'], ($gender === 'M' ? '男子' : '女子').'シングルス戦', 'シングルス戦'),
            ]);
        }
    }

    /** @param array<string,mixed> $attributes */
    private function upsertDefinition(Tournament $tournament, array $attributes): TournamentAggregateDefinition
    {
        return TournamentAggregateDefinition::query()->updateOrCreate(
            ['tournament_id' => $tournament->id, 'code' => $attributes['code']],
            $attributes + [
                'tie_break_policy' => 'shared_rank',
                'require_all_sources' => true,
                'is_published' => true,
                'is_active' => true,
            ],
        );
    }

    /** @param array<int,array<string,mixed>> $sources */
    private function replaceSources(TournamentAggregateDefinition $definition, array $sources): void
    {
        $retainedIds = [];
        foreach ($sources as $index => $source) {
            $row = $definition->sources()->updateOrCreate(
                [
                    'source_tournament_id' => $source['source_tournament_id'],
                    'stage' => $source['stage'],
                    'game_from' => $source['game_from'],
                    'game_to' => $source['game_to'],
                ],
                $source + [
                    'is_required' => true,
                    'sort_order' => $index + 1,
                ],
            );
            $retainedIds[] = $row->id;
        }

        $definition->sources()->whereNotIn('id', $retainedIds)->delete();
    }

    /** @return array<string,mixed> */
    private function source(Tournament $tournament, string $label, string $stage): array
    {
        return [
            'source_tournament_id' => $tournament->id,
            'label' => $label,
            'stage' => $stage,
            'game_from' => 1,
            'game_to' => 3,
            'expected_games_per_member' => 3,
        ];
    }
}
