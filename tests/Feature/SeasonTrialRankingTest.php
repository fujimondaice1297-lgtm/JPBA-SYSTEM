<?php

use App\Models\ProBowler;
use App\Models\ProBowlerSeedList;
use App\Models\ProBowlerSeedListPlayer;
use App\Models\ProBowlerTitle;
use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultPublicationRow;
use App\Services\ProBowlerSeedService;
use App\Services\SeasonTrialRankingService;

function createSeasonTrialRankingBowler(string $licenseNo, string $name, array $attributes = []): ProBowler
{
    return ProBowler::query()->create(array_merge([
        'license_no' => $licenseNo,
        'name_kanji' => $name,
        'sex' => 1,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'valid',
    ], $attributes));
}

function createSeasonTrialRankingTournament(string $name, string $startDate): Tournament
{
    return Tournament::query()->create([
        'name' => $name,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'year' => 2026,
        'gender' => 'M',
        'official_type' => 'official',
        'title_category' => 'season_trial',
        'title_scope' => 'season_trial',
        'counts_for_official_points' => true,
        'counts_for_average' => true,
        'counts_for_prize' => true,
    ]);
}

/** @param array<int,array{bowler:ProBowler,ranking:int,points:int,games:int,total_pin:int}> $rows */
function publishSeasonTrialRankingRows(Tournament $tournament, array $rows): TournamentResultPublication
{
    $publication = TournamentResultPublication::query()->create([
        'tournament_id' => $tournament->id,
        'revision' => 1,
        'status' => TournamentResultPublication::STATUS_CURRENT,
        'row_count' => count($rows),
        'pro_count' => count($rows),
        'amateur_count' => 0,
        'total_points' => array_sum(array_column($rows, 'points')),
        'total_prize_money' => 0,
        'result_checksum' => hash('sha256', $tournament->id.'-result'),
        'distribution_checksum' => hash('sha256', $tournament->id.'-distribution'),
        'source_snapshot_ids' => [],
        'published_at' => $tournament->start_date,
    ]);

    foreach ($rows as $row) {
        TournamentResultPublicationRow::query()->create([
            'publication_id' => $publication->id,
            'ranking' => $row['ranking'],
            'pro_bowler_id' => $row['bowler']->id,
            'pro_bowler_license_no' => $row['bowler']->license_no,
            'display_name' => $row['bowler']->name_kanji,
            'identity_key' => 'pro:'.$row['bowler']->id,
            'gender' => 'M',
            'total_pin' => $row['total_pin'],
            'games' => $row['games'],
            'average' => $row['games'] > 0 ? $row['total_pin'] / $row['games'] : null,
            'points' => $row['points'],
            'award_points' => $row['points'],
            'step_points' => 0,
            'prize_money' => 0,
        ]);
    }

    return $publication;
}

test('season trial ranking adds every published venue and breaks ties by total pin', function () {
    $a = createSeasonTrialRankingBowler('M00009801', 'ST集計 選手A');
    $b = createSeasonTrialRankingBowler('M00009802', 'ST集計 選手B');
    $winter = createSeasonTrialRankingTournament('2026ウィンターシリーズ シーズントライアルA会場', '2026-01-20');
    $spring = createSeasonTrialRankingTournament('2026スプリングシリーズ シーズントライアルB会場', '2026-05-20');

    publishSeasonTrialRankingRows($winter, [
        ['bowler' => $a, 'ranking' => 1, 'points' => 40, 'games' => 12, 'total_pin' => 2400],
        ['bowler' => $b, 'ranking' => 2, 'points' => 20, 'games' => 12, 'total_pin' => 2500],
    ]);
    publishSeasonTrialRankingRows($spring, [
        ['bowler' => $a, 'ranking' => 2, 'points' => 20, 'games' => 12, 'total_pin' => 2400],
        ['bowler' => $b, 'ranking' => 1, 'points' => 40, 'games' => 12, 'total_pin' => 2500],
    ]);

    $ranking = app(SeasonTrialRankingService::class)->ranking(2026);

    expect($ranking['published_tournament_count'])->toBe(2)
        ->and($ranking['rows'])->toHaveCount(2)
        ->and($ranking['rows'][0]['pro_bowler_id'])->toBe($b->id)
        ->and($ranking['rows'][0]['points'])->toBe(60)
        ->and($ranking['rows'][0]['season_points']['winter'])->toBe(20)
        ->and($ranking['rows'][0]['season_points']['spring'])->toBe(40)
        ->and($ranking['rows'][1]['pro_bowler_id'])->toBe($a->id);

    $this->get(route('rankings.season_trial', ['year' => 2026]))
        ->assertOk()
        ->assertSee('シーズントライアル年間ポイントランキング')
        ->assertSee('ST集計 選手B')
        ->assertSee('速報・成績へ戻る')
        ->assertSee('2会場');
});

