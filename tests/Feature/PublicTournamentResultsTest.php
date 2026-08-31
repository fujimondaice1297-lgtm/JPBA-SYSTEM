<?php

use App\Models\FlashNews;
use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('published tournament scores and final results are reachable from the public hub', function () {
    $tournament = Tournament::query()->create([
        'name' => '一般公開 成績テスト大会',
        'setup_status' => 'final',
        'start_date' => '2026-08-30',
        'end_date' => '2026-08-30',
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
    ]);
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009971',
        'name_kanji' => '公開速報 確認選手',
        'sex' => 1,
        'is_active' => true,
        'member_class' => 'player',
    ]);
    $entry = TournamentEntry::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_id' => $bowler->id,
        'status' => 'entry',
    ]);

    foreach ([1 => 210, 2 => 220] as $gameNumber => $score) {
        GameScore::query()->create([
            'tournament_id' => $tournament->id,
            'stage' => '予選',
            'gender' => 'M',
            'license_number' => $bowler->license_no,
            'name' => $bowler->name_kanji,
            'entry_number' => '1',
            'game_number' => $gameNumber,
            'score' => $score,
            'pro_bowler_id' => $bowler->id,
        ]);
    }

    TournamentResult::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_id' => $bowler->id,
        'pro_bowler_license_no' => $bowler->license_no,
        'ranking' => 1,
        'games' => 2,
        'total_pin' => 430,
        'average' => 215,
        'points' => 500,
        'prize_money' => 100000,
        'ranking_year' => 2026,
    ]);

    $this->get(route('public.tournaments.live_results'))
        ->assertOk()
        ->assertSee('速報・成績')
        ->assertSee('一般公開 成績テスト大会')
        ->assertSee(route('public.tournaments.live', $tournament), false)
        ->assertSee(route('public.tournaments.results', $tournament), false);

    $this->get(route('public.tournaments.live', $tournament))
        ->assertOk()
        ->assertSee('これは大会進行中の速報値です')
        ->assertSee('公開速報 確認選手')
        ->assertSee('430')
        ->assertSee('大会登録ボール 0個')
        ->assertSee(route('scores.entry_balls.show', $entry), false);

    $this->get(route('public.tournaments.results', $tournament))
        ->assertOk()
        ->assertSee('最終成績')
        ->assertSee('公開速報 確認選手')
        ->assertSee('215.00')
        ->assertSee('100,000');

    $this->get(route('public.tournaments.show', $tournament))
        ->assertOk()
        ->assertSee('速報・途中経過を見る（2スコア）')
        ->assertSee('全成績を見る（1名）');
});

test('draft score data stays hidden from public live and result pages', function () {
    $tournament = Tournament::query()->create([
        'name' => '非公開 入力途中大会',
        'setup_status' => 'draft',
        'start_date' => '2026-08-30',
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
    ]);

    GameScore::query()->create([
        'tournament_id' => $tournament->id,
        'stage' => '予選',
        'gender' => 'M',
        'license_number' => 'M00009972',
        'name' => '非公開 確認選手',
        'game_number' => 1,
        'score' => 200,
    ]);
    TournamentResult::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_license_no' => 'M00009972',
        'ranking' => 1,
        'games' => 1,
        'total_pin' => 200,
        'average' => 200,
        'ranking_year' => 2026,
    ]);

    $this->get(route('public.tournaments.live_results'))
        ->assertOk()
        ->assertDontSee('非公開 入力途中大会');
    $this->get(route('public.tournaments.live', $tournament))->assertNotFound();
    $this->get(route('public.tournaments.results', $tournament))->assertNotFound();
});

test('external flash news links are publicly reachable without authentication', function () {
    $item = FlashNews::query()->create([
        'title' => '特設速報テスト',
        'url' => 'https://example.com/live-score',
    ]);

    $this->get(route('public.tournaments.live_results'))
        ->assertOk()
        ->assertSee('特設速報テスト')
        ->assertSee(route('flash_news.public', $item), false);

    $this->get(route('flash_news.public', $item))
        ->assertRedirect('https://example.com/live-score');
});

