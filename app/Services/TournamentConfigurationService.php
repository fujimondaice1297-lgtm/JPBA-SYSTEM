<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEntryRule;
use App\Models\TournamentResultOutput;
use Illuminate\Support\Facades\DB;

class TournamentConfigurationService
{
    public function syncEntryRules(Tournament $tournament, array $input): void
    {
        $enabledTypes = collect($input['priority_rule_types'] ?? [])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($tournament, $input, $enabledTypes): void {
            $tournament->entryRules()->delete();

            $priority = 100;
            foreach ([
                TournamentEntryRule::PAST_CHAMPIONS,
                TournamentEntryRule::CURRENT_YEAR_WINNERS,
                TournamentEntryRule::PERMANENT_SEEDS,
            ] as $ruleType) {
                if (! $enabledTypes->contains($ruleType)) {
                    continue;
                }

                $tournament->entryRules()->create([
                    'rule_type' => $ruleType,
                    'priority_order' => $priority,
                    'source_series_id' => $ruleType === TournamentEntryRule::PAST_CHAMPIONS
                        ? $tournament->tournament_series_id
                        : null,
                    'auto_sync' => true,
                    'is_active' => true,
                ]);
                $priority += 100;
            }

            $sourceTournamentId = isset($input['priority_source_tournament_id'])
                ? (int) $input['priority_source_tournament_id']
                : 0;
            $sourceTopN = isset($input['priority_source_tournament_top_n'])
                ? (int) $input['priority_source_tournament_top_n']
                : 0;

            if ($sourceTournamentId > 0 && $sourceTopN > 0) {
                $tournament->entryRules()->create([
                    'rule_type' => TournamentEntryRule::SOURCE_TOURNAMENT_TOP_N,
                    'priority_order' => $priority,
                    'max_count' => $sourceTopN,
                    'source_tournament_id' => $sourceTournamentId,
                    'auto_sync' => true,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function syncResultOutputs(Tournament $tournament, array $input): void
    {
        $outputs = [];

        if (! empty($input['counts_for_official_points'])) {
            $outputs[] = ['output_type' => 'points', 'output_scope' => 'official'];
        }
        if (! empty($input['counts_for_season_trial_points'])) {
            $outputs[] = ['output_type' => 'points', 'output_scope' => 'season_trial_championship'];
        }
        if (! empty($input['produces_entry_priority'])) {
            $outputs[] = ['output_type' => 'qualification', 'output_scope' => 'entry_priority'];
        }
        if (! empty($input['counts_for_average'])) {
            $outputs[] = ['output_type' => 'average', 'output_scope' => 'official'];
        }
        if (! empty($input['counts_for_prize'])) {
            $outputs[] = ['output_type' => 'prize', 'output_scope' => 'official'];
        }

        $titleScope = trim((string) ($input['title_scope'] ?? 'none'));
        if ($titleScope !== '' && $titleScope !== 'none') {
            $outputs[] = ['output_type' => 'title', 'output_scope' => $titleScope];
        }

        DB::transaction(function () use ($tournament, $outputs): void {
            $tournament->resultOutputs()->delete();

            foreach ($outputs as $output) {
                TournamentResultOutput::query()->create([
                    'tournament_id' => $tournament->id,
                    'output_type' => $output['output_type'],
                    'output_scope' => $output['output_scope'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
