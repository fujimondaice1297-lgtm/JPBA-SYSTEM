<?php

test('phpunit always uses the isolated postgres test database', function () {
    $basePath = dirname(__DIR__, 2);
    $phpunit = file_get_contents($basePath.'/phpunit.xml');
    $composer = file_get_contents($basePath.'/composer.json');

    expect($phpunit)
        ->toContain('name="DB_CONNECTION" value="pgsql" force="true"')
        ->toContain('name="DB_DATABASE" value="jpba_test" force="true"')
        ->toContain('name="DB_URL" value="" force="true"');
    expect($composer)
        ->toContain('@php artisan optimize:clear --ansi')
        ->toContain('jpba:test-database-prepare --database=jpba_test')
        ->toContain('@php artisan test');
});

test('test database preparation is create only and safety guarded', function () {
    $basePath = dirname(__DIR__, 2);
    $command = file_get_contents(
        $basePath.'/app/Console/Commands/PrepareTestDatabaseCommand.php',
    );

    expect($command)
        ->toContain('_test\\z/i')
        ->toContain('CREATE DATABASE')
        ->not->toContain('DROP DATABASE');
});

test('laravel test bootstrap refuses cached production or non test database configuration', function () {
    $basePath = dirname(__DIR__, 2);
    $testCase = file_get_contents($basePath.'/tests/TestCase.php');

    expect($testCase)
        ->toContain("environment('testing')")
        ->toContain("preg_match('/_test\\z/i', \$database)")
        ->toContain('automated tests may only run in APP_ENV=testing on a database ending in _test');
});

test('continuous integration runs dependency audits postgres tests blade build and pdf regression', function () {
    $basePath = dirname(__DIR__, 2);
    $workflow = file_get_contents($basePath.'/.github/workflows/tests.yml');

    expect($workflow)
        ->toContain('image: postgres:18')
        ->toContain('composer audit --locked --no-interaction')
        ->toContain('npm audit --omit=dev')
        ->toContain('run: npm run build')
        ->toContain('run: composer test')
        ->toContain('run: php artisan view:cache')
        ->toContain('run: php artisan tournament:pdf-regression');
});

test('production environment template keeps secure release gates explicit', function () {
    $basePath = dirname(__DIR__, 2);
    $environment = file_get_contents($basePath.'/.env.production.example');

    expect($environment)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('APP_TIMEZONE=Asia/Tokyo')
        ->toContain('APP_LOCALE=ja')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('MAIL_MAILER=smtp')
        ->toContain('JPBA_ACHIEVEMENT_CUTOVER_DATE=')
        ->toContain('JPBA_BACKUP_KEY_PATH=');
});

test('fresh migration guards both historical user license columns', function () {
    $basePath = dirname(__DIR__, 2);
    $migration = file_get_contents(
        $basePath.'/database/migrations/2025_09_02_000051_backfill_users_pro_bowler_id_from_license_columns.php',
    );

    expect($migration)
        ->toContain("Schema::hasColumn('users', 'pro_bowler_license_no')")
        ->toContain("Schema::hasColumn('users', 'license_no')");
});
