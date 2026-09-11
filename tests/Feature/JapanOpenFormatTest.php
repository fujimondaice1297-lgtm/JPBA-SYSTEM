<?php

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Models\User;
use App\Services\JapanOpenFormatService;
use App\Services\JapanOpenRosterImportService;
use App\Services\TournamentAggregateResultService;
use App\Services\TournamentResultPublicationService;
use Illuminate\Support\Facades\DB;

function setupJapanOpenForTest(int $year = 2098): array
{
    return app(JapanOpenFormatService::class)->setup([
        'year' => $year,
        'edition_no' => 99,
        'name' => 'テスト用ジャパンオープン',
        'start_date' => $year.'-10-01',
        'end_date' => $year.'-10-04',
        'venue_name' => 'テスト会場',
        'ball_registration_limit' => 12,
    ], true);
}

function japanOpenComponents(array $report): array
{
    return Tournament::query()
        ->whereIn('id', array_values($report['component_ids']))
        ->get()
        ->mapWithKeys(fn (Tournament $tournament): array => [
            data_get($tournament->template_snapshot, 'japan_open.component_code') => $tournament,
        ])
        ->all();
}

function insertJapanOpenScores(Tournament $tournament, string $stage, int $score = 200): void
{
    foreach (DB::table('tournament_participants')->where('tournament_id', $tournament->id)->get() as $participant) {
        foreach (range(1, 3) as $gameNumber) {
            DB::table('game_scores')->insert([
                'tournament_id' => $tournament->id,
                'stage' => $stage,
                'license_number' => $participant->display_license_no ?: $participant->pro_bowler_license_no,
                'name' => $participant->display_name,
                'game_number' => $gameNumber,
                'score' => $score,
                'gender' => $participant->gender,
                'pro_bowler_id' => $participant->pro_bowler_id,
                'tournament_participant_id' => $participant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

test('admin creates an idempotent eleven component japan open edition without player data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

    $response = $this->actingAs($admin)->post(route('tournament_templates.japan_open.store'), [
        'year' => 2098,
        'edition_no' => 99,
        'name' => 'テスト用ジャパンオープン',
        'start_date' => '2098-10-01',
        'end_date' => '2098-10-04',
        'venue_name' => 'テスト会場',
        'ball_registration_limit' => 12,
    ]);

    $response->assertRedirect();
    expect(DB::table('tournaments')->where('year', 2098)->count())->toBe(11)
        ->and(DB::table('tournament_aggregate_definitions')->count())->toBe(6)
        ->and(DB::table('tournament_aggregate_sources')->count())->toBe(10)
        ->and(DB::table('tournament_participants')->count())->toBe(0)
        ->and(DB::table('game_scores')->count())->toBe(0)
        ->and(DB::table('tournament_results')->count())->toBe(0);

    $report = setupJapanOpenForTest();
    expect(DB::table('tournaments')->where('year', 2098)->count())->toBe(11)
        ->and(DB::table('tournament_aggregate_definitions')->count())->toBe(6)
        ->and(DB::table('tournament_aggregate_sources')->count())->toBe(10)
        ->and($report['created_tournament_ids'])->toBe([]);

    $components = japanOpenComponents($report);
    expect($components['men_team']->counts_for_official_points)->toBeFalse()
        ->and($components['men_team']->counts_for_average)->toBeTrue()
        ->and($components['men_all_events']->counts_for_official_points)->toBeFalse()
        ->and($components['men_all_events']->counts_for_average)->toBeFalse()
        ->and($components['masters']->counts_for_official_points)->toBeTrue()
        ->and($components['queens']->counts_for_official_points)->toBeTrue();

    Tournament::query()->whereIn('id', array_values($report['component_ids']))
        ->update(['setup_status' => 'in_progress']);
    $this->get(route('public.tournaments.index'))
        ->assertOk()
        ->assertSee('大会総合案内')
        ->assertDontSee('男子チーム戦');
    $this->get(route('public.tournaments.show', $components['overview']))
        ->assertOk()
        ->assertSee('男子チーム戦')
        ->assertSee('女子クイーンズ');
});

test('team roster paste creates team doubles and singles participants with professional limits', function () {
    $components = japanOpenComponents(setupJapanOpenForTest());
    ProBowler::query()->create(['license_no' => 'M00001001', 'name_kanji' => 'プロ一郎', 'sex' => 1]);
    ProBowler::query()->create(['license_no' => 'M00001002', 'name_kanji' => 'プロ二郎', 'sex' => 1]);

    $text = implode("\n", [
        "チームコード\tチーム名\t順番\tライセンスNo.\t選手名",
        "A01\tテストチーム\t1\t1001\tプロ一郎",
        "A01\tテストチーム\t2\t\tアマ一郎",
        "A01\tテストチーム\t3\t1002\tプロ二郎",
        "A01\tテストチーム\t4\t\tアマ二郎",
    ]);
    $result = app(JapanOpenRosterImportService::class)->import($components['men_team'], $text);

    expect($result['team_count'])->toBe(1)
        ->and($result['doubles_count'])->toBe(2)
        ->and($result['member_count'])->toBe(4)
        ->and(DB::table('tournament_competitor_groups')->where('tournament_id', $components['men_team']->id)->count())->toBe(1)
        ->and(DB::table('tournament_competitor_groups')->where('tournament_id', $components['men_doubles']->id)->count())->toBe(2)
        ->and(DB::table('tournament_participants')->where('tournament_id', $components['men_team']->id)->count())->toBe(4)
        ->and(DB::table('tournament_participants')->where('tournament_id', $components['men_doubles']->id)->count())->toBe(4)
        ->and(DB::table('tournament_participants')->where('tournament_id', $components['men_singles']->id)->count())->toBe(4);

    $invalid = implode("\n", [
        "A01\tテストチーム\t1\t1001\tプロ一郎",
        "A01\tテストチーム\t2\t1002\tプロ二郎",
        "A01\tテストチーム\t3\t\tアマ一郎",
        "A01\tテストチーム\t4\t\tアマ二郎",
    ]);
    expect(fn () => app(JapanOpenRosterImportService::class)->import($components['men_team'], $invalid))
        ->toThrow(InvalidArgumentException::class, 'ダブルス組');
});

test('team and all events results calculate publish and remain outside individual official accounting', function () {
    $components = japanOpenComponents(setupJapanOpenForTest());
    ProBowler::query()->create(['license_no' => 'M00001001', 'name_kanji' => 'プロ一郎', 'sex' => 1]);
    ProBowler::query()->create(['license_no' => 'M00001002', 'name_kanji' => 'プロ二郎', 'sex' => 1]);

    app(JapanOpenRosterImportService::class)->import($components['men_team'], implode("\n", [
        "A01\tテストチーム\t1\t1001\tプロ一郎",
        "A01\tテストチーム\t2\t\tアマ一郎",
        "A01\tテストチーム\t3\t1002\tプロ二郎",
        "A01\tテストチーム\t4\t\tアマ二郎",
    ]));

    insertJapanOpenScores($components['men_team'], 'チーム戦');
    insertJapanOpenScores($components['men_doubles'], 'ダブルス戦');
    insertJapanOpenScores($components['men_singles'], 'シングルス戦');

    $teamDefinition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['men_team']->id)
        ->where('code', 'team_total')
        ->firstOrFail();
    $doublesDefinition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['men_doubles']->id)
        ->where('code', 'doubles_total')
        ->firstOrFail();
    $allEventsDefinition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['men_all_events']->id)
        ->where('code', 'all_events_9g')
        ->firstOrFail();
    $calculator = app(TournamentAggregateResultService::class);
    $teamSnapshot = $calculator->calculate($teamDefinition);
    $doublesSnapshot = $calculator->calculate($doublesDefinition);
    $allEventsSnapshot = $calculator->calculate($allEventsDefinition);

    expect($teamSnapshot->rows)->toHaveCount(1)
        ->and($teamSnapshot->rows->first()->games)->toBe(12)
        ->and($teamSnapshot->rows->first()->total_pin)->toBe(2400)
        ->and($doublesSnapshot->rows)->toHaveCount(2)
        ->and($doublesSnapshot->rows->every(fn ($row): bool => $row->games === 6))->toBeTrue()
        ->and($doublesSnapshot->rows->every(fn ($row): bool => $row->total_pin === 1200))->toBeTrue()
        ->and($allEventsSnapshot->rows)->toHaveCount(4)
        ->and($allEventsSnapshot->rows->first()->games)->toBe(9)
        ->and($allEventsSnapshot->rows->first()->total_pin)->toBe(1800);

    $publicationPreview = app(TournamentResultPublicationService::class)
        ->preview($components['men_team'], $teamSnapshot);
    expect(implode(' ', $publicationPreview['errors']))->toContain('直接反映できません');

    $components['men_team']->update(['setup_status' => 'in_progress']);
    $this->get(route('public.tournaments.aggregate', [$components['men_team'], $teamDefinition]))
        ->assertOk()
        ->assertSee('テストチーム')
        ->assertSee('2,400');
    $this->get(route('public.tournaments.aggregate', [$components['men_team'], $teamDefinition, 'mode' => 'official']))
        ->assertOk()
        ->assertSee('正式成績');
    $this->get(route('public.tournaments.aggregate.pdf', [$components['men_team'], $teamDefinition, 'mode' => 'official']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
