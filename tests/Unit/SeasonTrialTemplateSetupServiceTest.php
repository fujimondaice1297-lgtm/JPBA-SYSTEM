<?php

namespace Tests\Unit;

use App\Services\SeasonTrialTemplateSetupService;
use Tests\TestCase;

class SeasonTrialTemplateSetupServiceTest extends TestCase
{
    public function test_it_removes_event_specific_data_and_keeps_standard_season_trial_rules(): void
    {
        $settings = app(SeasonTrialTemplateSetupService::class)->standardizeCapturedSettings([
            'schema_version' => 1,
            'source_tournament_id' => 61,
            'tournament' => [
                'venue_id' => 18,
                'venue_name' => 'Source Venue',
                'lane_from' => 3,
                'lane_to' => 36,
                'include_annual_seeds' => true,
                'counts_for_official_points' => true,
                'shootout_settings' => [
                    'source' => 'source PDF',
                    'stage_progress' => [
                        'prelim_player_count' => 48,
                        'prelim_qualifier_count' => 24,
                    ],
                ],
            ],
            'organizations' => [['category' => 'host', 'name' => 'Source Host']],
            'stage_settings' => [],
            'point_distributions' => [['rank' => 1, 'points' => 100]],
            'prize_distributions' => [['rank' => 1, 'amount' => 100000]],
            'entry_rules' => [['rule_type' => 'past_champions']],
            'result_outputs' => [],
        ]);

        $tournament = $settings['tournament'];
        $this->assertNull($tournament['venue_id']);
        $this->assertNull($tournament['venue_name']);
        $this->assertNull($tournament['lane_from']);
        $this->assertNull($tournament['lane_to']);
        $this->assertSame('', $tournament['season_key']);
        $this->assertFalse($tournament['include_annual_seeds']);
        $this->assertFalse($tournament['counts_for_official_points']);
        $this->assertTrue($tournament['counts_for_average']);
        $this->assertTrue($tournament['counts_for_prize']);
        $this->assertSame('season_trial', $tournament['title_scope']);
        $this->assertArrayNotHasKey('source', $tournament['shootout_settings']);
        $this->assertArrayNotHasKey(
            'prelim_player_count',
            $tournament['shootout_settings']['stage_progress']
        );
        $this->assertArrayNotHasKey(
            'prelim_qualifier_count',
            $tournament['shootout_settings']['stage_progress']
        );
        $this->assertSame(8, $tournament['shootout_settings']['stage_progress']['prelim_game_count']);
        $this->assertSame(4, $tournament['shootout_settings']['stage_progress']['semifinal_game_count']);
        $this->assertSame(12, $tournament['shootout_settings']['stage_progress']['semifinal_total_game_count']);
        $this->assertSame(8, $tournament['shootout_settings']['stage_progress']['semifinal_qualifier_count']);
        $this->assertSame('carry_prelim_semifinal_to_shootout_seed', $tournament['result_carry_preset']);

        $this->assertSame([], $settings['organizations']);
        $this->assertSame([], $settings['point_distributions']);
        $this->assertSame([], $settings['prize_distributions']);
        $this->assertSame([], $settings['entry_rules']);
        $this->assertSame([
            ['stage' => '予選', 'total_games' => 8, 'enabled' => true],
            ['stage' => '準決勝', 'total_games' => 4, 'enabled' => true],
        ], $settings['stage_settings']);
        $this->assertSame([
            'points|season_trial_championship',
            'qualification|entry_priority',
            'average|official',
            'prize|official',
            'title|season_trial',
        ], array_map(
            static fn (array $output): string => $output['output_type'].'|'.$output['output_scope'],
            $settings['result_outputs'],
        ));
    }
}
