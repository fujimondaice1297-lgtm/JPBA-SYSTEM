<?php

use App\Models\FlashNews;
use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentResult;
use App\Models\User;

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
