<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournaments')) {
            return;
        }

        $tournamentIds = $this->seasonTrialTournamentIds();

        DB::table('tournaments')
            ->whereIn('id', $tournamentIds)
            ->update([
                'counts_for_official_points' => true,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('tournament_result_outputs')) {
            foreach ($tournamentIds as $tournamentId) {
                $key = [
                    'tournament_id' => $tournamentId,
                    'output_type' => 'points',
                    'output_scope' => 'official',
                ];
                $output = DB::table('tournament_result_outputs')->where($key);
                $attributes = [
                    'distribution_pattern_id' => null,
                    'settings' => null,
                    'is_active' => true,
                    'updated_at' => now(),
                ];

                if ($output->exists()) {
                    $output->update($attributes);
                } else {
                    DB::table('tournament_result_outputs')->insert($key + $attributes + [
                        'created_at' => now(),
                    ]);
                }
            }
        }

        $this->updateSeasonTrialTemplateSettings(true);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tournaments')) {
            return;
        }

        $tournamentIds = $this->seasonTrialTournamentIds();

        if (Schema::hasTable('tournament_result_outputs')) {
            DB::table('tournament_result_outputs')
                ->whereIn('tournament_id', $tournamentIds)
                ->where('output_type', 'points')
                ->where('output_scope', 'official')
                ->delete();
        }

        DB::table('tournaments')
            ->whereIn('id', $tournamentIds)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('tournament_result_publications')
                    ->whereColumn('tournament_result_publications.tournament_id', 'tournaments.id');
            })
            ->update([
                'counts_for_official_points' => false,
                'updated_at' => now(),
            ]);

        $this->updateSeasonTrialTemplateSettings(false);
    }

    /** @return array<int,int> */
    private function seasonTrialTournamentIds(): array
    {
        return DB::table('tournaments')
            ->where(function ($query): void {
                $query->where('title_scope', 'season_trial')
                    ->orWhere('title_category', 'season_trial')
                    ->orWhere('name', 'like', '%シーズントライアル%');
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function updateSeasonTrialTemplateSettings(bool $enabled): void
    {
        if (! Schema::hasTable('tournament_templates') || ! Schema::hasTable('tournament_template_versions')) {
            return;
        }

        $versions = DB::table('tournament_template_versions as versions')
            ->join('tournament_templates as templates', 'templates.id', '=', 'versions.tournament_template_id')
            ->where('templates.code', 'season-trial-standard')
            ->select('versions.id', 'versions.settings')
            ->get();

        foreach ($versions as $version) {
            $settings = is_array($version->settings)
                ? $version->settings
                : json_decode((string) $version->settings, true);
            if (! is_array($settings)) {
                continue;
            }

            $settings['tournament'] = is_array($settings['tournament'] ?? null)
                ? $settings['tournament']
                : [];
            $settings['tournament']['counts_for_official_points'] = $enabled;

            $outputs = collect(is_array($settings['result_outputs'] ?? null) ? $settings['result_outputs'] : [])
                ->reject(fn ($output): bool => is_array($output)
                    && ($output['output_type'] ?? null) === 'points'
                    && ($output['output_scope'] ?? null) === 'official')
                ->values();

            if ($enabled) {
                $outputs->prepend([
                    'output_type' => 'points',
                    'output_scope' => 'official',
                    'distribution_pattern_id' => null,
                    'settings' => null,
                    'is_active' => true,
                ]);
            }
            $settings['result_outputs'] = $outputs->all();

            DB::table('tournament_template_versions')
                ->where('id', $version->id)
                ->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }
};
