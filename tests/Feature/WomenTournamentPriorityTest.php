<?php

use App\Models\ProBowler;
use App\Services\WomenTournamentPriorityService;

test('official women tournament priority data is continuous and unique for both periods', function (string $period, int $rowCount, int $entryOnlyCount) {
    $path = database_path("data/jpba_official_2026_women_priority_{$period}.json");
    $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['year'])->toBe(2026)
        ->and($payload['period'])->toBe($period)
        ->and($payload['row_count'])->toBe($rowCount)
        ->and($payload['entry_only_count'])->toBe($entryOnlyCount)
        ->and(array_column($payload['rows'], 'priority_rank'))->toBe(range(1, $rowCount))
        ->and(array_unique(array_column($payload['rows'], 'license_no')))->toHaveCount($rowCount);
})->with([
    'upper' => ['upper', 243, 5],
    'lower' => ['lower', 239, 13],
]);

test('public women tournament priority joins player profiles and switches period', function () {
    $seed = ProBowler::query()->create([
        'license_no' => 'F00000599',
        'name_kanji' => '石田万音',
        'sex' => 2,
        'kibetsu' => 55,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'valid',
    ]);

    ProBowler::query()->create([
        'license_no' => 'F00000449',
        'name_kanji' => '宮城鈴菜',
        'sex' => 2,
        'kibetsu' => 42,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'valid',
    ]);

    $service = app(WomenTournamentPriorityService::class);
    $lower = $service->priority(2026, 'lower');

    expect($lower['row_count'])->toBe(239)
        ->and($lower['scored_qualifier_count'])->toBe(172)
        ->and($lower['entry_only_count'])->toBe(13)
        ->and($lower['rows'][0]['pro_bowler_id'])->toBe($seed->id)
        ->and($lower['rows'][0]['display_name'])->toBe('石田万音')
        ->and(collect($lower['rows'])->firstWhere('priority_rank', 55)['display_name'])->toBe('宮城鈴菜');

    $this->get(route('rankings.women_tournament_priority', [
        'year' => 2026,
        'period' => 'lower',
    ]))
        ->assertOk()
        ->assertSee('2026年度下半期女子トーナメント出場優先順位')
        ->assertSee('239名')
        ->assertSee('172名')
        ->assertSee('石田万音')
        ->assertSee('宮城鈴菜')
        ->assertSee(route('public.players.show', $seed->id), false);

    $this->get(route('rankings.women_tournament_priority', [
        'year' => 2026,
        'period' => 'upper',
    ]))
        ->assertOk()
        ->assertSee('2026年度上半期女子トーナメント出場優先順位')
        ->assertSee('243名')
        ->assertSee('トーナメントサード')
        ->assertSee('191名');
});

test('public results page links to women tournament priority', function () {
    $this->get(route('public.tournaments.live_results'))
        ->assertOk()
        ->assertSee(route('rankings.women_tournament_priority'), false)
        ->assertSee('女子トーナメント出場優先順位');
});
