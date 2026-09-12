<?php

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Models\TournamentMatchScoreSheet;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use App\Models\User;
use App\Services\JapanOpenAdvancementService;
use App\Services\JapanOpenDoubleEliminationService;
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

/** @return \Illuminate\Support\Collection<int,ProBowler> */
function insertJapanOpenChampionshipPrelimScores(
    Tournament $tournament,
    int $playerCount,
    string $gender = 'M',
): \Illuminate\Support\Collection {
    return collect(range(1, $playerCount))->map(function (int $position) use ($tournament, $gender): ProBowler {
        $isFemale = $gender === 'F';
        $pro = ProBowler::query()->create([
            'license_no' => sprintf('%s00003%03d', $gender, $position),
            'name_kanji' => sprintf('%s予選選手%02d', $isFemale ? '女子' : '', $position),
            'sex' => $isFemale ? 2 : 1,
        ]);
        $participantId = DB::table('tournament_participants')->insertGetId([
            'tournament_id' => $tournament->id,
            'pro_bowler_license_no' => $pro->license_no,
            'pro_bowler_id' => $pro->id,
            'participant_type' => 'pro',
            'display_name' => $pro->name_kanji,
            'display_license_no' => $pro->license_no,
            'gender' => $gender,
            'source_note' => 'テスト参加者',
            'is_temporary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 8) as $gameNumber) {
            DB::table('game_scores')->insert([
                'tournament_id' => $tournament->id,
                'stage' => '予選',
                'license_number' => $pro->license_no,
                'name' => $pro->name_kanji,
                'game_number' => $gameNumber,
                'score' => $gameNumber === 1 ? 301 - $position : 200,
                'gender' => $gender,
                'pro_bowler_id' => $pro->id,
                'tournament_participant_id' => $participantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $pro;
    });
}

/** @return \Illuminate\Support\Collection<int,ProBowler> */
function createJapanOpenSemifinalSnapshot(Tournament $tournament, int $playerCount = 9): \Illuminate\Support\Collection
{
    $pros = collect(range(1, $playerCount))->map(function (int $position) use ($tournament): ProBowler {
        $pro = ProBowler::query()->create([
            'license_no' => sprintf('M00004%03d', $position),
            'name_kanji' => sprintf('決勝候補%02d', $position),
            'sex' => $tournament->gender === 'F' ? 2 : 1,
        ]);
        $participantId = DB::table('tournament_participants')->insertGetId([
            'tournament_id' => $tournament->id,
            'pro_bowler_license_no' => $pro->license_no,
            'pro_bowler_id' => $pro->id,
            'participant_type' => 'pro',
            'display_name' => $pro->name_kanji,
            'display_license_no' => $pro->license_no,
            'gender' => $tournament->gender,
            'source_note' => '決勝同期テスト',
            'is_temporary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalPin = 3100 - ($position * 10);
        $baseScore = intdiv($totalPin, 14);
        $remainder = $totalPin % 14;
        foreach (range(1, 14) as $gameIndex) {
            DB::table('game_scores')->insert([
                'tournament_id' => $tournament->id,
                'stage' => $gameIndex <= 8 ? '予選' : '準決勝',
                'license_number' => $pro->license_no,
                'name' => $pro->name_kanji,
                'game_number' => $gameIndex <= 8 ? $gameIndex : $gameIndex - 8,
                'score' => $baseScore + ($gameIndex <= $remainder ? 1 : 0),
                'gender' => $tournament->gender,
                'pro_bowler_id' => $pro->id,
                'tournament_participant_id' => $participantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $pro;
    });

    $snapshot = TournamentResultSnapshot::query()->create([
        'tournament_id' => $tournament->id,
        'result_code' => 'semifinal_total',
        'result_name' => '予選＋準決勝14G通算成績',
        'result_type' => 'total_pin',
        'stage_name' => '準決勝',
        'games_count' => 14,
        'carry_game_count' => 8,
        'calculation_definition' => ['source_sets' => []],
        'is_final' => false,
        'is_published' => false,
        'is_current' => true,
        'reflected_at' => now(),
    ]);

    foreach ($pros as $index => $pro) {
        $position = $index + 1;
        TournamentResultSnapshotRow::query()->create([
            'snapshot_id' => $snapshot->id,
            'ranking' => $position,
            'pro_bowler_id' => $pro->id,
            'pro_bowler_license_no' => $pro->license_no,
            'display_name' => $pro->name_kanji,
            'gender' => $tournament->gender,
            'total_pin' => 3100 - ($position * 10),
            'games' => 14,
            'average' => (3100 - ($position * 10)) / 14,
            'is_complete' => true,
        ]);
    }

    return $pros;
}

function completeJapanOpenDoubleEliminationMatch(
    Tournament $tournament,
    string $matchCode,
    string $winnerName,
): void {
    $sheets = TournamentMatchScoreSheet::query()
        ->with('players')
        ->where('tournament_id', $tournament->id)
        ->where('stage_code', JapanOpenDoubleEliminationService::STAGE_CODE)
        ->where('match_code', $matchCode)
        ->get();

    expect($sheets)->not->toBeEmpty();
    foreach ($sheets as $sheet) {
        foreach ($sheet->players as $player) {
            $player->update([
                'final_score' => $player->display_name === $winnerName ? 220 : 190,
                'is_winner' => $player->display_name === $winnerName,
            ]);
        }
        $sheet->update(['confirmed_at' => now()]);
    }

    app(JapanOpenDoubleEliminationService::class)->syncAvailableMatches($tournament->fresh());
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
        ->and(data_get($components['women_all_events']->template_snapshot, 'japan_open.advancement_field_size'))->toBe(100)
        ->and(data_get($components['masters']->template_snapshot, 'japan_open.semifinal_qualifier_count'))->toBe(46)
        ->and(data_get($components['queens']->template_snapshot, 'japan_open.semifinal_qualifier_count'))->toBe(32);

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

test('masters direct seeds sync into participants without duplicates', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2094));
    $seed = ProBowler::query()->create([
        'license_no' => 'M00002101',
        'name_kanji' => '大会シード一郎',
        'sex' => 1,
    ]);
    app(ProBowlerSeedService::class)->addTournamentSeed(
        $components['masters'],
        $seed,
        ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER,
    );

    $this->actingAs($admin)
        ->get(route('tournaments.result_snapshots.index', $components['masters']))
        ->assertOk()
        ->assertSee('大会シードを参加者へ追加')
        ->assertSee('予選8Gから準決勝6Gへ');

    $route = route('tournaments.result_snapshots.japan_open_seeds', $components['masters']);
    $this->actingAs($admin)->post($route)->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post($route)->assertRedirect()->assertSessionHasNoErrors();

    $participants = DB::table('tournament_participants')
        ->where('tournament_id', $components['masters']->id)
        ->where('pro_bowler_id', $seed->id)
        ->get();

    expect($participants)->toHaveCount(1)
        ->and($participants->first()->source_note)->toStartWith(JapanOpenAdvancementService::DIRECT_SEED_SOURCE_NOTE)
        ->and(data_get(
            $components['masters']->fresh()->template_snapshot,
            'japan_open.direct_seed_sync.candidate_count',
        ))->toBe(1);
});

test('masters prelim total automatically syncs the top forty six semifinalists', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2093));
    $pros = insertJapanOpenChampionshipPrelimScores($components['masters'], 48);

    $response = $this->actingAs($admin)->post(
        route('tournaments.result_snapshots.reflect', $components['masters']),
        ['preset_key' => 'prelim_total', 'gender' => '', 'shift' => ''],
    );

    $response->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('ok', fn (string $message): bool => str_contains($message, '準決勝進出者46名も自動同期'));

    $assignments = DB::table('tournament_round_lane_assignments')
        ->where('tournament_id', $components['masters']->id)
        ->where('stage', JapanOpenAdvancementService::SEMIFINAL_STAGE)
        ->where('round_label', JapanOpenAdvancementService::SEMIFINAL_ROUND_LABEL)
        ->orderBy('seed_rank')
        ->get();

    expect($assignments)->toHaveCount(46)
        ->and($assignments->first()->display_name)->toBe('予選選手01')
        ->and($assignments->last()->display_name)->toBe('予選選手46')
        ->and($assignments->every(fn (object $row): bool => (int) $row->source_games === 8))->toBeTrue()
        ->and($assignments->pluck('pro_bowler_id'))->not->toContain($pros[46]->id)
        ->and($assignments->pluck('pro_bowler_id'))->not->toContain($pros[47]->id)
        ->and(data_get(
            $components['masters']->fresh()->template_snapshot,
            'japan_open.semifinal_sync.qualifier_count',
        ))->toBe(46);
});

test('queens prelim total automatically syncs the top thirty two semifinalists', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2091));
    $pros = insertJapanOpenChampionshipPrelimScores($components['queens'], 34, 'F');

    $response = $this->actingAs($admin)->post(
        route('tournaments.result_snapshots.reflect', $components['queens']),
        ['preset_key' => 'prelim_total', 'gender' => '', 'shift' => ''],
    );

    $response->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('ok', fn (string $message): bool => str_contains($message, '準決勝進出者32名も自動同期'));

    $assignments = DB::table('tournament_round_lane_assignments')
        ->where('tournament_id', $components['queens']->id)
        ->where('stage', JapanOpenAdvancementService::SEMIFINAL_STAGE)
        ->where('round_label', JapanOpenAdvancementService::SEMIFINAL_ROUND_LABEL)
        ->orderBy('seed_rank')
        ->get();

    expect($assignments)->toHaveCount(32)
        ->and($assignments->first()->display_name)->toBe('女子予選選手01')
        ->and($assignments->last()->display_name)->toBe('女子予選選手32')
        ->and($assignments->every(fn (object $row): bool => (int) $row->source_games === 8))->toBeTrue()
        ->and($assignments->pluck('pro_bowler_id'))->not->toContain($pros[32]->id)
        ->and($assignments->pluck('pro_bowler_id'))->not->toContain($pros[33]->id)
        ->and(data_get(
            $components['queens']->fresh()->template_snapshot,
            'japan_open.semifinal_sync.qualifier_count',
        ))->toBe(32);
});

test('semifinalist sync stops when the qualification boundary is tied', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2092));
    $settings = (array) $components['queens']->template_snapshot;
    data_set($settings, 'japan_open.semifinal_qualifier_count', 2);
    $components['queens']->template_snapshot = $settings;
    $components['queens']->save();

    $snapshot = TournamentResultSnapshot::query()->create([
        'tournament_id' => $components['queens']->id,
        'result_code' => 'prelim_total',
        'result_name' => '女子予選8G',
        'result_type' => 'total_pin',
        'stage_name' => '予選',
        'games_count' => 8,
        'carry_game_count' => 0,
        'calculation_definition' => ['source_sets' => []],
        'is_final' => false,
        'is_published' => false,
        'is_current' => true,
        'reflected_at' => now(),
    ]);

    foreach ([1800, 1700, 1700] as $index => $totalPin) {
        TournamentResultSnapshotRow::query()->create([
            'snapshot_id' => $snapshot->id,
            'ranking' => $index + 1,
            'display_name' => '女子選手'.($index + 1),
            'gender' => 'F',
            'total_pin' => $totalPin,
            'games' => 8,
            'average' => $totalPin / 8,
            'is_complete' => true,
        ]);
    }

    expect(fn () => app(JapanOpenAdvancementService::class)->syncSemifinalists($components['queens']))
        ->toThrow(InvalidArgumentException::class, '進出境界が同ピン');
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
        ->and(DB::table('tournament_participants')
            ->where('tournament_id', $components['masters']->id)
            ->where('pro_bowler_id', $pros[0]->id)
            ->where('source_note', 'like', JapanOpenAdvancementService::DIRECT_SEED_SOURCE_NOTE.'%')
            ->exists())->toBeTrue()
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

