<?php

use App\Models\Information;
use App\Models\User;

test('public information pages use public navigation while member detail keeps member navigation', function () {
    $information = Information::query()->create([
        'title' => '公開レイアウト確認のお知らせ',
        'body' => '一般公開用の本文です。',
        'is_public' => true,
        'category' => 'NEWS',
        'published_at' => now(),
        'starts_at' => now()->subMinute(),
        'audience' => 'public',
    ]);

    $this->get(route('informations.index'))
        ->assertOk()
        ->assertSee('INFORMATION')
        ->assertSee('公開レイアウト確認のお知らせ')
        ->assertSee('class="jpba-head"', false)
        ->assertDontSee('class="jpba-navbar', false);

    $this->get(route('informations.show', $information))
        ->assertOk()
        ->assertSee('お知らせ 詳細')
        ->assertSee('公開レイアウト確認のお知らせ')
        ->assertSee('class="jpba-head"', false)
        ->assertDontSee('class="jpba-navbar', false);

    $member = User::factory()->create(['role' => 'member']);
    $this->actingAs($member)
        ->get(route('informations.member.show', $information))
        ->assertOk()
        ->assertSee('お知らせ 詳細')
        ->assertSee('jpba-navbar', false)
        ->assertDontSee('class="jpba-head"', false);
});

test('archived information renders its sanitized html body', function () {
    $information = Information::query()->create([
        'title' => '移行記事',
        'body' => '<p><strong>保存済み本文</strong></p>',
        'body_format' => 'html',
        'is_public' => true,
        'category' => 'NEWS',
        'published_at' => now(),
        'starts_at' => now()->subMinute(),
        'audience' => 'public',
        'source_type' => 'legacy_information',
        'source_key' => 'legacy-information-test',
    ]);

    $this->get(route('informations.show', $information))
        ->assertOk()
        ->assertSee('<strong>保存済み本文</strong>', false);

    $this->get(route('informations.index'))
        ->assertOk()
        ->assertSee('保存済み本文')
        ->assertDontSee('&lt;p&gt;', false)
        ->assertDontSee('<strong>保存済み本文</strong>', false);
});