test('championship priority follows the official category order and removes duplicates', function () {
    $tournamentSeed = createSeasonTrialRankingBowler('M00009811', '優先順位 シード');
    $officialWinner = createSeasonTrialRankingBowler('M00009812', '優先順位 公式優勝');
    $permanentSeed = createSeasonTrialRankingBowler('M00009813', '優先順位 永久', [
        'permanent_seed_date' => '2020-01-01',
    ]);
    $stWinner = createSeasonTrialRankingBowler('M00009814', '優先順位 ST優勝');
    $rankingPlayer = createSeasonTrialRankingBowler('M00009815', '優先順位 ランキング');

    $seedList = ProBowlerSeedList::query()->create([
        'seed_year' => 2026,
        'gender' => 'M',
        'seed_list_type' => 'tournament_seed',
        'base_top_count' => 24,
        'is_active' => true,
    ]);
    ProBowlerSeedListPlayer::query()->create([
        'seed_list_id' => $seedList->id,
        'pro_bowler_id' => $tournamentSeed->id,
        'license_no' => $tournamentSeed->license_no,
        'seed_category' => ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED,
        'seed_rank' => 1,
        'priority_order' => 1,
        'is_active' => true,
    ]);

    $officialTournament = Tournament::query()->create([
        'name' => '2026公式テスト大会',
        'start_date' => '2026-03-01',
        'year' => 2026,
        'gender' => 'M',
        'title_category' => 'normal',
        'title_scope' => 'official',
    ]);
    ProBowlerTitle::query()->create([
        'pro_bowler_id' => $officialWinner->id,
        'tournament_id' => $officialTournament->id,
        'title_name' => $officialTournament->name,
        'year' => 2026,
        'won_date' => '2026-03-01',
        'source' => 'result',
    ]);

    $seasonTrial = createSeasonTrialRankingTournament('2026サマーシリーズ シーズントライアルC会場', '2026-07-20');
    publishSeasonTrialRankingRows($seasonTrial, [
        ['bowler' => $stWinner, 'ranking' => 1, 'points' => 80, 'games' => 12, 'total_pin' => 2700],
        ['bowler' => $rankingPlayer, 'ranking' => 2, 'points' => 70, 'games' => 12, 'total_pin' => 2600],
        ['bowler' => $tournamentSeed, 'ranking' => 3, 'points' => 60, 'games' => 12, 'total_pin' => 2500],
    ]);

    $priority = app(SeasonTrialRankingService::class)->championshipPriority(2026);
    $rows = collect($priority['rows']);

    expect($rows->pluck('category_code')->take(5)->all())->toBe(['①', '②', '③', '④', '⑥'])
        ->and($rows->where('pro_bowler_id', $tournamentSeed->id))->toHaveCount(1)
        ->and($rows->firstWhere('pro_bowler_id', $rankingPlayer->id)['category_code'])->toBe('⑥')
        ->and($priority['capacity'])->toBe(55);

    $this->get(route('rankings.season_trial_championship_priority', ['year' => 2026]))
        ->assertOk()
        ->assertSee('STチャンピオンズ優先出場一覧')
        ->assertSee('優先順位 ランキング')
        ->assertSee('速報・成績へ戻る')
        ->assertSee('ST年間ポイントランキング上位者');
});