test('semifinal top eight seed the official japan open first round pairings', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2089));
    createJapanOpenSemifinalSnapshot($components['masters']);

    $response = $this->actingAs($admin)->post(
        route('tournaments.result_snapshots.japan_open_finalists', $components['masters']),
    );
    $response->assertRedirect()->assertSessionHasNoErrors();

    $sheets = TournamentMatchScoreSheet::query()
        ->with('players')
        ->where('tournament_id', $components['masters']->id)
        ->where('stage_code', JapanOpenDoubleEliminationService::STAGE_CODE)
        ->orderBy('match_order')
        ->get()
        ->groupBy('match_code');

    expect(data_get(
        $components['masters']->fresh()->template_snapshot,
        'japan_open.double_elimination.seed_sync.finalist_count',
    ))->toBe(8)
        ->and($sheets->keys()->all())->toBe(['W1', 'W2', 'W3', 'W4'])
        ->and($sheets['W1'])->toHaveCount(2)
        ->and($sheets['W1']->first()->players->pluck('display_name')->all())->toBe(['決勝候補01', '決勝候補08'])
        ->and($sheets['W2']->first()->players->pluck('display_name')->all())->toBe(['決勝候補04', '決勝候補05'])
        ->and($sheets['W3']->first()->players->pluck('display_name')->all())->toBe(['決勝候補02', '決勝候補07'])
        ->and($sheets['W4']->first()->players->pluck('display_name')->all())->toBe(['決勝候補03', '決勝候補06']);

    $this->actingAs($admin)
        ->get(route('tournaments.result_snapshots.index', $components['masters']))
        ->assertOk()
        ->assertSee('④ 14G上位8名を決勝へ')
        ->assertSee('⑤・⑥ 対戦進行と再優勝決定戦')
        ->assertSee('決勝候補01');
});

