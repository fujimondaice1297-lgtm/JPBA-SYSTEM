<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TournamentTemplateService
{
    private const TEMPLATE_COLUMNS = [
        'venue_id',
        'venue_name',
        'venue_address',
        'venue_tel',
        'venue_fax',
        'gender',
        'official_type',
        'title_category',
        'competition_type',
        'include_annual_seeds',
        'annual_seed_rank_limit',
        'auto_sync_priority_rules',
        'counts_for_official_points',
        'counts_for_average',
        'counts_for_prize',
        'title_scope',
        'result_flow_type',
        'round_robin_qualifier_count',
        'round_robin_win_bonus',
        'round_robin_tie_bonus',
        'round_robin_position_round_enabled',
        'single_elimination_qualifier_count',
        'single_elimination_seed_source_result_code',
        'single_elimination_seed_policy',
        'single_elimination_seed_settings',
        'shootout_qualifier_count',
        'shootout_seed_source_result_code',
        'shootout_format',
        'shootout_settings',
        'result_carry_preset',
        'result_carry_settings',
        'spectator_policy',
        'broadcast',
        'streaming',
        'broadcast_url',
        'streaming_url',
        'prize',
        'admission_fee',
        'entry_conditions',
        'materials',
        'inspection_required',
        'use_shift_draw',
        'shift_codes',
        'accept_shift_preference',
        'use_lane_draw',
        'lane_from',
        'lane_to',
        'lane_assignment_mode',
        'box_player_count',
        'odd_lane_player_count',
        'even_lane_player_count',
        'lane_movement_settings',
        'extra_venues',
        'sidebar_schedule',
        'award_highlights',
        'result_cards',
        'title_logo_path',
        'host',
        'special_sponsor',
        'sponsor',
        'support',
        'supervisor',
        'authorized_by',
    ];

    public function createVersion(
        Tournament $source,
        string $name,
        ?TournamentSeries $series = null,
        ?TournamentTemplate $template = null,
        ?string $code = null,
        ?string $description = null,
        ?string $changeNote = null,
    ): TournamentTemplateVersion {
        return DB::transaction(function () use (
            $source,
            $name,
            $series,
            $template,
            $code,
            $description,
            $changeNote,
        ): TournamentTemplateVersion {
            if ($template === null) {
                $template = TournamentTemplate::query()->create([
                    'tournament_series_id' => $series?->id,
                    'name' => $name,
                    'code' => $this->uniqueCode($code ?: $name),
                    'description' => $description,
                    'is_active' => true,
                ]);
            } else {
                $template->update([
                    'tournament_series_id' => $series?->id ?? $template->tournament_series_id,
                    'name' => $name ?: $template->name,
                    'description' => $description ?? $template->description,
                ]);
            }

            $nextVersion = ((int) $template->versions()->max('version')) + 1;

            return $template->versions()->create([
                'version' => $nextVersion,
                'status' => 'published',
                'settings' => $this->capture($source),
                'change_note' => $changeNote,
                'published_at' => now(),
            ]);
        });
    }

    public function capture(Tournament $source): array
    {
        $settings = [
            'schema_version' => 1,
            'source_tournament_id' => (int) $source->id,
            'tournament' => Arr::only($source->attributesToArray(), self::TEMPLATE_COLUMNS),
            'organizations' => $source->organizations()
                ->orderBy('sort_order')
                ->get(['category', 'name', 'url', 'sort_order'])
                ->toArray(),
            'stage_settings' => $this->tableRows(
                'stage_settings',
                $source->id,
                ['stage', 'total_games', 'enabled']
            ),
            'point_distributions' => $this->tableRows(
                'point_distributions',
                $source->id,
                ['rank', 'points', 'pattern_id']
            ),
            'prize_distributions' => $this->tableRows(
                'prize_distributions',
                $source->id,
                ['rank', 'amount', 'pattern_id']
            ),
            'entry_rules' => $this->tableRows(
                'tournament_entry_rules',
                $source->id,
                [
                    'rule_type',
                    'priority_order',
                    'max_count',
                    'source_tournament_id',
                    'source_series_id',
                    'parameters',
                    'auto_sync',
                    'is_active',
                ]
            ),
            'result_outputs' => $this->tableRows(
                'tournament_result_outputs',
                $source->id,
                [
                    'output_type',
                    'output_scope',
                    'distribution_pattern_id',
                    'settings',
                    'is_active',
                ]
            ),
        ];

        return $settings;
    }

    public function prefill(TournamentTemplateVersion $version): array
    {
        $version->loadMissing('template');
        $settings = (array) $version->settings;
        $prefill = (array) ($settings['tournament'] ?? []);

        $prefill['tournament_series_id'] = $version->template->tournament_series_id;
        $prefill['tournament_template_version_id'] = $version->id;
        $prefill['template_name'] = $version->template->name;
        $prefill['org'] = (array) ($settings['organizations'] ?? []);
        $prefill['priority_rule_types'] = collect($settings['entry_rules'] ?? [])
            ->where('is_active', true)
            ->pluck('rule_type')
            ->values()
            ->all();

        $outputs = collect($settings['result_outputs'] ?? [])->where('is_active', true);
        $prefill['counts_for_season_trial_points'] = $outputs->contains(
            fn (array $output): bool => ($output['output_type'] ?? null) === 'points'
                && ($output['output_scope'] ?? null) === 'season_trial_championship'
        );
        $prefill['produces_entry_priority'] = $outputs->contains(
            fn (array $output): bool => ($output['output_type'] ?? null) === 'qualification'
                && ($output['output_scope'] ?? null) === 'entry_priority'
        );

        $sourceRule = collect($settings['entry_rules'] ?? [])
            ->firstWhere('rule_type', 'source_tournament_top_n');
        if (is_array($sourceRule)) {
            $prefill['priority_source_tournament_id'] = null;
            $prefill['priority_source_tournament_top_n'] = $sourceRule['max_count'] ?? null;
        }

        foreach ([
            'start_date',
            'end_date',
            'entry_start',
            'entry_end',
            'shift_draw_open_at',
            'shift_draw_close_at',
            'lane_draw_open_at',
            'lane_draw_close_at',
            'shift_auto_draw_reminder_send_on',
            'lane_auto_draw_reminder_send_on',
        ] as $dateField) {
            $prefill[$dateField] = null;
        }

        return $prefill;
    }

    public function applyRelatedSettings(Tournament $tournament, TournamentTemplateVersion $version): void
    {
        $settings = (array) $version->settings;

        DB::transaction(function () use ($tournament, $version, $settings): void {
            $this->replaceRows(
                'stage_settings',
                $tournament->id,
                (array) ($settings['stage_settings'] ?? []),
                ['stage', 'total_games', 'enabled']
            );
            $this->replaceRows(
                'point_distributions',
                $tournament->id,
                (array) ($settings['point_distributions'] ?? []),
                ['rank', 'points', 'pattern_id']
            );
            $this->replaceRows(
                'prize_distributions',
                $tournament->id,
                (array) ($settings['prize_distributions'] ?? []),
                ['rank', 'amount', 'pattern_id']
            );
            $this->replaceRows(
                'tournament_entry_rules',
                $tournament->id,
                (array) ($settings['entry_rules'] ?? []),
                [
                    'rule_type',
                    'priority_order',
                    'max_count',
                    'source_tournament_id',
                    'source_series_id',
                    'parameters',
                    'auto_sync',
                    'is_active',
                ]
            );
            $this->replaceRows(
                'tournament_result_outputs',
                $tournament->id,
                (array) ($settings['result_outputs'] ?? []),
                [
                    'output_type',
                    'output_scope',
                    'distribution_pattern_id',
                    'settings',
                    'is_active',
                ]
            );

            $tournament->forceFill([
                'tournament_template_version_id' => $version->id,
                'template_snapshot' => $settings,
            ])->save();
        });
    }

    private function tableRows(string $table, int $tournamentId, array $columns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('tournament_id', $tournamentId)
            ->orderBy('id')
            ->get($columns)
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    private function replaceRows(
        string $table,
        int $tournamentId,
        array $rows,
        array $allowedColumns,
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('tournament_id', $tournamentId)->delete();

        foreach ($rows as $row) {
            $payload = Arr::only((array) $row, $allowedColumns);
            $payload['tournament_id'] = $tournamentId;

            foreach (['parameters', 'settings'] as $jsonColumn) {
                if (array_key_exists($jsonColumn, $payload) && is_array($payload[$jsonColumn])) {
                    $payload[$jsonColumn] = json_encode(
                        $payload[$jsonColumn],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
            }

            if (Schema::hasColumn($table, 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table($table)->insert($payload);
        }
    }

    private function uniqueCode(string $value): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'tournament-template';
        }

        $code = $base;
        $suffix = 2;
        while (TournamentTemplate::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
