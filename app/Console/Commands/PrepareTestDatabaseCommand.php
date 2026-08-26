<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PrepareTestDatabaseCommand extends Command
{
    private const ADMIN_CONNECTION = 'jpba_test_database_admin';

    protected $signature = 'jpba:test-database-prepare
        {--database=jpba_test : Dedicated PostgreSQL database name ending in _test}';

    protected $description = 'Create the isolated PostgreSQL test database when it does not exist.';

    public function handle(): int
    {
        $database = trim((string) $this->option('database'));
        if (! preg_match('/\A[a-z][a-z0-9_]*_test\z/i', $database)) {
            $this->error('Safety stop: the test database name must contain only letters, digits, underscores and end in _test.');

            return self::FAILURE;
        }

        $pgsql = config('database.connections.pgsql');
        if (! is_array($pgsql) || ! extension_loaded('pdo_pgsql')) {
            $this->error('The PostgreSQL connection or pdo_pgsql extension is unavailable.');

            return self::FAILURE;
        }

        config([
            'database.connections.'.self::ADMIN_CONNECTION => array_replace($pgsql, [
                'url' => null,
                'database' => 'postgres',
            ]),
        ]);
        DB::purge(self::ADMIN_CONNECTION);

        try {
            $connection = DB::connection(self::ADMIN_CONNECTION);
            $exists = $connection->selectOne(
                'SELECT 1 AS found FROM pg_database WHERE datname = ?',
                [$database],
            );

            if ($exists !== null) {
                $this->info("Test database already exists: {$database}");

                return self::SUCCESS;
            }

            $quotedDatabase = '"'.str_replace('"', '""', $database).'"';
            $connection->unprepared(
                "CREATE DATABASE {$quotedDatabase} WITH ENCODING 'UTF8' TEMPLATE template0",
            );
            $this->info("Test database created: {$database}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Test database preparation failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::disconnect(self::ADMIN_CONNECTION);
            DB::purge(self::ADMIN_CONNECTION);
        }
    }
}
