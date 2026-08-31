<?php

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultPublicationRow;
use App\Services\OfficialCurrentRankingService;

function createCurrentRankingBowler(string $license, string $name, int $sex): ProBowler
{
    return ProBowler::query()->create([
        'license_no' => $license,
        'name_kanji' => $name,
        'sex' => $sex,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'valid',
    ]);
}

function publishCurrentRankingRow(
    Tournament $tournament,
    ProBowler $bowler,
    int $points,
    int $prize,
    int $games,
    int $totalPin,
): void {
    $publication = TournamentResultPublication::query()->create([
        'tournament_id' => $tournament->id,
        'revision' => 1,
        'status' => TournamentResultPublication::STATUS_CURRENT,
        'row_count' => 1,
        'pro_count' => 1,
        'amateur_count' => 0,
        'total_points' => $points,
        'total_prize_money' => $prize,
        'result_checksum' => hash('sha256', 'current-ranking-result-'.$tournament->id.'-'.$bowler->id),
        'distribution_checksum' => hash('sha256', 'current-ranking-distribution-'.$tournament->id.'-'.$bowler->id),
        'source_snapshot_ids' => [],
        'published_at' => $tournament->start_date,
    ]);

    TournamentResultPublicationRow::query()->create([
        'publication_id' => $publication->id,
        'ranking' => 1,
        'pro_bowler_id' => $bowler->id,
        'pro_bowler_license_no' => $bowler->license_no,
        'display_name' => $bowler->name_kanji,
        'identity_key' => 'pro:'.$bowler->id,
        'gender' => $bowler->sex === 2 ? 'F' : 'M',
        'total_pin' => $totalPin,
        'games' => $games,
        'average' => $games > 0 ? $totalPin / $games : null,
        'points' => $points,
        'award_points' => $points,
        'step_points' => 0,
        'prize_money' => $prize,
    ]);
}

test('current official rankings aggregate current publications and separate gender and ranking type', function () {
    $maleA = createCurrentRankingBowler('M00009501', '公式集計 男子A', 1);
    $maleB = createCurrentRankingBowler('M00009502', '公式集計 男子B', 1);
    $female = createCurrentRankingBowler('F00000951', '公式集計 女子', 2);

    $officialA = Tournament::query()->create([
        'name' => '2026公式ランキングA',
        'year' => 2026,
        'start_date' => '2026-04-01',
        'gender' => 'M',
        'counts_for_official_points' => true,
        'counts_for_average' => true,
        'counts_for_prize' => true,
    ]);
    $officialB = Tournament::query()->create([
        'name' => '2026公式ランキングB',
        'year' => 2026,
        'start_date' => '2026-05-01',
        'gender' => 'M',
        'counts_for_official_points' => true,
        'counts_for_average' => true,
        'counts_for_prize' => true,
    ]);
    $excluded = Tournament::query()->create([
        'name' => '2026ポイント賞金対象外',
        'year' => 2026,
        'start_date' => '2026-06-01',
        'gender' => 'M',
        'counts_for_official_points' => false,
        'counts_for_average' => false,
        'counts_for_prize' => false,
        'title_scope' => 'none',
    ]);
    $women = Tournament::query()->create([
        'name' => '2026女子公式ランキング',
        'year' => 2026,
        'start_date' => '2026-07-01',
        'gender' => 'F',
        'counts_for_official_points' => true,
        'counts_for_average' => true,
        'counts_for_prize' => true,
    ]);

    publishCurrentRankingRow($officialA, $maleA, 100, 200000, 10, 2200);
    publishCurrentRankingRow($officialB, $maleB, 90, 300000, 10, 2100);
    publishCurrentRankingRow($excluded, $maleA, 999, 999999, 4, 1200);
    publishCurrentRankingRow($women, $female, 150, 400000, 10, 2150);

    $service = app(OfficialCurrentRankingService::class);
    $points = $service->ranking(2026, 'M', 'points');
    $prize = $service->ranking(2026, 'M', 'prize');

    expect($points['rows'][0]['pro_bowler_id'])->toBe($maleA->id)
        ->and($points['rows'][0]['points'])->toBe(100)
        ->and($points['rows'][0]['prize_money'])->toBe(200000)
        ->and($points['rows'][0]['games'])->toBe(10)
        ->and($prize['rows'][0]['pro_bowler_id'])->toBe($maleB->id)
        ->and(collect($points['rows'])->pluck('pro_bowler_id')->all())->not->toContain($female->id);

    $this->get(route('rankings.official_current', [
        'year' => 2026,
        'gender' => 'M',
        'type' => 'points',
    ]))
        ->assertOk()
        ->assertSee('2026年 男子ポイントランキング')
        ->assertSeeInOrder(['公式集計 男子A', '公式集計 男子B'])
        ->assertDontSee('公式集計 女子');

    $this->get(route('rankings.official_current', [
        'year' => 2026,
        'gender' => 'M',
        'type' => 'prize',
    ]))
        ->assertOk()
        ->assertSee('2026年 男子賞金ランキング')
        ->assertSeeInOrder(['公式集計 男子B', '公式集計 男子A']);
});
