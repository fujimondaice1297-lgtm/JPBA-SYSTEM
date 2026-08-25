<?php

return [
    'root' => env('JPBA_BACKUP_ROOT', storage_path('backups/automated')),
    'restore_root' => env('JPBA_BACKUP_RESTORE_ROOT', storage_path('backups/restore-tests')),
    'key_path' => env('JPBA_BACKUP_KEY_PATH', storage_path('jpba-backup.key')),
    'retention_generations' => (int) env('JPBA_BACKUP_RETENTION_GENERATIONS', 14),
    'restore_database_prefix' => env('JPBA_BACKUP_RESTORE_DB_PREFIX', 'jpba_restore_'),
    'binaries' => [
        'pg_dump' => env('JPBA_PG_DUMP_BINARY', 'pg_dump'),
        'pg_restore' => env('JPBA_PG_RESTORE_BINARY', 'pg_restore'),
        'createdb' => env('JPBA_CREATEDB_BINARY', 'createdb'),
        'dropdb' => env('JPBA_DROPDB_BINARY', 'dropdb'),
        'psql' => env('JPBA_PSQL_BINARY', 'psql'),
    ],
];
