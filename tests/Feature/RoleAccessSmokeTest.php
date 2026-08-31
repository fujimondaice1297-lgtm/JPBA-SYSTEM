<?php

use App\Models\ProBowler;
use App\Models\User;

function createRoleSmokeBowler(string $licenseNo, string $name): ProBowler
{
    return ProBowler::query()->create([
        'license_no' => $licenseNo,
        'name_kanji' => $name,
        'sex' => 1,
        'is_active' => true,
        'is_visible' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'missing',
    ]);
}

function createRoleSmokeUser(string $role, ?ProBowler $bowler = null): User
{
    return User::factory()->create([
        'role' => $role,
        'is_admin' => $role === 'admin',
        'account_status' => User::STATUS_ACTIVE,
        'pro_bowler_id' => $bowler?->id,
        'pro_bowler_license_no' => $bowler?->license_no,
        'license_no' => $bowler?->license_no,
    ]);
}

test('public visitors can use public pages and are redirected away from protected pages', function () {
    $bowler = createRoleSmokeBowler('M00009701', '公開確認 選手');

    foreach ([
        route('public.home'),
        route('public.schedule'),
        route('public.players.index'),
        route('public.players.show', $bowler),
        route('public.tournaments.index'),
        route('public.tournaments.live_results'),
        route('rankings.season_trial'),
        route('rankings.season_trial_championship_priority'),
        route('public.privacy'),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    foreach ([
        route('member.dashboard'),
        route('management.home'),
        route('admin.home'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

test('members can use only their own member workflows and cannot import rankings', function () {
    $memberBowler = createRoleSmokeBowler('M00009702', '会員確認 本人');
    $otherBowler = createRoleSmokeBowler('M00009703', '会員確認 他選手');
    $member = createRoleSmokeUser('member', $memberBowler);

    $this->actingAs($member)->get(route('member.dashboard'))->assertOk();
    $this->actingAs($member)->get(route('athlete.edit'))->assertOk();
    $this->actingAs($member)->get(route('used_balls.index'))->assertOk();

    $annualResponse = $this->actingAs($member)->get(route('ball_annual_registrations.edit', [
        'year' => 2026,
        'pro_bowler_id' => $otherBowler->id,
    ]));
    $annualResponse->assertOk()
        ->assertSee('会員確認 本人')
        ->assertDontSee('会員確認 他選手');

    $rankingResponse = $this->actingAs($member)->get(route('rankings.index'));
    $rankingResponse->assertOk()
        ->assertSee('公式ランキング')
        ->assertDontSee('公式ランキングを確定保存する')
        ->assertDontSee('年度別シード管理へ');
    $this->actingAs($member)->get(route('rankings.season_trial'))->assertOk();
    $this->actingAs($member)->get(route('rankings.season_trial_championship_priority'))->assertOk();

    $this->actingAs($member)
        ->post(route('rankings.import_official'), [
            'ranking_year' => 2026,
            'gender' => 'M',
            'ranking_text' => '1 9702 会員確認本人 1 100 20,000 200.00 1,000 100,000',
        ])
        ->assertForbidden();

    foreach ([
        route('ball_annual_registrations.index'),
        route('management.home'),
        route('tp_registration.index'),
        route('admin.home'),
        route('admin.compliance.index'),
        route('admin.player_accounts.show', $memberBowler),
    ] as $url) {
        $this->actingAs($member)->get($url)->assertForbidden();
    }
});

test('editors can perform staff work but cannot use administrator only pages', function () {
    $bowler = createRoleSmokeBowler('M00009704', 'スタッフ代理 対象選手');
    $editor = createRoleSmokeUser('editor');

    foreach ([
        route('management.home'),
        route('tp_registration.index'),
        route('ball_annual_registrations.index'),
        route('ball_annual_registrations.edit', ['year' => 2026, 'pro_bowler_id' => $bowler->id]),
        route('rankings.index'),
        route('rankings.season_trial'),
        route('rankings.season_trial_championship_priority'),
    ] as $url) {
        $this->actingAs($editor)->get($url)->assertOk();
    }

    $this->actingAs($editor)
        ->get(route('rankings.index'))
        ->assertSee('公式ランキングを確定保存する')
        ->assertSee('年度別シード管理へ');

    foreach ([
        route('admin.home'),
        route('admin.compliance.index'),
        route('admin.player_accounts.show', $bowler),
        route('admin.public_pages.index'),
        route('admin.tools.db.tables'),
    ] as $url) {
        $this->actingAs($editor)->get($url)->assertForbidden();
    }
});

test('administrators can use management and administrator only pages', function () {
    $bowler = createRoleSmokeBowler('M00009705', '管理者確認 対象選手');
    $admin = createRoleSmokeUser('admin');

    foreach ([
        route('management.home'),
        route('admin.home'),
        route('tp_registration.index'),
        route('ball_annual_registrations.index'),
        route('rankings.index'),
        route('rankings.season_trial'),
        route('rankings.season_trial_championship_priority'),
        route('admin.compliance.index'),
        route('admin.player_accounts.show', $bowler),
        route('admin.public_pages.index'),
        route('admin.tools.db.tables'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});
