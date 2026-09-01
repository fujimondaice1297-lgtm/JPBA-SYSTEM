<?php

use App\Services\OfficialPointDistributionService;

test('official point distribution preserves every published rank and boundary value', function () {
    $distribution = app(OfficialPointDistributionService::class)->distribution();

    expect($distribution['men'])->toHaveCount(96)
        ->and($distribution['men'][0])->toBe(['rank' => 1, 'points' => 1000])
        ->and($distribution['men'][95])->toBe(['rank' => 96, 'points' => 1])
        ->and($distribution['women'])->toHaveCount(72)
        ->and($distribution['women'][0])->toBe(['rank' => 1, 'points' => 800])
        ->and($distribution['women'][71])->toBe(['rank' => 72, 'points' => 1])
        ->and($distribution['season_trial'])->toHaveCount(8)
        ->and($distribution['season_trial'][0])->toBe(['rank' => 1, 'points' => 50])
        ->and($distribution['season_trial'][7])->toBe(['rank' => 8, 'points' => 18]);
});

test('official point distribution is public and linked from the results hub', function () {
    $this->get(route('rankings.point_distribution'))
        ->assertOk()
        ->assertSee('JPBAポイント配分表')
        ->assertSee('男子（96名）')
        ->assertSee('女子（72名）')
        ->assertSee('シーズントライアル（8名）')
        ->assertSee('96位')
        ->assertSee('72位');

    $this->get(route('public.tournaments.live_results'))
        ->assertOk()
        ->assertSee(route('rankings.point_distribution'), false)
        ->assertSee('JPBAポイント配分表');
});
