<?php

use App\Models\ProTestCandidate;
use App\Models\ProTestEvent;
use App\Models\ProTestResultPublication;
use App\Models\ProTestScore;
use App\Models\ProTestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can import scores and only an explicitly published privacy safe snapshot is public', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin)->post(route('pro_tests.store'), [
        'year' => 2027,
        'name' => '2027年度プロボウラー資格取得テスト',
    ])->assertRedirect();

    $event = ProTestEvent::query()->sole();

    $this->actingAs($admin)->post(route('pro_tests.sessions.store', $event), [
        'gender' => 'M',
        'stage_code' => 'first',
        'stage_label' => '第1次テスト',
        'day_number' => 1,
        'test_date' => '2027-04-08',
        'venue' => 'テスト会場',
        'game_start' => 1,
        'game_end' => 3,
        'sort_order' => 10,
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event), [
        'candidate_rows' => implode("\n", [
            'M001,男子,公開 太郎,コウカイ タロウ,東京都,右',
            'M002,男子,受験 次郎,ジュケン ジロウ,大阪府,左',
        ]),
    ])->assertRedirect();

    $session = ProTestSession::query()->sole();
    $this->actingAs($admin)->post(route('pro_tests.sessions.scores.import', [$event, $session]), [
        'score_rows' => "M001,200,210,220\nM002,190,195,200",
    ])->assertRedirect();

    $this->get(route('public.pro_tests.sessions.show', [$event, $session]))->assertNotFound();

    $this->actingAs($admin)
        ->post(route('pro_tests.sessions.publish', [$event, $session]))
        ->assertRedirect();

    $this->get(route('public.pro_tests.sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('公開 太郎')
        ->assertSee('630')
        ->assertDontSee('<th>年齢</th>', false)
        ->assertDontSee('<th>生年月日</th>', false)
        ->assertDontSee('<th>電話</th>', false)
        ->assertDontSee('<th>メール</th>', false);

    $this->actingAs($admin)->post(route('pro_tests.sessions.scores.import', [$event, $session]), [
        'score_rows' => 'M001,300,300,300',
    ])->assertRedirect();

    $this->get(route('public.pro_tests.sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('630')
        ->assertDontSee('>900<', false);

    $this->actingAs($admin)
        ->post(route('pro_tests.sessions.publish', [$event, $session]))
        ->assertRedirect();

    expect(ProTestResultPublication::query()->count())->toBe(2);
    $this->get(route('public.pro_tests.sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('第2版')
        ->assertSee('>900<', false);
});

test('final public result contains passers only', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $event = ProTestEvent::query()->create([
        'year' => 2027,
        'name' => '最終結果公開テスト',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    $event->candidates()->createMany([
        ['exam_number' => 'M001', 'gender' => 'M', 'name' => '合格 一郎', 'final_result' => 'pending'],
        ['exam_number' => 'M002', 'gender' => 'M', 'name' => '不合格 二郎', 'final_result' => 'pending'],
    ]);

    $this->actingAs($admin)->post(route('pro_tests.final_results.import', $event), [
        'final_result_rows' => "M001,合格,M00001500\nM002,不合格",
    ])->assertRedirect();
    $this->actingAs($admin)
        ->post(route('pro_tests.final_results.publish', $event))
        ->assertRedirect();

    $this->get(route('public.pro_tests.show', $event))
        ->assertOk()
        ->assertSee('合格 一郎')
        ->assertDontSee('不合格 二郎')
        ->assertDontSee('<th>年齢</th>', false)
        ->assertDontSee('<th>住所</th>', false)
        ->assertDontSee('<th>連絡先</th>', false);

    $event->candidates()->where('exam_number', 'M001')->update(['name' => '訂正 一郎']);

    $this->get(route('public.pro_tests.show', $event))
        ->assertOk()
        ->assertSee('合格 一郎')
        ->assertDontSee('訂正 一郎');

    $this->actingAs($admin)
        ->post(route('pro_tests.final_results.publish', $event))
        ->assertRedirect();

    $this->get(route('public.pro_tests.show', $event))
        ->assertOk()
        ->assertSee('第2版')
        ->assertSee('訂正 一郎')
        ->assertDontSee('合格 一郎');
});

test('entry stage exemptions follow the one consecutive retry rule and block earlier stage scores', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $event2026 = ProTestEvent::query()->create([
        'year' => 2026,
        'name' => '2026年度プロテスト',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event2026), [
        'candidate_rows' => 'OLD001,男子,前年 不合格,ゼンネン フゴウカク,東京都,右',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('pro_tests.stage_results.import', $event2026), [
        'stage_result_rows' => 'OLD001,第2次,不合格,翌年度免除対象',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $event2027 = ProTestEvent::query()->create([
        'year' => 2027,
        'name' => '2027年度プロテスト',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event2027), [
        'candidate_rows' => 'BADF01,女子,性別 不一致,セイベツ フイッチ,東京都,右,第2次,前年第2次不合格,OLD001,',
    ])->assertRedirect()->assertSessionHasErrors('candidate_rows');
    expect($event2027->candidates()->count())->toBe(0);

    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event2027), [
        'candidate_rows' => 'BAD001,男子,根拠 なし,コンキョ ナシ,大阪府,右,第2次,アマチュア好成績,,,',
    ])->assertRedirect()->assertSessionHasErrors('candidate_rows');
    expect($event2027->candidates()->count())->toBe(0);

    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event2027), [
        'candidate_rows' => implode("\n", [
            'NEW001,男子,再受験 太郎,サイジュケン タロウ,東京都,右,第2次,前年第2次不合格,OLD001,',
            'NEW002,男子,承認 次郎,ショウニン ジロウ,大阪府,右,第2次,アマチュア好成績,,協会承認済み',
            'NEW003,男子,特例 三郎,トクレイ サブロウ,愛知県,右,第3次,プロ公式戦アマ優勝,,大会優勝特例',
        ]),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $retry = ProTestCandidate::query()->where('exam_number', 'NEW001')->sole();
    $approved = ProTestCandidate::query()->where('exam_number', 'NEW002')->sole();
    $champion = ProTestCandidate::query()->where('exam_number', 'NEW003')->sole();
    expect($retry->entry_stage)->toBe('second')
        ->and($retry->previousCandidate->exam_number)->toBe('OLD001')
        ->and($retry->stageResults()->where('stage_code', 'first')->value('result'))->toBe('exempt')
        ->and($approved->stageResults()->where('stage_code', 'first')->value('result'))->toBe('exempt')
        ->and($champion->stageResults()->where('stage_code', 'first')->value('result'))->toBe('exempt')
        ->and($champion->stageResults()->where('stage_code', 'second')->value('result'))->toBe('exempt');

    $firstSession = $event2027->sessions()->create([
        'gender' => 'M',
        'stage_code' => 'first',
        'stage_label' => '第1次テスト',
        'day_number' => 1,
        'game_start' => 1,
        'game_end' => 1,
        'sort_order' => 10,
    ]);
    $secondSession = $event2027->sessions()->create([
        'gender' => 'M',
        'stage_code' => 'second',
        'stage_label' => '第2次テスト',
        'day_number' => 1,
        'game_start' => 1,
        'game_end' => 2,
        'pass_average' => 200,
        'is_stage_final' => true,
        'sort_order' => 20,
    ]);

    $this->actingAs($admin)->post(route('pro_tests.sessions.scores.import', [$event2027, $firstSession]), [
        'score_rows' => 'NEW001,210',
    ])->assertRedirect()->assertSessionHasErrors('score_rows');
    expect(ProTestScore::query()->where('pro_test_candidate_id', $retry->id)->count())->toBe(0);

    $this->actingAs($admin)->post(route('pro_tests.sessions.scores.import', [$event2027, $secondSession]), [
        'score_rows' => "NEW001,210,220\nNEW002,205",
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('pro_tests.sessions.publish', [$event2027, $secondSession]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($retry->stageResults()->where('stage_code', 'second')->value('result'))->toBe('passed');
    expect($approved->stageResults()->where('stage_code', 'second')->exists())->toBeFalse()
        ->and($secondSession->publications()->first()->rows()->where('exam_number', 'NEW002')->value('result_label'))->toBeNull();
    $this->get(route('public.pro_tests.sessions.show', [$event2027, $secondSession]))
        ->assertOk()
        ->assertDontSee('アマチュア好成績')
        ->assertDontSee('前年第2次不合格');

    $this->actingAs($admin)->post(route('pro_tests.stage_results.import', $event2027), [
        'stage_result_rows' => 'NEW001,第2次,不合格,最終判定',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $event2028 = ProTestEvent::query()->create([
        'year' => 2028,
        'name' => '2028年度プロテスト',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    $this->actingAs($admin)->post(route('pro_tests.candidates.import', $event2028), [
        'candidate_rows' => 'LAST001,男子,再々受験 太郎,サイサイジュケン タロウ,東京都,右,第2次,前年第2次不合格,NEW001,',
    ])->assertRedirect()->assertSessionHasErrors('candidate_rows');

    expect($event2028->candidates()->count())->toBe(0);
});