test('japan open double elimination advances winners and ends without an unnecessary reset', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2088));
    createJapanOpenSemifinalSnapshot($components['queens']);
    app(JapanOpenDoubleEliminationService::class)->syncFinalists($components['queens']);

    foreach ([
        'W1' => '決勝候補01', 'W2' => '決勝候補04',
        'W3' => '決勝候補02', 'W4' => '決勝候補03',
        'W5' => '決勝候補01', 'W6' => '決勝候補02',
        'L1' => '決勝候補08', 'L2' => '決勝候補07',
        'L3' => '決勝候補08', 'L4' => '決勝候補07',
        'W7' => '決勝候補01', 'L5' => '決勝候補08',
        'TP' => '決勝候補08', 'GF1' => '決勝候補01',
    ] as $matchCode => $winnerName) {
        completeJapanOpenDoubleEliminationMatch($components['queens'], $matchCode, $winnerName);
    }

    $state = app(JapanOpenDoubleEliminationService::class)->status($components['queens']->fresh());
    expect($state['is_complete'])->toBeTrue()
        ->and($state['champion']['display_name'])->toBe('決勝候補01')
        ->and($state['runner_up']['display_name'])->toBe('決勝候補08')
        ->and($state['third_place']['display_name'])->toBe('決勝候補02')
        ->and(collect($state['final_rankings'])->pluck('player.display_name', 'ranking')->all())->toBe([
            1 => '決勝候補01',
            2 => '決勝候補08',
            3 => '決勝候補02',
            4 => '決勝候補07',
            5 => '決勝候補03',
            6 => '決勝候補04',
            7 => '決勝候補06',
            8 => '決勝候補05',
        ])
        ->and($state['reset_required'])->toBeFalse()
        ->and(TournamentMatchScoreSheet::query()
            ->where('tournament_id', $components['queens']->id)
            ->where('match_code', 'GF2')
            ->exists())->toBeFalse();
});

