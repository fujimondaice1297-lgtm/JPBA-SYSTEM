<?php

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Models\User;
use App\Services\JapanOpenAdvancementService;
use App\Services\JapanOpenFormatService;
use App\Services\JapanOpenRosterImportService;
use App\Services\ProBowlerSeedService;
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

/** @param array<string,int> $scoresByName */
function insertJapanOpenScoresByName(Tournament $tournament, string $stage, array $scoresByName): void
{
    foreach (DB::table('tournament_participants')->where('tournament_id', $tournament->id)->get() as $participant) {
        $score = $scoresByName[$participant->display_name] ?? 200;
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
        ->and($components['queens']->counts_for_official_points)->toBeTrue()
        ->and(data_get($components['men_all_events']->template_snapshot, 'japan_open.advancement_field_size'))->toBe(125)
        ->and(data_get($components['women_all_events']->template_snapshot, 'japan_open.advancement_field_size'))->toBe(100);

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

test('all events qualifiers sync to masters by shift while preserving direct seeds', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2097));
    $pros = collect([
        ['license_no' => 'M00001001', 'name_kanji' => 'シード一郎', 'sex' => 1],
        ['license_no' => 'M00001002', 'name_kanji' => 'Ａプロ', 'sex' => 1],
        ['license_no' => 'M00001003', 'name_kanji' => 'Ｂプロ一', 'sex' => 1],
        ['license_no' => 'M00001004', 'name_kanji' => 'Ｂプロ二', 'sex' => 1],
    ])->map(fn (array $row) => ProBowler::query()->create($row));

    app(JapanOpenRosterImportService::class)->import($components['men_team'], implode("\n", [
        "チームコード\tチーム名\t順番\tライセンスNo.\t選手名\tシフト",
        "A01\tAチーム\t1\t1001\tシード一郎\tA",
        "A01\tAチーム\t2\t\tＡアマ一\tA",
        "A01\tAチーム\t3\t1002\tＡプロ\tA",
        "A01\tAチーム\t4\t\tＡアマ二\tA",
        "B01\tBチーム\t1\t1003\tＢプロ一\tB",
        "B01\tBチーム\t2\t\tＢアマ一\tB",
        "B01\tBチーム\t3\t1004\tＢプロ二\tB",
        "B01\tBチーム\t4\t\tＢアマ二\tB",
    ]));

    $scores = [
        'シード一郎' => 250,
        'Ａアマ一' => 240,
        'Ａプロ' => 230,
        'Ａアマ二' => 220,
        'Ｂプロ一' => 245,
        'Ｂアマ一' => 235,
        'Ｂプロ二' => 225,
        'Ｂアマ二' => 215,
    ];
    insertJapanOpenScoresByName($components['men_team'], 'チーム戦', $scores);
    insertJapanOpenScoresByName($components['men_doubles'], 'ダブルス戦', $scores);
    insertJapanOpenScoresByName($components['men_singles'], 'シングルス戦', $scores);

    $definition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['men_all_events']->id)
        ->where('code', 'all_events_9g')
        ->firstOrFail();
    $snapshot = app(TournamentAggregateResultService::class)->calculate($definition, $admin->id);
    app(ProBowlerSeedService::class)->addTournamentSeed(
        $components['masters'],
        $pros[0],
        ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER,
    );

    expect($snapshot->rows->pluck('shift')->unique()->sort()->values()->all())->toBe(['A', 'B'])
        ->and(DB::table('amateur_bowlers')->where('note', 'ジャパンオープン編成一括取込')->count())->toBe(4);

    $response = $this->actingAs($admin)->post(
        route('tournaments.aggregate_results.japan_open_advancement', $components['men_all_events']),
        ['field_size' => 5, 'shift_a_count' => 2, 'shift_b_count' => 2],
    );
    $response->assertRedirect()->assertSessionHasNoErrors();

    $qualifiers = DB::table('tournament_participants')
        ->where('tournament_id', $components['masters']->id)
        ->where('source_note', 'like', JapanOpenAdvancementService::QUALIFIER_SOURCE_NOTE.'%')
        ->orderBy('sort_order')
        ->get();
    expect($qualifiers)->toHaveCount(4)
        ->and($qualifiers->pluck('display_name')->all())->toBe(['Ｂプロ一', 'Ａアマ一', 'Ｂアマ一', 'Ａプロ'])
        ->and($qualifiers->pluck('shift')->sort()->values()->all())->toBe(['A', 'A', 'B', 'B'])
        ->and($qualifiers->pluck('display_name'))->not->toContain('シード一郎')
        ->and(data_get(
            $components['men_all_events']->fresh()->template_snapshot,
            'japan_open.advancement_sync.source_snapshot_id',
        ))->toBe($snapshot->id);
});

