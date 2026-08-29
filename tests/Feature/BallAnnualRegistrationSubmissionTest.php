<?php

use App\Models\ApprovedBall;
use App\Models\BallAnnualRegistration;
use App\Models\ProBowler;
use App\Models\UsedBall;
use App\Models\User;

test('a member can submit first and revised annual ball registrations on PostgreSQL', function () {
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009992',
        'name_kanji' => '年度申請 確認選手',
        'sex' => 1,
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'role' => 'member',
        'pro_bowler_id' => $bowler->id,
        'pro_bowler_license_no' => $bowler->license_no,
        'license_no' => $bowler->license_no,
    ]);
    $catalogBall = ApprovedBall::query()->create([
        'name' => 'ANNUAL REGISTRATION TEST',
        'manufacturer' => 'ABS',
        'brand' => 'NANODESU',
        'approved' => true,
        'catalog_status' => 'listed',
        'usbc_match_status' => 'matched',
        'release_date' => '2026-01-01',
    ]);
    $usedBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $catalogBall->id,
        'serial_number' => 'ANNUAL-SERIAL-1',
        'registered_at' => '2026-08-29',
    ]);

    $this->actingAs($member)
        ->post(route('ball_annual_registrations.submit'), [
            'year' => 2026,
            'used_ball_ids' => [$usedBall->id],
        ])
        ->assertRedirect(route('ball_annual_registrations.edit', [
            'year' => 2026,
            'pro_bowler_id' => $bowler->id,
        ]));

    $first = BallAnnualRegistration::query()
        ->where('pro_bowler_id', $bowler->id)
        ->where('registration_year', 2026)
        ->sole();

    $this->assertSame(1, $first->revision);
    $this->assertSame(BallAnnualRegistration::STATUS_SUBMITTED, $first->status);
    $this->assertDatabaseHas('ball_annual_registration_items', [
        'registration_id' => $first->id,
        'used_ball_id' => $usedBall->id,
    ]);

    $first->update(['status' => BallAnnualRegistration::STATUS_APPROVED]);

    $this->actingAs($member)
        ->post(route('ball_annual_registrations.submit'), [
            'year' => 2026,
            'used_ball_ids' => [$usedBall->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ball_annual_registrations', [
        'pro_bowler_id' => $bowler->id,
        'registration_year' => 2026,
        'revision' => 2,
        'status' => BallAnnualRegistration::STATUS_SUBMITTED,
    ]);
});