test('internal amateur identifiers are masked on public score pages', function () {
    $tournament = Tournament::query()->create([
        'name' => 'アマ識別子 非表示大会',
        'setup_status' => 'final',
        'start_date' => '2026-08-30',
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
    ]);
    $internalLicense = 'AMATEUR-PRIVATE-IDENTIFIER';

    GameScore::query()->create([
        'tournament_id' => $tournament->id,
        'stage' => '予選',
        'gender' => 'M',
        'license_number' => $internalLicense,
        'name' => 'アマ確認選手',
        'game_number' => 1,
        'score' => 200,
    ]);
    TournamentResult::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_license_no' => $internalLicense,
        'amateur_name' => 'アマ確認選手',
        'ranking' => 1,
        'games' => 1,
        'total_pin' => 200,
        'average' => 200,
        'ranking_year' => 2026,
    ]);

    $this->get(route('public.tournaments.live', $tournament))
        ->assertOk()
        ->assertSee('アマ確認選手')
        ->assertSee('アマ')
        ->assertDontSee($internalLicense);

    $this->get(route('public.tournaments.results', $tournament))
        ->assertOk()
        ->assertSee('アマ確認選手')
        ->assertSee('アマ')
        ->assertDontSee($internalLicense);

    $this->get(route('public.tournaments.show', $tournament))
        ->assertOk()
        ->assertSee('アマ確認選手')
        ->assertDontSee($internalLicense);
});

test('administrators can verify publication status and maintain external live links', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tournament = Tournament::query()->create([
        'name' => '管理公開導線テスト大会',
        'setup_status' => 'completed',
        'start_date' => '2026-08-30',
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
    ]);
    GameScore::query()->create([
        'tournament_id' => $tournament->id,
        'stage' => '予選',
        'gender' => 'M',
        'license_number' => 'M00009973',
        'name' => '管理導線 選手',
        'game_number' => 1,
        'score' => 200,
    ]);
    TournamentResult::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_license_no' => 'M00009973',
        'ranking' => 1,
        'games' => 1,
        'total_pin' => 200,
        'average' => 200,
        'ranking_year' => 2026,
    ]);

    $this->actingAs($admin)
        ->get(route('tournaments.edit', $tournament))
        ->assertOk()
        ->assertSee('一般公開：大会ページ・速報・全成績')
        ->assertSee('1スコア登録済み')
        ->assertSee('1名登録済み')
        ->assertSee('取込完了（公開）');

    $item = FlashNews::query()->create([
        'title' => '変更前の外部速報',
        'url' => 'https://example.com/before',
    ]);

    $this->put(route('flash_news.update', $item), [
        'title' => '変更後の外部速報',
        'url' => 'https://example.com/after',
    ])->assertRedirect(route('flash_news.index'));

    $this->assertDatabaseHas('flash_news', [
        'id' => $item->id,
        'title' => '変更後の外部速報',
        'url' => 'https://example.com/after',
    ]);

    $this->delete(route('flash_news.destroy', $item))
        ->assertRedirect(route('flash_news.index'));
    $this->assertDatabaseMissing('flash_news', ['id' => $item->id]);
});

