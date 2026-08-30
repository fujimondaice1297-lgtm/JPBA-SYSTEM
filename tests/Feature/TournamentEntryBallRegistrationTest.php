<?php

use App\Models\ApprovedBall;
use App\Models\BallAnnualRegistration;
use App\Models\ProBowler;
use App\Models\ProBowlerTraining;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\UsedBall;
use App\Models\User;
use App\Services\TrainingComplianceService;

test('a qualified member can register annually approved balls and the public view stays private', function () {
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009993',
        'name_kanji' => '大会ボール 確認選手',
        'sex' => 1,
        'is_active' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'public_image_path' => 'https://www.jpba1.jp/assets/img/prof/test-player.jpg',
    ]);
    $member = User::factory()->create([
        'role' => 'member',
        'pro_bowler_id' => $bowler->id,
        'pro_bowler_license_no' => $bowler->license_no,
        'license_no' => $bowler->license_no,
    ]);
    $training = app(TrainingComplianceService::class)->mandatoryTraining();
    ProBowlerTraining::query()->create([
        'pro_bowler_id' => $bowler->id,
        'training_id' => $training->id,
        'completed_at' => '2026-01-01',
        'expires_at' => '2028-12-31',
        'record_status' => 'valid',
    ]);

    $approvedCatalogBall = ApprovedBall::query()->create([
        'name' => 'ANNUAL APPROVED BALL',
        'manufacturer' => 'ABS',
        'brand' => 'NANODESU',
        'approved' => true,
        'catalog_status' => 'listed',
        'usbc_match_status' => 'matched',
        'release_date' => '2026-01-01',
    ]);
    $unapprovedCatalogBall = ApprovedBall::query()->create([
        'name' => 'ANNUAL UNAPPROVED BALL',
        'manufacturer' => 'ABS',
        'brand' => 'NANODESU',
        'approved' => true,
        'catalog_status' => 'listed',
        'usbc_match_status' => 'matched',
        'release_date' => '2026-01-01',
    ]);
    $approvedUsedBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $approvedCatalogBall->id,
        'serial_number' => 'PRIVATE-SERIAL-APPROVED',
        'registered_at' => '2026-08-30',
    ]);
    $unapprovedUsedBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $unapprovedCatalogBall->id,
        'serial_number' => 'PRIVATE-SERIAL-UNAPPROVED',
        'registered_at' => '2026-08-30',
    ]);

    $annualRegistration = BallAnnualRegistration::query()->create([
        'pro_bowler_id' => $bowler->id,
        'registration_year' => 2026,
        'revision' => 1,
        'status' => BallAnnualRegistration::STATUS_APPROVED,
        'approved_at' => now(),
    ]);
    $annualRegistration->usedBalls()->attach($approvedUsedBall->id);

    $tournament = Tournament::query()->create([
        'name' => '大会ボール登録テスト',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-01',
        'year' => 2026,
        'gender' => 'M',
        'ball_registration_limit' => 4,
        'inspection_required' => false,
    ]);
    $entry = TournamentEntry::query()->create([
        'tournament_id' => $tournament->id,
        'pro_bowler_id' => $bowler->id,
        'status' => 'entry',
    ]);

    $this->actingAs($member)
        ->get(route('member.entries.balls.edit', $entry))
        ->assertOk()
        ->assertSee('ANNUAL APPROVED BALL')
        ->assertDontSee('ANNUAL UNAPPROVED BALL');

    $this->actingAs($member)
        ->post(route('member.entries.balls.store', $entry), [
            'used_ball_ids' => [$approvedUsedBall->id, $unapprovedUsedBall->id],
        ])
        ->assertSessionHasErrors('used_ball_ids');

    $this->assertDatabaseMissing('tournament_entry_balls', [
        'tournament_entry_id' => $entry->id,
    ]);

    $this->actingAs($member)
        ->post(route('member.entries.balls.store', $entry), [
            'used_ball_ids' => [$approvedUsedBall->id],
        ])
        ->assertRedirect(route('member.entries.balls.edit', $entry));

    $this->assertDatabaseHas('tournament_entry_balls', [
        'tournament_entry_id' => $entry->id,
        'used_ball_id' => $approvedUsedBall->id,
    ]);

    $this->actingAs($member)
        ->get(route('scores.entry_balls.show', [
            'entry' => $entry,
            'public' => 1,
        ]))
        ->assertOk()
        ->assertSee('大会登録ボール')
        ->assertSee('ANNUAL APPROVED BALL')
        ->assertSee('https://www.jpba1.jp/assets/img/prof/test-player.jpg', false)
        ->assertDontSee('PRIVATE-SERIAL-APPROVED')
        ->assertDontSee('PRIVATE-SERIAL-UNAPPROVED')
        ->assertDontSee('検量証番号')
        ->assertDontSee('有効期限');
});
