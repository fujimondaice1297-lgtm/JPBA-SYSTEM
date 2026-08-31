<?php

use App\Models\ProBowler;

function createPublicSearchBowler(string $license, string $name, int $sex, string $memberClass): ProBowler
{
    return ProBowler::query()->create([
        'license_no' => $license,
        'name_kanji' => $name,
        'sex' => $sex,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => $memberClass,
        'can_enter_official_tournament' => $memberClass === 'player',
        'training_compliance_status' => 'valid',
    ]);
}

test('public player search stays empty until gender and player class are both selected', function () {
    createPublicSearchBowler('M00009601', '一般検索 男子プロ', 1, 'player');
    createPublicSearchBowler('M00009602', '一般検索 男子講師', 1, 'pro_instructor');
    createPublicSearchBowler('F00000961', '一般検索 女子プロ', 2, 'player');

    $this->get(route('public.players.index'))
        ->assertOk()
        ->assertSee('性別と選手区分は必須です')
        ->assertDontSee('一般検索 男子プロ')
        ->assertDontSee('一般検索 男子講師')
        ->assertDontSee('一般検索 女子プロ');

    $this->get(route('public.players.index', ['gender' => '男性']))
        ->assertOk()
        ->assertDontSee('一般検索 男子プロ');

    $this->get(route('public.players.index', [
        'gender' => '男性',
        'member_class' => 'player',
        'player_status' => 'active',
    ]))
        ->assertOk()
        ->assertSee('一般検索 男子プロ')
        ->assertDontSee('一般検索 男子講師')
        ->assertDontSee('一般検索 女子プロ');

    $this->get(route('public.players.index', [
        'gender' => '男性',
        'member_class' => 'pro_instructor',
        'player_status' => 'active',
    ]))
        ->assertOk()
        ->assertSee('一般検索 男子講師')
        ->assertDontSee('一般検索 男子プロ');
});
