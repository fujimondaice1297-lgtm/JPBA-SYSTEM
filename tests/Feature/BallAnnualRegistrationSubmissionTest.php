<?php

use App\Models\ApprovedBall;
use App\Models\BallAnnualRegistration;
use App\Models\BallAnnualRegistrationHistory;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\UsedBall;
use App\Models\User;
use App\Services\BallAnnualRegistrationService;

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

test('only annually approved balls with a valid inspection carry over to the next year', function () {
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009991',
        'name_kanji' => '年度引継 確認選手',
        'sex' => 1,
        'is_active' => true,
    ]);
    $catalogBall = ApprovedBall::query()->create([
        'name' => 'ANNUAL CARRYOVER TEST',
        'manufacturer' => 'ABS',
        'brand' => 'NANODESU',
        'approved' => true,
        'catalog_status' => 'listed',
        'usbc_match_status' => 'matched',
        'release_date' => '2026-01-01',
    ]);

    $validBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $catalogBall->id,
        'serial_number' => 'CARRYOVER-VALID',
        'inspection_number' => 'INSPECTION-VALID',
        'registered_at' => '2026-07-01',
        'expires_at' => '2027-06-30',
    ]);
    $expiredBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $catalogBall->id,
        'serial_number' => 'CARRYOVER-EXPIRED',
        'inspection_number' => 'INSPECTION-EXPIRED',
        'registered_at' => '2026-01-01',
        'expires_at' => '2026-12-31',
    ]);
    $provisionalBall = UsedBall::query()->create([
        'pro_bowler_id' => $bowler->id,
        'approved_ball_id' => $catalogBall->id,
        'serial_number' => 'CARRYOVER-PROVISIONAL',
        'registered_at' => '2026-08-01',
    ]);

    $previous = BallAnnualRegistration::query()->create([
        'pro_bowler_id' => $bowler->id,
        'registration_year' => 2026,
        'revision' => 1,
        'status' => BallAnnualRegistration::STATUS_APPROVED,
        'approved_at' => now(),
    ]);
    $previous->usedBalls()->attach([
        $validBall->id,
        $expiredBall->id,
        $provisionalBall->id,
    ]);

    $service = app(BallAnnualRegistrationService::class);
    $carryover = $service->latestApprovedOrCarryover((int) $bowler->id, 2027);

    expect($carryover)->not->toBeNull()
        ->and($carryover->registration_year)->toBe(2027)
        ->and($carryover->status)->toBe(BallAnnualRegistration::STATUS_APPROVED)
        ->and($carryover->usedBalls()->pluck('used_balls.id')->map(fn ($id) => (int) $id)->all())
        ->toBe([$validBall->id]);

    $this->assertDatabaseHas('ball_annual_registration_histories', [
        'registration_id' => $carryover->id,
        'action' => 'inspection_carryover',
        'to_status' => BallAnnualRegistration::STATUS_APPROVED,
    ]);

    $second = $service->latestApprovedOrCarryover((int) $bowler->id, 2027);

    expect($second?->id)->toBe($carryover->id)
        ->and(BallAnnualRegistration::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('registration_year', 2027)
            ->count())->toBe(1);
});

test('staff proxy submission can be returned resubmitted and approved for tournament use', function () {
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009989',
        'name_kanji' => '代理申請 確認選手',
        'sex' => 1,
        'is_active' => true,
    ]);
    $member = User::factory()->create([
        'name' => '代理申請 確認選手',
        'role' => 'member',
        'pro_bowler_id' => $bowler->id,
        'pro_bowler_license_no' => $bowler->license_no,
        'license_no' => $bowler->license_no,
    ]);
    $admin = User::factory()->create([
        'name' => '年度申請 管理者',
        'role' => 'admin',
        'is_admin' => true,
    ]);
    $catalogBall = ApprovedBall::query()->create([
        'name' => 'PROXY ANNUAL TEST',
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
        'serial_number' => 'PROXY-ANNUAL-SERIAL',
        'registered_at' => '2026-08-31',
    ]);
    $tournament = Tournament::query()->create([
        'name' => '代理大会ボール登録テスト',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-01',
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

    $this->actingAs($admin)
        ->post(route('ball_annual_registrations.submit'), [
            'year' => 2026,
            'pro_bowler_id' => $bowler->id,
            'used_ball_ids' => [$usedBall->id],
        ])
        ->assertRedirect();

    $registration = BallAnnualRegistration::query()
        ->where('pro_bowler_id', $bowler->id)
        ->sole();

    expect($registration->status)->toBe(BallAnnualRegistration::STATUS_SUBMITTED)
        ->and($registration->submitted_by_user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('ball_annual_registrations.return', $registration), [
            'return_reason' => 'シリアル番号を再確認してください。',
        ])
        ->assertRedirect();

    expect($registration->refresh()->status)->toBe(BallAnnualRegistration::STATUS_RETURNED)
        ->and($registration->return_reason)->toBe('シリアル番号を再確認してください。')
        ->and($registration->returned_by_user_id)->toBe($admin->id);

    $this->actingAs($member)
        ->get(route('ball_annual_registrations.edit', ['year' => 2026]))
        ->assertOk()
        ->assertSee('差戻し理由')
        ->assertSee('シリアル番号を再確認してください。');

    $this->actingAs($member)
        ->post(route('ball_annual_registrations.submit'), [
            'year' => 2026,
            'used_ball_ids' => [$usedBall->id],
        ])
        ->assertRedirect();

    expect($registration->refresh()->status)->toBe(BallAnnualRegistration::STATUS_SUBMITTED)
        ->and($registration->submitted_by_user_id)->toBe($member->id)
        ->and($registration->return_reason)->toBeNull();

    $this->actingAs($admin)
        ->post(route('ball_annual_registrations.approve', $registration))
        ->assertRedirect();

    expect($registration->refresh()->status)->toBe(BallAnnualRegistration::STATUS_APPROVED)
        ->and($registration->approved_by_user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('member.entries.balls.edit', $entry))
        ->assertOk()
        ->assertSee('スタッフ代理入力')
        ->assertSee('PROXY ANNUAL TEST');

    $this->actingAs($admin)
        ->post(route('member.entries.balls.store', $entry), [
            'used_ball_ids' => [$usedBall->id],
        ])
        ->assertRedirect(route('member.entries.balls.edit', $entry));

    $this->assertDatabaseHas('tournament_entry_balls', [
        'tournament_entry_id' => $entry->id,
        'used_ball_id' => $usedBall->id,
    ]);

    expect(BallAnnualRegistrationHistory::query()
        ->where('registration_id', $registration->id)
        ->orderBy('id')
        ->pluck('action')
        ->all())->toBe(['submitted', 'returned', 'submitted', 'approved']);
});
