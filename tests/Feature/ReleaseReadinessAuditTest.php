<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('release readiness preparation audit passes core checks without changing data', function () {
    $exitCode = Artisan::call('jpba:release-readiness', ['--json' => true]);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($result['mode'])->toBe('preparation')
        ->and($result['summary']['ng'])->toBe(0)
        ->and(collect($result['checks'])->firstWhere('label', '未適用migration')['detail'])->toBe('0件')
        ->and(collect($result['checks'])->firstWhere('label', '一般公開の旧JPBAサイト依存')['detail'])->toBe('0件')
        ->and(collect($result['checks'])->firstWhere('label', '非同期処理・保存テーブル')['status'])->toBe('OK');
});

test('production readiness audit blocks non production configuration', function () {
    $exitCode = Artisan::call('jpba:release-readiness', [
        '--production' => true,
        '--json' => true,
    ]);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($result['mode'])->toBe('production')
        ->and($result['summary']['ng'])->toBeGreaterThan(0)
        ->and(collect($result['checks'])->firstWhere('label', '本番環境')['status'])->toBe('NG')
        ->and(collect($result['checks'])->firstWhere('label', '本番メール送信')['status'])->toBe('NG')
        ->and(collect($result['checks'])->firstWhere('label', '公認記録切替日')['status'])->toBe('NG');
});

test('production readiness rejects a malformed achievement cutover date', function () {
    config()->set('achievements.cutover_date', '2026-02-30');

    Artisan::call('jpba:release-readiness', [
        '--production' => true,
        '--json' => true,
    ]);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($result['checks'])->firstWhere('label', '公認記録切替日'))
        ->toMatchArray(['status' => 'NG', 'detail' => '2026-02-30']);
});

test('production readiness reports a missing database queue table', function () {
    Schema::dropIfExists('failed_jobs');
    config()->set('queue.default', 'database');

    Artisan::call('jpba:release-readiness', [
        '--production' => true,
        '--json' => true,
    ]);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $check = collect($result['checks'])->firstWhere('label', '非同期処理・保存テーブル');

    expect($check['status'])->toBe('NG')
        ->and($check['detail'])->toContain('failed_jobs');
});
