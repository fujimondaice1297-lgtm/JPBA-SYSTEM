<?php

use App\Services\JpbaOfficialAchievementTopicService;

it('extracts official achievement evidence without confusing a tournament ordinal for the certification number', function () {
    $html = <<<'HTML'
    <html><body>
    <div>2026/07/10</div>
    <h3>「テストプロボウリングトーナメント」大会初日</h3>
    <p>予選Aシフト3G目にて山田太郎(60期 No.1431 テストセンター)が大会第1号、
    自身5回目となる公認パーフェクトゲーム、JPBA公認男子 第1797号を達成しました。</p>
    </body></html>
    HTML;

    $records = app(JpbaOfficialAchievementTopicService::class)
        ->parse($html, 'https://www.jpba.or.jp/topics/2026/07.html');

    expect($records)->toHaveCount(1)
        ->and($records[0]['record_type'])->toBe('perfect')
        ->and($records[0]['license_number'])->toBe(1431)
        ->and($records[0]['certification_number_value'])->toBe(1797)
        ->and($records[0]['awarded_on'])->toBe('2026-07-10')
        ->and($records[0]['tournament_name'])->toBe('テストプロボウリングトーナメント')
        ->and($records[0]['game_numbers'])->toContain('3G目');
});

it('extracts seven ten frame details and legacy certification wording', function () {
    $html = <<<'HTML'
    <html><body>
    <div>2019/11/03</div>
    <h3>「テスト大会」7-10スプリットメイド達成</h3>
    <p>マスターズ準決勝5G目8フレーム、山田太郎 (52期 No.1288 テスト)が
    JPBA公認 第158号 7-10スプリットメイドを達成しました。</p>
    </body></html>
    HTML;

    $records = app(JpbaOfficialAchievementTopicService::class)
        ->parse($html, 'https://www.jpba.or.jp/topics/2019/topics11.html');

    expect($records)->toHaveCount(1)
        ->and($records[0]['record_type'])->toBe('seven_ten')
        ->and($records[0]['certification_number_value'])->toBe(158)
        ->and($records[0]['frame_number'])->toContain('8フレーム')
        ->and($records[0]['game_numbers'])->toContain('5G目');
});

it('does not mix certification numbers between several achievements on the same day', function () {
    $html = <<<'HTML'
    <html><body>
    <div>2026/05/24</div>
    <h3>「『テスト杯』第13回プロアマトーナメント」大会2日目</h3>
    <p>準決勝5G目にて倉持悠人(63期 No.1478 テスト)が公認パーフェクトゲーム、
    JPBA公認男子 第1787号を達成しました。</p>
    <p>準決勝前半3Gにて濱﨑りりあ(55期 No.637 テスト)が公認800シリーズ、
    JPBA公認女子 第45号を達成しました。</p>
    <p>同じく準決勝前半3Gにて藤永北斗(61期 No.1443 テスト)が公認800シリーズ、
    JPBA公認男子 第344号を達成しました。</p>
    </body></html>
    HTML;

    $records = collect(
        app(JpbaOfficialAchievementTopicService::class)
            ->parse($html, 'https://www.jpba.or.jp/topics/2026/05.html')
    );

    expect($records)->toHaveCount(3)
        ->and($records->pluck('certification_number_value')->sort()->values()->all())
        ->toBe([45, 344, 1787])
        ->and($records->pluck('license_number')->sort()->values()->all())
        ->toBe([637, 1443, 1478])
        ->and($records->first()['tournament_name'])
        ->toBe('『テスト杯』第13回プロアマトーナメント');
});
