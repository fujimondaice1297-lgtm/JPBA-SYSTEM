<?php

use App\Models\Tournament;
use App\Services\TournamentClassificationPolicyService;

test('women priority determination tournament cannot produce points prize or titles', function () {
    $service = app(TournamentClassificationPolicyService::class);
    $attributes = $service->normalizeTournamentAttributes([
        'name' => '2026年度 下半期女子トーナメント出場優先順位決定戦',
        'gender' => 'F',
        'counts_for_official_points' => true,
        'counts_for_prize' => true,
        'title_scope' => 'official',
        'title_category' => 'normal',
    ]);
    $outputs = $service->normalizeResultOutputInput(new Tournament($attributes), [
        'counts_for_official_points' => true,
        'counts_for_season_trial_points' => true,
        'counts_for_prize' => true,
        'title_scope' => 'official',
        'produces_entry_priority' => false,
    ]);

    expect($attributes['counts_for_official_points'])->toBeFalse()
        ->and($attributes['counts_for_prize'])->toBeFalse()
        ->and($attributes['title_scope'])->toBe('none')
        ->and($attributes['title_category'])->toBe('excluded')
        ->and($outputs['counts_for_official_points'])->toBeFalse()
        ->and($outputs['counts_for_season_trial_points'])->toBeFalse()
        ->and($outputs['counts_for_prize'])->toBeFalse()
        ->and($outputs['title_scope'])->toBe('none')
        ->and($outputs['produces_entry_priority'])->toBeTrue();
});
