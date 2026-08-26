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

test('continuous integration runs postgres tests blade and pdf regression', function () {
    $basePath = dirname(__DIR__, 2);
    $workflow = file_get_contents($basePath.'/.github/workflows/tests.yml');

    expect($workflow)
        ->toContain('image: postgres:18')
        ->toContain('run: composer test')
        ->toContain('run: php artisan view:cache')
        ->toContain('run: php artisan tournament:pdf-regression');
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