test('semifinal live ranking carries preliminary scores according to tournament settings', function () {
    $tournament = Tournament::query()->create([
        'name' => '準決勝持越し設定テスト大会',
        'setup_status' => 'in_progress',
        'start_date' => '2026-08-31',
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
        'result_carry_preset' => 'carry_prelim_to_semifinal_reset_final',
        'result_carry_settings' => [],
    ]);

    $players = [
        ['license' => 'M00009981', 'name' => '持越し 上位選手', 'prelim' => [250, 250], 'semifinal' => 180],
        ['license' => 'M00009982', 'name' => '準決勝 単独上位', 'prelim' => [200, 200], 'semifinal' => 250],
    ];

    foreach ($players as $playerData) {
        $bowler = ProBowler::query()->create([
            'license_no' => $playerData['license'],
            'name_kanji' => $playerData['name'],
            'sex' => 1,
            'is_active' => true,
            'member_class' => 'player',
        ]);

        foreach ($playerData['prelim'] as $index => $score) {
            GameScore::query()->create([
                'tournament_id' => $tournament->id,
                'stage' => '予選',
                'gender' => 'M',
                'license_number' => $bowler->license_no,
                'name' => $bowler->name_kanji,
                'game_number' => $index + 1,
                'score' => $score,
                'pro_bowler_id' => $bowler->id,
            ]);
        }

        GameScore::query()->create([
            'tournament_id' => $tournament->id,
            'stage' => '準決勝',
            'gender' => 'M',
            'license_number' => $bowler->license_no,
            'name' => $bowler->name_kanji,
            'game_number' => 1,
            'score' => $playerData['semifinal'],
            'pro_bowler_id' => $bowler->id,
        ]);
    }

    $this->get(route('public.tournaments.live', [
        'tournament' => $tournament,
        'stage' => '準決勝',
        'upto_game' => 1,
    ]))
        ->assertOk()
        ->assertSee('準決勝 1G終了時点')
        ->assertSee('通算3G')
        ->assertSee('予選の')
        ->assertSee('2Gを持ち込んでいます')
        ->assertSeeInOrder(['持越し 上位選手', '500', '180', '3', '680'])
        ->assertSeeInOrder(['準決勝 単独上位', '400', '250', '3', '650']);
});

