<?php

use App\Services\JpbaOfficialAchievementTopicService;

it('expands two explicitly paired JPBA certification numbers', function () {
    $html = <<<'HTML'
    <html><body>
    <div>2018/07/20</div>
    <h3>「中日杯2018東海オープン」森本健太、大会第1号＆第3号パーフェクト達成！</h3>
    <p>予選Aシフト1G目に森本健太が大会第1号パーフェクトを、さらに3G目に第3号パーフェクトを達成。</p>
    <p>自身初(JPBA公認1461号＆1462号)の記録達成となりました。</p>
    <p>森本健太 (51期 No.1267 テスト)</p>
    </body></html>
    HTML;

    $records = collect(
        app(JpbaOfficialAchievementTopicService::class)
            ->parse($html, 'https://www.jpba.or.jp/topics/2018/topics07.html')
    )->whereIn('certification_number_value', [1461, 1462]);

    expect($records)->toHaveCount(2)
        ->and($records->pluck('certification_number_value')->sort()->values()->all())
        ->toBe([1461, 1462])
        ->and($records->pluck('license_number')->unique()->values()->all())
        ->toBe([1267])
        ->and($records->sortBy('certification_number_value')->pluck('game_numbers')->values()->all())
        ->toBe(['予選Aシフト1G目', '3G目']);
});

it('does not borrow a game position from the next achievement heading', function () {
    $html = <<<'HTML'
    <html><body>
    <div>2023/11/05</div>
    <h3>小原照之 公認パーフェクトゲーム達成！</h3>
    <p>男子ダブルエリミネーション3位決定戦にて小原照之(32期 No.761)がJPBA公認第1680号を達成しました。</p>
    <h3>松永裕美 公認パーフェクトゲーム達成！</h3>
    <p>「第45回STORMジャパンオープンボウリング選手権」女子2回戦2G目にて松永裕美(37期 No.384)がJPBA公認第340号を達成しました。</p>
    </body></html>
    HTML;

    $records = collect(
        app(JpbaOfficialAchievementTopicService::class)
            ->parse($html, 'https://www.jpba.or.jp/topics/2023/11.html')
    )->keyBy('certification_number_value');

    expect($records)->toHaveCount(2)
        ->and($records[1680]['game_numbers'])->toBeNull()
        ->and($records[340]['game_numbers'])->toContain('2G目')
        ->and($records[1680]['tournament_name'])
        ->toBe('第45回STORMジャパンオープンボウリング選手権');
});
