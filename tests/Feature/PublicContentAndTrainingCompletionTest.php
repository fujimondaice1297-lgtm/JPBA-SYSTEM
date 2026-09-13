<?php

use App\Models\Information;
use App\Models\InformationFile;
use App\Models\ProBowler;
use App\Models\Sponsor;
use App\Models\Training;
use App\Models\TrainingOfficialList;
use App\Models\TrainingOfficialListEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('pro wappen guidance is published inside the new site', function () {
    foreach ([
        'Emblem_N.jpg',
        'Emblem_A.jpg',
        'Emblem_H.jpg',
        'Hpro_Murata1.jpg',
        '0115_3.jpg',
        'Hpro_Murata2.jpg',
    ] as $filename) {
        expect(is_file(public_path("images/jpba/pro-wappen/{$filename}")))->toBeTrue();
    }

    $this->get('/pages/pro-wappen')
        ->assertOk()
        ->assertSee('赤枠ワッペン')
        ->assertSee('金枠ワッペン')
        ->assertSee('金ワッペン')
        ->assertSee('/images/jpba/pro-wappen/Emblem_N.jpg', false);

    $this->get(route('public.records.index'))
        ->assertOk()
        ->assertSee('プロワッペン')
        ->assertSee('/pages/pro-wappen', false);
});

test('instructor training archive exposes saved internal articles and files by year', function () {
    $information = Information::query()->create([
        'title' => '2030認定インストラクター資格取得講習会',
        'body' => '内部保存済み資料です。',
        'body_format' => 'plain',
        'is_public' => true,
        'category' => 'ｲﾝｽﾄﾗｸﾀｰ',
        'published_at' => '2030-06-01 10:00:00',
        'audience' => 'public',
    ]);
    $file = InformationFile::query()->create([
        'information_id' => $information->id,
        'type' => 'pdf',
        'title' => '開催要項',
        'file_path' => 'documents/test/instructor-guide.pdf',
        'visibility' => 'public',
        'sort_order' => 1,
    ]);

    $this->get(route('public.instructors.training_archive', ['year' => 2030]))
        ->assertOk()
        ->assertSee($information->title)
        ->assertSee('開催要項')
        ->assertSee(route('information_files.download', $file), false);
});

test('administrator can manage a timed sponsor banner and public home only shows active banners', function () {
    Storage::fake('public');
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => User::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)->post(route('admin.sponsors.store'), [
        'name' => '公式協賛テスト',
        'logo' => UploadedFile::fake()->image('sponsor.png', 640, 180),
        'alt_text' => '公式協賛テスト バナー',
        'website' => 'https://example.com/sponsor',
        'is_published' => '1',
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'sort_order' => 10,
    ])->assertSessionHasNoErrors();

    $active = Sponsor::query()->where('name', '公式協賛テスト')->sole();
    Storage::disk('public')->assertExists($active->logo_path);
    Sponsor::query()->create([
        'name' => '公開終了協賛',
        'logo_path' => 'sponsor-banners/expired.png',
        'is_published' => true,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
        'sort_order' => 20,
    ]);

    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee('公式協賛テスト バナー')
        ->assertSee('https://example.com/sponsor', false)
        ->assertDontSee('公開終了協賛');

    $this->actingAs($admin)->get(route('admin.sponsors.index'))
        ->assertOk()
        ->assertSee('公式協賛テスト');
});

test('staff can search and export real participants from a formal official training cycle', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => User::STATUS_ACTIVE,
    ]);
    $bowler = ProBowler::query()->create([
        'license_no' => 'M00009876',
        'license_no_num' => 9876,
        'name_kanji' => '公式講習 実在選手',
        'sex' => 1,
        'is_active' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'official_list_valid',
    ]);
    $training = Training::query()->where('code', 'mandatory')->firstOrFail();
    $officialList = TrainingOfficialList::query()->create([
        'training_id' => $training->id,
        'edition_number' => 99,
        'title' => '第99回TP講習会 受講修了者リスト',
        'valid_from' => '2030-01-01',
        'valid_through' => '2032-12-31',
        'source_url' => 'https://example.com/tp-99.pdf',
        'source_published_at' => '2030-01-02 10:00:00',
        'source_sha256' => str_repeat('9', 64),
        'is_current' => false,
        'sync_status' => 'imported',
        'total_count' => 1,
        'male_count' => 1,
        'female_count' => 0,
        'matched_count' => 1,
        'unmatched_count' => 0,
        'inactive_count' => 0,
    ]);
    TrainingOfficialListEntry::query()->create([
        'training_official_list_id' => $officialList->id,
        'pro_bowler_id' => $bowler->id,
        'gender' => 'M',
        'license_no_num' => 9876,
        'source_order' => 1,
        'source_name' => '公式講習 実在選手',
        'match_status' => 'matched',
    ]);

    $this->actingAs($admin)
        ->get(route('tp_registration.official_lists.show', [$officialList, 'q' => '9876']))
        ->assertOk()
        ->assertSee('第99回TP講習会')
        ->assertSee('公式講習 実在選手')
        ->assertSee('9876')
        ->assertDontSee('M00009876');

    $response = $this->actingAs($admin)
        ->get(route('tp_registration.official_lists.export', $officialList))
        ->assertOk()
        ->assertDownload('tp_official_list_99.csv');
    expect($response->streamedContent())->toContain('公式講習 実在選手')->toContain('9876');
});
