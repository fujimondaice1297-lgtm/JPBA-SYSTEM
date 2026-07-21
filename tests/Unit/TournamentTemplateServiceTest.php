<?php

namespace Tests\Unit;

use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use App\Services\TournamentTemplateService;
use Tests\TestCase;

class TournamentTemplateServiceTest extends TestCase
{
    public function test_prefill_restores_rules_and_non_column_outputs(): void
    {
        $template = new TournamentTemplate([
            'tournament_series_id' => 7,
            'name' => 'シーズントライアル標準',
        ]);
        $version = new TournamentTemplateVersion([
            'settings' => [
                'tournament' => [
                    'competition_type' => 'singles',
                    'title_scope' => 'season_trial',
                ],
                'organizations' => [],
                'entry_rules' => [
                    ['rule_type' => 'past_champions', 'is_active' => true],
                    [
                        'rule_type' => 'source_tournament_top_n',
                        'source_tournament_id' => 61,
                        'max_count' => 8,
                        'is_active' => true,
                    ],
                ],
                'result_outputs' => [
                    [
                        'output_type' => 'points',
                        'output_scope' => 'season_trial_championship',
                        'is_active' => true,
                    ],
                    [
                        'output_type' => 'qualification',
                        'output_scope' => 'entry_priority',
                        'is_active' => true,
                    ],
                ],
            ],
        ]);
        $version->setAttribute('id', 15);
        $version->setRelation('template', $template);

        $prefill = app(TournamentTemplateService::class)->prefill($version);

        $this->assertSame(7, $prefill['tournament_series_id']);
        $this->assertSame(15, $prefill['tournament_template_version_id']);
        $this->assertTrue($prefill['counts_for_season_trial_points']);
        $this->assertTrue($prefill['produces_entry_priority']);
        $this->assertContains('past_champions', $prefill['priority_rule_types']);
        $this->assertNull($prefill['priority_source_tournament_id']);
        $this->assertSame(8, $prefill['priority_source_tournament_top_n']);
    }
}