test('women all events qualifiers sync overall and keep manual entrants', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2095));
    $pros = collect([
        ['license_no' => 'F00002001', 'name_kanji' => '女子シード', 'sex' => 2],
        ['license_no' => 'F00002002', 'name_kanji' => '女子プロ', 'sex' => 2],
        ['license_no' => 'F00002003', 'name_kanji' => '手動出場者', 'sex' => 2],
    ])->map(fn (array $row) => ProBowler::query()->create($row));

    app(JapanOpenRosterImportService::class)->import($components['women_team'], implode("\n", [
        "W01\t女子チーム\t1\t2001\t女子シード",
        "W01\t女子チーム\t2\t\t女子アマ一",
        "W01\t女子チーム\t3\t2002\t女子プロ",
        "W01\t女子チーム\t4\t\t女子アマ二",
    ]));
    $scores = [
        '女子シード' => 250,
        '女子アマ一' => 240,
        '女子プロ' => 230,
        '女子アマ二' => 220,
    ];
    insertJapanOpenScoresByName($components['women_team'], 'チーム戦', $scores);
    insertJapanOpenScoresByName($components['women_doubles'], 'ダブルス戦', $scores);
    insertJapanOpenScoresByName($components['women_singles'], 'シングルス戦', $scores);
    $definition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['women_all_events']->id)
        ->where('code', 'all_events_9g')
        ->firstOrFail();
    app(TournamentAggregateResultService::class)->calculate($definition);
    app(ProBowlerSeedService::class)->addTournamentSeed(
        $components['queens'],
        $pros[0],
        ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER,
    );
    DB::table('tournament_participants')->insert([
        'tournament_id' => $components['queens']->id,
        'pro_bowler_license_no' => $pros[2]->license_no,
        'pro_bowler_id' => $pros[2]->id,
        'participant_type' => 'pro',
        'display_name' => $pros[2]->name_kanji,
        'display_license_no' => $pros[2]->license_no,
        'gender' => 'F',
        'source_note' => '主催者手動登録',
        'is_temporary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(JapanOpenAdvancementService::class)->sync($components['women_all_events'], 4);

    expect($result['reserved_entry_count'])->toBe(2)
        ->and($result['qualifier_count'])->toBe(2)
        ->and(DB::table('tournament_participants')
            ->where('tournament_id', $components['queens']->id)
            ->where('source_note', '主催者手動登録')
            ->exists())->toBeTrue()
        ->and(DB::table('tournament_participants')
            ->where('tournament_id', $components['queens']->id)
            ->where('source_note', 'like', JapanOpenAdvancementService::QUALIFIER_SOURCE_NOTE.'%')
            ->orderBy('sort_order')
            ->pluck('display_name')
            ->all())->toBe(['女子アマ一', '女子プロ']);
});

test('all events advancement stops when a tie crosses the qualifier boundary', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2096));
    ProBowler::query()->create(['license_no' => 'M00001011', 'name_kanji' => '同点一郎', 'sex' => 1]);
    ProBowler::query()->create(['license_no' => 'M00001012', 'name_kanji' => '同点二郎', 'sex' => 1]);

    app(JapanOpenRosterImportService::class)->import($components['men_team'], implode("\n", [
        "A01\tAチーム\t1\t1011\t同点一郎\tA",
        "A01\tAチーム\t2\t\tＡアマ\tA",
        "A01\tAチーム\t3\t1012\t同点二郎\tA",
        "A01\tAチーム\t4\t\tＡアマ二\tA",
        "B01\tBチーム\t1\t\tＢアマ一\tB",
        "B01\tBチーム\t2\t\tＢアマ二\tB",
        "B01\tBチーム\t3\t\tＢアマ三\tB",
        "B01\tBチーム\t4\t\tＢアマ四\tB",
    ]));
    $scores = [
        '同点一郎' => 240,
        '同点二郎' => 230,
        'Ａアマ' => 240,
        'Ａアマ二' => 210,
        'Ｂアマ一' => 240,
        'Ｂアマ二' => 230,
        'Ｂアマ三' => 220,
        'Ｂアマ四' => 210,
    ];
    insertJapanOpenScoresByName($components['men_team'], 'チーム戦', $scores);
    insertJapanOpenScoresByName($components['men_doubles'], 'ダブルス戦', $scores);
    insertJapanOpenScoresByName($components['men_singles'], 'シングルス戦', $scores);
    $definition = TournamentAggregateDefinition::query()
        ->where('tournament_id', $components['men_all_events']->id)
        ->where('code', 'all_events_9g')
        ->firstOrFail();
    app(TournamentAggregateResultService::class)->calculate($definition);

    expect(fn () => app(JapanOpenAdvancementService::class)->sync(
        $components['men_all_events'],
        2,
        1,
        1,
    ))->toThrow(InvalidArgumentException::class, '進出境界が同ピン');
});