test('published snapshot carry stages are reused when legacy tournament settings are empty', function () {
    $tournament = Tournament::query()->create([
        'name' => '公式スナップショット持越しテスト大会',
        'setup_status' => 'final',
        'start_date' => '2026-08-31',
        'year' => 2026,
        'gender' => 'F',
        'official_type' => 'official',
        'result_carry_preset' => 'default',
        'result_carry_settings' => [],
    ]);
    $bowler = ProBowler::query()->create([
        'license_no' => 'F00009983',
        'name_kanji' => '公式持越し 確認選手',
        'sex' => 2,
        'is_active' => true,
        'member_class' => 'player',
    ]);

    foreach ([1 => 220, 2 => 230] as $gameNumber => $score) {
        GameScore::query()->create([
            'tournament_id' => $tournament->id,
            'stage' => '予選',
            'gender' => 'F',
            'license_number' => $bowler->license_no,
            'name' => $bowler->name_kanji,
            'game_number' => $gameNumber,
            'score' => $score,
            'pro_bowler_id' => $bowler->id,
        ]);
    }
    GameScore::query()->create([
        'tournament_id' => $tournament->id,
        'stage' => '準決勝',
        'gender' => 'F',
        'license_number' => $bowler->license_no,
        'name' => $bowler->name_kanji,
        'game_number' => 1,
        'score' => 240,
        'pro_bowler_id' => $bowler->id,
    ]);

    DB::table('tournament_result_snapshots')->insert([
        'tournament_id' => $tournament->id,
        'result_code' => 'semifinal_total',
        'result_name' => '準決勝通算',
        'result_type' => 'total_pin',
        'stage_name' => '準決勝',
        'games_count' => 3,
        'carry_game_count' => 2,
        'carry_stage_names' => json_encode(['予選'], JSON_UNESCAPED_UNICODE),
        'calculation_definition' => json_encode([], JSON_UNESCAPED_UNICODE),
        'is_final' => true,
        'is_published' => true,
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('public.tournaments.live', [
        'tournament' => $tournament,
        'stage' => '準決勝',
        'upto_game' => 1,
    ]))
        ->assertOk()
        ->assertSee('通算3G')
        ->assertSeeInOrder(['公式持越し 確認選手', '450', '240', '3', '690']);
});

test('round robin live ranking includes carry pins and win bonus points', function () {
    $tournament = Tournament::query()->create([
        'name' => 'ラウンドロビン持越しテスト大会',
        'setup_status' => 'final',
        'start_date' => '2026-08-31',
        'year' => 2026,
        'gender' => 'F',
        'official_type' => 'official',
        'result_flow_type' => 'prelim_to_rr_to_final',
        'round_robin_qualifier_count' => 4,
        'round_robin_win_bonus' => 30,
        'round_robin_tie_bonus' => 15,
        'round_robin_position_round_enabled' => false,
        'result_carry_preset' => 'default',
        'result_carry_settings' => [],
    ]);

    $snapshotId = DB::table('tournament_result_snapshots')->insertGetId([
        'tournament_id' => $tournament->id,
        'result_code' => 'semifinal_total',
        'result_name' => '準決勝通算',
        'result_type' => 'total_pin',
        'stage_name' => '準決勝',
        'games_count' => 2,
        'carry_game_count' => 1,
        'carry_stage_names' => json_encode(['予選'], JSON_UNESCAPED_UNICODE),
        'calculation_definition' => json_encode([], JSON_UNESCAPED_UNICODE),
        'is_final' => true,
        'is_published' => true,
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rows = [
        ['license' => 'F00009984', 'name' => 'RR 持越し首位', 'carry' => 500, 'score' => 150],
        ['license' => 'F00009985', 'name' => 'RR 第２選手', 'carry' => 420, 'score' => 180],
        ['license' => 'F00009986', 'name' => 'RR 第３選手', 'carry' => 380, 'score' => 170],
        ['license' => 'F00009987', 'name' => 'RR 第４選手', 'carry' => 300, 'score' => 250],
    ];

    foreach ($rows as $index => $row) {
        $bowler = ProBowler::query()->create([
            'license_no' => $row['license'],
            'name_kanji' => $row['name'],
            'sex' => 2,
            'is_active' => true,
            'member_class' => 'player',
        ]);

        DB::table('tournament_result_snapshot_rows')->insert([
            'snapshot_id' => $snapshotId,
            'ranking' => $index + 1,
            'pro_bowler_id' => $bowler->id,
            'pro_bowler_license_no' => $bowler->license_no,
            'display_name' => $bowler->name_kanji,
            'scratch_pin' => 0,
            'carry_pin' => 0,
            'total_pin' => $row['carry'],
            'games' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['予選', '準決勝'] as $stage) {
            GameScore::query()->create([
                'tournament_id' => $tournament->id,
                'stage' => $stage,
                'gender' => 'F',
                'license_number' => $bowler->license_no,
                'name' => $bowler->name_kanji,
                'game_number' => 1,
                'score' => (int) floor($row['carry'] / 2),
                'pro_bowler_id' => $bowler->id,
            ]);
        }

        GameScore::query()->create([
            'tournament_id' => $tournament->id,
            'stage' => 'ラウンドロビン',
            'gender' => 'F',
            'license_number' => $bowler->license_no,
            'name' => $bowler->name_kanji,
            'game_number' => 1,
            'score' => $row['score'],
            'pro_bowler_id' => $bowler->id,
        ]);
    }

    $this->get(route('public.tournaments.live', [
        'tournament' => $tournament,
        'stage' => 'ラウンドロビン',
        'upto_game' => 1,
    ]))
        ->assertOk()
        ->assertSee('ラウンドロビン順位は、通算トータルピンに勝敗ボーナスを加えたトータルポイント順です')
        ->assertSee('勝-敗-分')
        ->assertSee('ボーナス')
        ->assertSee('トータルP')
        ->assertSeeInOrder(['RR 持越し首位', '500', '150', '150', '3', '650'])
        ->assertSee('+50');
});
