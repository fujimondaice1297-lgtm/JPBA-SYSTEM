<?php

use App\Services\JapanOpenFormatService;

test('japan open blueprint keeps the reusable official competition structure', function () {
    $blueprint = (new JapanOpenFormatService)->blueprint();
    $components = $blueprint['components'];

    expect($components)->toHaveCount(11)
        ->and($blueprint['competition_rules']['team_member_count'])->toBe(4)
        ->and($blueprint['competition_rules']['team_max_professionals'])->toBe(2)
        ->and($blueprint['competition_rules']['doubles_member_count'])->toBe(2)
        ->and($blueprint['competition_rules']['doubles_max_professionals'])->toBe(1)
        ->and($blueprint['competition_rules']['all_events_games_per_player'])->toBe(9)
        ->and($blueprint['competition_rules']['all_events_sources'])->toBe(['team', 'doubles', 'singles'])
        ->and($blueprint['competition_rules']['masters_queens_semifinal_total_games'])->toBe(14)
        ->and($blueprint['competition_rules']['final_format'])->toBe('double_elimination')
        ->and($blueprint['accounting_policy']['official_points_components'])->toBe(['masters', 'queens'])
        ->and($blueprint['accounting_policy']['category_aggregates_are_official_individual_results'])->toBeFalse();

    foreach (['men_team', 'men_doubles', 'men_singles', 'men_all_events', 'women_team', 'women_doubles', 'women_singles', 'women_all_events'] as $code) {
        expect($components[$code]['counts_for_points'])->toBeFalse()
            ->and($components[$code]['counts_for_prize'])->toBeFalse()
            ->and($components[$code]['counts_for_title'])->toBeFalse();
    }

    foreach (['men_team', 'men_doubles', 'men_singles', 'women_team', 'women_doubles', 'women_singles'] as $code) {
        expect($components[$code]['counts_for_average'])->toBeTrue();
    }

    foreach (['men_all_events', 'women_all_events'] as $code) {
        expect($components[$code]['counts_for_average'])->toBeFalse();
    }

    foreach (['masters', 'queens'] as $code) {
        expect($components[$code]['counts_for_points'])->toBeTrue()
            ->and($components[$code]['counts_for_average'])->toBeTrue()
            ->and($components[$code]['counts_for_prize'])->toBeTrue()
            ->and($components[$code]['counts_for_title'])->toBeTrue();
    }
});
