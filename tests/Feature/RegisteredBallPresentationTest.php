<?php

use App\Models\ApprovedBall;
use App\Models\ProBowler;
use App\Models\User;

beforeEach(function () {
    $this->bowler = ProBowler::query()->create([
        'license_no' => 'M00009991',
        'name_kanji' => '登録確認 選手',
        'sex' => 1,
        'is_active' => true,
    ]);
    $this->member = User::factory()->create([
        'role' => 'member',
        'pro_bowler_id' => $this->bowler->id,
        'pro_bowler_license_no' => $this->bowler->license_no,
        'license_no' => $this->bowler->license_no,
    ]);
    $this->globalBall = ApprovedBall::query()->create([
        'name' => 'VENGEANCE TEST',
        'manufacturer' => 'ABS',
        'brand' => '900GLOBAL',
        'usbc_match_status' => 'matched',
        'usbc_matched_brand' => '900 Global',
        'approved' => true,
        'catalog_status' => 'listed',
        'release_date' => '2026-01-01',
    ]);
    $this->nanodesuBall = ApprovedBall::query()->create([
        'name' => 'NANODESU TEST',
        'manufacturer' => 'ABS',
        'brand' => 'NANODESU',
        'usbc_match_status' => 'matched',
        'usbc_matched_brand' => 'ABS',
        'approved' => true,
        'catalog_status' => 'listed',
        'release_date' => '2026-01-01',
    ]);
});

test('a provisional mirrored ball is presented as one logical registration', function () {
    $serialNumber = 'SERIAL-PROVISIONAL-1';

    $this->actingAs($this->member)
        ->post(route('registered_balls.store'), [
            'license_no' => $this->bowler->license_no,
            'approved_ball_id' => $this->globalBall->id,
            'serial_number' => $serialNumber,
            'registered_at' => '2026-08-28',
            'inspection_number' => '',
        ])
        ->assertRedirect(route('registered_balls.index'));

    $this->assertDatabaseHas('registered_balls', [
        'pro_bowler_id' => $this->bowler->id,
        'serial_number' => $serialNumber,
        'inspection_number' => null,
    ]);
    $this->assertDatabaseHas('used_balls', [
        'pro_bowler_id' => $this->bowler->id,
        'serial_number' => $serialNumber,
        'inspection_number' => null,
    ]);

    $response = $this->actingAs($this->member)
        ->get(route('registered_balls.index'))
        ->assertOk()
        ->assertSee('総件数:')
        ->assertSee('仮登録')
        ->assertSee('検量証登録');

    expect(preg_match_all(
        '/<td>\s*'.preg_quote($serialNumber, '/').'\s*<\/td>/',
        $response->getContent()
    ))->toBe(1);
});

test('ball registration choices use product brands instead of catalog distributors', function () {
    $this->actingAs($this->member)
        ->get(route('registered_balls.create'))
        ->assertOk()
        ->assertSee('ブランドで絞り込み')
        ->assertSee('data-brand="900GLOBAL"', false)
        ->assertSee('900GLOBAL - VENGEANCE TEST')
        ->assertSee('NANODESU - NANODESU TEST')
        ->assertDontSee('ABS - VENGEANCE TEST');

    $this->actingAs($this->member)
        ->get(route('used_balls.create', ['brand' => '900GLOBAL']))
        ->assertOk()
        ->assertSee('900GLOBAL - VENGEANCE TEST')
        ->assertDontSee('ABS - NANODESU TEST');
});