test('japan open final bracket publishes points prize title public result and pdf end to end', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $components = japanOpenComponents(setupJapanOpenForTest(2085));
    $tournament = $components['masters'];
    createJapanOpenSemifinalSnapshot($tournament);
    app(JapanOpenDoubleEliminationService::class)->syncFinalists($tournament);

    foreach ([
        'W1' => '決勝候補01', 'W2' => '決勝候補04',
        'W3' => '決勝候補02', 'W4' => '決勝候補03',
        'W5' => '決勝候補01', 'W6' => '決勝候補02',
        'L1' => '決勝候補08', 'L2' => '決勝候補07',
        'L3' => '決勝候補08', 'L4' => '決勝候補07',
        'W7' => '決勝候補01', 'L5' => '決勝候補08',
        'TP' => '決勝候補08', 'GF1' => '決勝候補01',
    ] as $matchCode => $winnerName) {
        completeJapanOpenDoubleEliminationMatch($tournament, $matchCode, $winnerName);
    }

    foreach (range(1, 9) as $rank) {
        DB::table('point_distributions')->insert([
            'tournament_id' => $tournament->id,
            'rank' => $rank,
            'points' => 1100 - ($rank * 100),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prize_distributions')->insert([
            'tournament_id' => $tournament->id,
            'rank' => $rank,
            'amount' => 110000 - ($rank * 10000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $reflect = $this->actingAs($admin)->post(
        route('tournaments.result_snapshots.japan_open_double_elimination_finalize', $tournament),
    );
    $snapshot = TournamentResultSnapshot::query()
        ->where('tournament_id', $tournament->id)
        ->where('is_final', true)
        ->where('is_current', true)
        ->firstOrFail();
    $reflect->assertRedirect(route('tournaments.result_publications.index', [
        'tournament' => $tournament->id,
        'snapshot_id' => $snapshot->id,
    ]))->assertSessionHasNoErrors();

    expect($snapshot->result_type)->toBe(JapanOpenDoubleEliminationService::SHEET_TYPE)
        ->and($snapshot->rows)->toHaveCount(8)
        ->and($snapshot->rows->sortBy('ranking')->pluck('display_name')->values()->all())->toBe([
            '決勝候補01', '決勝候補08', '決勝候補02', '決勝候補07',
            '決勝候補03', '決勝候補04', '決勝候補06', '決勝候補05',
        ])
        ->and($snapshot->rows->firstWhere('ranking', 1)->games)->toBe(21)
        ->and($snapshot->rows->firstWhere('ranking', 1)->total_pin)->toBe(4630);

    $publicationService = app(TournamentResultPublicationService::class);
    $preview = $publicationService->preview($tournament->fresh(), $snapshot->fresh());
    expect($preview['can_publish'])->toBeTrue()
        ->and($preview['summary']['row_count'])->toBe(9)
        ->and($preview['rows'][0]['points'])->toBe(1000)
        ->and($preview['rows'][0]['prize_money'])->toBe(100000)
        ->and($preview['rows'][0]['games'])->toBe(21)
        ->and($preview['rows'][0]['total_pin'])->toBe(4630);

    $publish = $this->actingAs($admin)->post(
        route('tournaments.result_publications.publish', $tournament),
        [
            'snapshot_id' => $snapshot->id,
            'expected_checksum' => $preview['result_checksum'],
            'confirm_publish' => '1',
        ],
    );
    $publish->assertRedirect()->assertSessionHasNoErrors();

    $tournament->update(['setup_status' => 'final']);
    expect(DB::table('tournament_result_publications')->where('tournament_id', $tournament->id)->count())->toBe(1)
        ->and(DB::table('tournament_results')->where('tournament_id', $tournament->id)->count())->toBe(9)
        ->and((int) DB::table('tournament_results')->where('tournament_id', $tournament->id)->where('ranking', 1)->value('points'))->toBe(1000)
        ->and((int) DB::table('tournament_results')->where('tournament_id', $tournament->id)->where('ranking', 1)->value('prize_money'))->toBe(100000)
        ->and(DB::table('pro_bowler_titles')->where('tournament_id', $tournament->id)->count())->toBe(1);

    $this->get(route('public.tournaments.results', $tournament))
        ->assertOk()
        ->assertSee('決勝候補01')
        ->assertSee('1,000');
    $this->actingAs($admin)->get(route('tournaments.results.pdf', $tournament))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('japan open creates and resolves a reset final only when the unbeaten player first loses', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2087));
    createJapanOpenSemifinalSnapshot($components['masters']);
    app(JapanOpenDoubleEliminationService::class)->syncFinalists($components['masters']);

    foreach ([
        'W1' => '決勝候補01', 'W2' => '決勝候補04',
        'W3' => '決勝候補02', 'W4' => '決勝候補03',
        'W5' => '決勝候補01', 'W6' => '決勝候補02',
        'L1' => '決勝候補08', 'L2' => '決勝候補07',
        'L3' => '決勝候補08', 'L4' => '決勝候補07',
        'W7' => '決勝候補01', 'L5' => '決勝候補08',
        'TP' => '決勝候補08', 'GF1' => '決勝候補08',
    ] as $matchCode => $winnerName) {
        completeJapanOpenDoubleEliminationMatch($components['masters'], $matchCode, $winnerName);
    }

    $beforeReset = app(JapanOpenDoubleEliminationService::class)->status($components['masters']->fresh());
    expect($beforeReset['reset_required'])->toBeTrue()
        ->and($beforeReset['is_complete'])->toBeFalse()
        ->and($beforeReset['matches']['GF2']['status'])->toBe('ready')
        ->and($beforeReset['matches']['GF2']['sheets'])->toHaveCount(1);

    completeJapanOpenDoubleEliminationMatch($components['masters'], 'GF2', '決勝候補01');
    $afterReset = app(JapanOpenDoubleEliminationService::class)->status($components['masters']->fresh());
    expect($afterReset['is_complete'])->toBeTrue()
        ->and($afterReset['champion']['display_name'])->toBe('決勝候補01')
        ->and($afterReset['runner_up']['display_name'])->toBe('決勝候補08');
});

test('two game aggregate tie waits for an explicit tiebreak winner', function () {
    $components = japanOpenComponents(setupJapanOpenForTest(2086));
    createJapanOpenSemifinalSnapshot($components['masters']);
    $service = app(JapanOpenDoubleEliminationService::class);
    $service->syncFinalists($components['masters']);

    $sheets = TournamentMatchScoreSheet::query()
        ->with('players')
        ->where('tournament_id', $components['masters']->id)
        ->where('match_code', 'W1')
        ->orderBy('game_number')
        ->get();
    foreach ($sheets as $index => $sheet) {
        foreach ($sheet->players as $playerIndex => $player) {
            $player->update([
                'final_score' => ($index + $playerIndex) % 2 === 0 ? 220 : 200,
                'is_winner' => ($index + $playerIndex) % 2 === 0,
            ]);
        }
        $sheet->update(['confirmed_at' => now()]);
    }

    $tied = $service->syncAvailableMatches($components['masters']->fresh());
    expect($tied['matches']['W1']['status'])->toBe('tied')
        ->and($tied['matches']['W5']['status'])->toBe('waiting');

    $winnerIdentity = $tied['matches']['W1']['participants'][0]['identity'];
    $resolved = $service->setTieWinner($components['masters']->fresh(), 'W1', $winnerIdentity);
    expect($resolved['matches']['W1']['status'])->toBe('complete')
        ->and($resolved['matches']['W1']['winner']['identity'])->toBe($winnerIdentity);
});
