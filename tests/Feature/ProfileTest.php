<?php

use App\Models\ProBowler;
use App\Models\User;

beforeEach(function () {
    $this->bowler = ProBowler::query()->create([
        'license_no' => 'M00001219',
        'name_kanji' => '川添奨太',
        'sex' => 1,
        'email' => 'player@example.com',
    ]);
    $this->user = User::factory()->create([
        'role' => 'member',
        'pro_bowler_id' => $this->bowler->id,
        'pro_bowler_license_no' => $this->bowler->license_no,
    ]);
});

test('linked player profile page is displayed', function () {
    $response = $this->actingAs($this->user)->get(route('athlete.edit'));

    $response->assertOk()->assertSee('川添奨太');
});

test('player can update only their editable profile fields', function () {
    $response = $this->actingAs($this->user)->put(
        route('athlete.update', $this->bowler),
        [
            'height_cm' => 180,
            'height_is_public' => '1',
            'dominant_arm' => '右',
            'hobby' => '読書',
            'season_goal' => '優勝',
        ],
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('athlete.index', absolute: false));

    $this->bowler->refresh();
    expect($this->bowler->height_cm)->toBe(180)
        ->and($this->bowler->height_is_public)->toBeTrue()
        ->and($this->bowler->dominant_arm)->toBe('右')
        ->and($this->bowler->hobby)->toBe('読書')
        ->and($this->bowler->season_goal)->toBe('優勝');
});

test('player cannot update another player profile', function () {
    $other = ProBowler::query()->create([
        'license_no' => 'M00001220',
        'name_kanji' => '別選手',
        'sex' => 1,
    ]);

    $this->actingAs($this->user)
        ->put(route('athlete.update', $other), ['hobby' => '変更不可'])
        ->assertForbidden();

    expect($other->fresh()->hobby)->toBeNull();
});

test('unlinked member cannot open a player profile editor', function () {
    $unlinked = User::factory()->create(['role' => 'member']);

    $this->actingAs($unlinked)->get(route('athlete.edit'))->assertForbidden();
});
