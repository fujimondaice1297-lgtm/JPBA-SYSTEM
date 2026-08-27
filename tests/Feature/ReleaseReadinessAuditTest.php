<?php

use Illuminate\Support\Facades\Artisan;

test('release readiness preparation audit passes core checks without changing data', function () {
    $exitCode = Artisan::call('jpba:release-readiness', ['--json' => true]);
    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($result['mode'])->toBe('preparation')
        ->and($result['summary']['ng'])->toBe(0)
        ->and(collect($result['checks'])->firstWhere('label', '未適用migration')['detail'])->toBe('0件')
        ->and(collect($result['checks'])->firstWhere('label', '一般公開の旧JPBAサイト依存')['detail'])->toBe('0件');
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
