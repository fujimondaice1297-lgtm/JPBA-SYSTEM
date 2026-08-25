<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class JpbaBackupService
{
    private const MANIFEST = 'manifest.json';

    private const DATABASE_ARCHIVE = 'database.aes256.zip';

    private const PUBLIC_ARCHIVE = 'storage-public.aes256.zip';

    private const PRIVATE_ARCHIVE = 'storage-private.aes256.zip';

    public function plan(): array
    {
        $public = $this->inventory(storage_path('app/public'));
        $private = $this->inventory(storage_path('app/private'));
        $profiles = $this->inventory(storage_path('app/public/profiles'));

        return [
            'database' => (string) config('database.connections.pgsql.database'),
            'backup_root' => $this->backupRoot(),
            'retention_generations' => $this->retentionGenerations(),
            'encryption' => 'ZIP AES-256（外側には汎用payload名だけを保存）',
            'public_storage' => $public,
            'private_storage' => $private,
            'profile_photos' => $profiles,
        ];
    }

    public function create(bool $initializeKey = false): array
    {
        $this->assertZipEncryptionAvailable();
        $password = $this->readKey($initializeKey);
        $root = $this->backupRoot();
        $this->assertBackupRootIsSafe($root);
        File::ensureDirectoryExists($root);

        $createdAt = Carbon::now('Asia/Tokyo');
        $generationName = 'backup_'.$createdAt->format('Ymd_His');
        $generationPath = $root.DIRECTORY_SEPARATOR.$generationName;

        if (file_exists($generationPath)) {
            throw new RuntimeException('同じ名前のバックアップ世代が既に存在します: '.$generationPath);
        }

        File::ensureDirectoryExists($generationPath);

        try {
            $database = $this->createDatabaseArchive($generationPath, $password);
            $public = $this->createDirectoryArchive(
                storage_path('app/public'),
                $generationPath,
                self::PUBLIC_ARCHIVE,
                $password
            );
            $private = $this->createDirectoryArchive(
                storage_path('app/private'),
                $generationPath,
                self::PRIVATE_ARCHIVE,
                $password
            );
            $profiles = $this->inventory(storage_path('app/public/profiles'));

            $manifest = [
                'format_version' => 1,
                'generation' => $generationName,
                'created_at' => $createdAt->toIso8601String(),
                'git_commit' => $this->gitCommit(),
                'database' => [
                    'driver' => config('database.default'),
                    'name' => config('database.connections.pgsql.database'),
                    'counts' => $this->databaseCounts(),
                ],
                'encryption' => [
                    'method' => 'ZIP AES-256',
                    'filenames_hidden_in_encrypted_payload' => true,
                    'key_embedded' => false,
                ],
                'retention_generations' => $this->retentionGenerations(),
                'components' => [
                    'database' => $database,
                    'public_storage' => $public,
                    'private_storage' => $private,
                    'profile_photos' => $profiles,
                ],
            ];

            $manifestPath = $generationPath.DIRECTORY_SEPARATOR.self::MANIFEST;
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($manifestPath, $json.PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('manifest.json を保存できませんでした。');
            }

            $removed = $this->rotateGenerations($generationPath);

            return [
                'path' => $generationPath,
                'manifest' => $manifest,
                'rotated_generations' => $removed,
            ];
        } catch (Throwable $e) {
            $this->removeDirectorySafely($generationPath, $root);

            throw $e;
        }
    }

    public function verify(?string $path = null): array
    {
        $password = $this->readKey(false);
        $generationPath = $this->resolveGenerationPath($path);
        $manifest = $this->readManifest($generationPath);
        $checks = [];

        foreach (['database', 'public_storage', 'private_storage'] as $componentName) {
            $component = $manifest['components'][$componentName] ?? null;
            if (! is_array($component)) {
                throw new RuntimeException("manifestの{$componentName}定義がありません。");
            }

            $archivePath = $generationPath.DIRECTORY_SEPARATOR.$component['archive'];
            $actualHash = is_file($archivePath) ? hash_file('sha256', $archivePath) : null;
            $hashMatches = is_string($actualHash)
                && hash_equals((string) $component['sha256'], $actualHash);
            $passwordReadable = $hashMatches
                && $this->encryptedEntryIsReadable($archivePath, (string) $component['entry'], $password);
            $wrongKeyRejected = $hashMatches
                && ! $this->encryptedEntryIsReadable(
                    $archivePath,
                    (string) $component['entry'],
                    hash('sha256', $password.'-wrong-key-check')
                );

            $checks[$componentName] = [
                'archive_exists' => is_file($archivePath),
                'sha256_matches' => $hashMatches,
                'password_readable' => $passwordReadable,
                'wrong_key_rejected' => $wrongKeyRejected,
            ];
        }

        $ok = collect($checks)->every(
            fn (array $check) => ! in_array(false, $check, true)
        );

        return [
            'ok' => $ok,
            'path' => $generationPath,
            'generation' => $manifest['generation'] ?? basename($generationPath),
            'checks' => $checks,
            'manifest' => $manifest,
        ];
    }

    public function restoreTest(?string $path = null, bool $keep = false): array
    {
        $verification = $this->verify($path);
        if (! $verification['ok']) {
            throw new RuntimeException('バックアップ整合性検査に失敗したため復元しません。');
        }

        $password = $this->readKey(false);
        $generationPath = $verification['path'];
        $manifest = $verification['manifest'];
        $restoreRoot = $this->restoreRoot();
        File::ensureDirectoryExists($restoreRoot);

        $suffix = Carbon::now('Asia/Tokyo')->format('Ymd_His');
        $targetRoot = $restoreRoot.DIRECTORY_SEPARATOR.'restore_'.$suffix;
        $databaseName = $this->restoreDatabasePrefix().$suffix;
        $this->assertRestoreDatabaseNameIsSafe($databaseName);
        File::ensureDirectoryExists($targetRoot);

        $databaseCreated = false;

        try {
            $databaseDump = $targetRoot.DIRECTORY_SEPARATOR.'database.dump';
            $this->extractEncryptedEntry(
                $generationPath.DIRECTORY_SEPARATOR.$manifest['components']['database']['archive'],
                (string) $manifest['components']['database']['entry'],
                $databaseDump,
                $password
            );

            $publicTarget = $targetRoot.DIRECTORY_SEPARATOR.'public';
            $privateTarget = $targetRoot.DIRECTORY_SEPARATOR.'private';
            $this->restoreDirectoryComponent(
                $generationPath,
                $manifest['components']['public_storage'],
                $publicTarget,
                $password,
                $targetRoot
            );
            $this->restoreDirectoryComponent(
                $generationPath,
                $manifest['components']['private_storage'],
                $privateTarget,
                $password,
                $targetRoot
            );

            $this->runPostgresProcess('createdb', [
                '--host', $this->databaseConfig('host'),
                '--port', (string) $this->databaseConfig('port'),
                '--username', $this->databaseConfig('username'),
                $databaseName,
            ]);
            $databaseCreated = true;

            $this->runPostgresProcess('pg_restore', [
                '--exit-on-error',
                '--no-owner',
                '--no-acl',
                '--host', $this->databaseConfig('host'),
                '--port', (string) $this->databaseConfig('port'),
                '--username', $this->databaseConfig('username'),
                '--dbname', $databaseName,
                $databaseDump,
            ], 1800);

            $restoredDatabaseCounts = $this->restoredDatabaseCounts(
                $databaseName,
                array_keys((array) ($manifest['database']['counts'] ?? []))
            );
            $publicInventory = $this->inventory($publicTarget);
            $privateInventory = $this->inventory($privateTarget);
            $profileInventory = $this->inventory($publicTarget.DIRECTORY_SEPARATOR.'profiles');
            $samplePhoto = $this->firstImage($publicTarget.DIRECTORY_SEPARATOR.'profiles');

            $result = [
                'ok' => $restoredDatabaseCounts === ($manifest['database']['counts'] ?? [])
                    && $publicInventory === $this->inventoryFromManifest($manifest, 'public_storage')
                    && $privateInventory === $this->inventoryFromManifest($manifest, 'private_storage')
                    && $profileInventory === ($manifest['components']['profile_photos'] ?? [])
                    && $samplePhoto['valid_image'],
                'source_generation' => $manifest['generation'],
                'database' => [
                    'target' => $databaseName,
                    'counts' => $restoredDatabaseCounts,
                    'counts_match' => $restoredDatabaseCounts === ($manifest['database']['counts'] ?? []),
                ],
                'files' => [
                    'target_root' => $targetRoot,
                    'public' => $publicInventory,
                    'private' => $privateInventory,
                    'profile_photos' => $profileInventory,
                    'sample_photo' => $samplePhoto,
                ],
                'kept' => $keep,
            ];

            if (! $result['ok']) {
                throw new RuntimeException('復元後の件数または選手写真検査がmanifestと一致しません。');
            }

            return $result;
        } finally {
            if ($databaseCreated && ! $keep) {
                $this->dropRestoreDatabase($databaseName);
            }

            if (! $keep && is_dir($targetRoot)) {
                $this->removeDirectorySafely($targetRoot, $restoreRoot);
            }
        }
    }

    private function createDatabaseArchive(string $generationPath, string $password): array
    {
        $dumpPath = $generationPath.DIRECTORY_SEPARATOR.'.database.dump.tmp';
        $archivePath = $generationPath.DIRECTORY_SEPARATOR.self::DATABASE_ARCHIVE;

        try {
            $this->runPostgresProcess('pg_dump', [
                '--format=custom',
                '--no-owner',
                '--no-acl',
                '--host', $this->databaseConfig('host'),
                '--port', (string) $this->databaseConfig('port'),
                '--username', $this->databaseConfig('username'),
                '--file', $dumpPath,
                $this->databaseConfig('database'),
            ], 900);

            $this->createEncryptedSingleFileArchive($dumpPath, $archivePath, 'database.dump', $password);

            return $this->archiveManifest($archivePath, 'database.dump', [
                'source_bytes' => filesize($dumpPath),
            ]);
        } finally {
            if (is_file($dumpPath)) {
                unlink($dumpPath);
            }
        }
    }

    private function createDirectoryArchive(
        string $source,
        string $generationPath,
        string $archiveName,
        string $password
    ): array {
        $payloadPath = $generationPath.DIRECTORY_SEPARATOR.'.'.pathinfo($archiveName, PATHINFO_FILENAME).'.payload.zip';
        $archivePath = $generationPath.DIRECTORY_SEPARATOR.$archiveName;
        $inventory = $this->inventory($source);

        try {
            $this->createPayloadZip($source, $payloadPath);
            $this->createEncryptedSingleFileArchive($payloadPath, $archivePath, 'payload.zip', $password);

            return $this->archiveManifest($archivePath, 'payload.zip', $inventory);
        } finally {
            if (is_file($payloadPath)) {
                unlink($payloadPath);
            }
        }
    }

    private function createPayloadZip(string $source, string $destination): void
    {
        $zip = new ZipArchive;
        $status = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new RuntimeException('一時ZIPを作成できません: '.$destination.' ('.$status.')');
        }

        try {
            if (! is_dir($source)) {
                return;
            }

            $baseLength = strlen(rtrim($source, '\\/').DIRECTORY_SEPARATOR);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $path = $file->getPathname();
                $relative = str_replace('\\', '/', substr($path, $baseLength));
                if (! $zip->addFile($path, $relative)) {
                    throw new RuntimeException('ZIPへ追加できません: '.$path);
                }
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('一時ZIPを確定できません: '.$destination);
            }
        }
    }

    private function createEncryptedSingleFileArchive(
        string $source,
        string $destination,
        string $entryName,
        string $password
    ): void {
        $zip = new ZipArchive;
        $status = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new RuntimeException('暗号化ZIPを作成できません: '.$destination.' ('.$status.')');
        }

        try {
            $zip->setPassword($password);
            if (! $zip->addFile($source, $entryName)) {
                throw new RuntimeException('暗号化ZIPへpayloadを追加できません。');
            }
            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
            if (! $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256, $password)) {
                throw new RuntimeException('ZIP AES-256暗号化を設定できません。');
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('暗号化ZIPを確定できません: '.$destination);
            }
        }

        if (! $this->encryptedEntryIsReadable($destination, $entryName, $password)) {
            throw new RuntimeException('作成した暗号化ZIPを鍵で読み取れません: '.$destination);
        }
    }

    private function encryptedEntryIsReadable(string $archive, string $entryName, string $password): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            return false;
        }

        try {
            $zip->setPassword($password);
            $stream = $zip->getStream($entryName);
            if (! is_resource($stream)) {
                return false;
            }

            $chunk = fread($stream, 1);
            fclose($stream);

            return $chunk !== false;
        } finally {
            $zip->close();
        }
    }

    private function restoreDirectoryComponent(
        string $generationPath,
        array $component,
        string $target,
        string $password,
        string $temporaryRoot
    ): void {
        File::ensureDirectoryExists($target);
        $payloadPath = $temporaryRoot.DIRECTORY_SEPARATOR.'.'.basename($target).'.payload.zip';

        try {
            $this->extractEncryptedEntry(
                $generationPath.DIRECTORY_SEPARATOR.$component['archive'],
                (string) $component['entry'],
                $payloadPath,
                $password
            );
            $this->extractPayloadZip($payloadPath, $target);
        } finally {
            if (is_file($payloadPath)) {
                unlink($payloadPath);
            }
        }
    }

    private function extractEncryptedEntry(
        string $archive,
        string $entryName,
        string $destination,
        string $password
    ): void {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('暗号化ZIPを開けません: '.$archive);
        }

        $output = null;
        $input = null;

        try {
            $zip->setPassword($password);
            $input = $zip->getStream($entryName);
            if (! is_resource($input)) {
                throw new RuntimeException('暗号化ZIPのpayloadを開けません。鍵を確認してください。');
            }

            $output = fopen($destination, 'wb');
            if (! is_resource($output)) {
                throw new RuntimeException('復元ファイルを作成できません: '.$destination);
            }

            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('暗号化ZIPの展開に失敗しました。');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            $zip->close();
        }
    }

    private function extractPayloadZip(string $archive, string $target): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('復元payload ZIPを開けません。');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                $normalized = str_replace('\\', '/', $name);
                if ($normalized === ''
                    || str_starts_with($normalized, '/')
                    || preg_match('/^[A-Za-z]:\//', $normalized)
                    || in_array('..', explode('/', $normalized), true)) {
                    throw new RuntimeException('安全でないZIP内パスを検出しました: '.$name);
                }
            }

            if (! $zip->extractTo($target)) {
                throw new RuntimeException('payload ZIPの展開に失敗しました。');
            }
        } finally {
            $zip->close();
        }
    }

    private function restoredDatabaseCounts(string $database, array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            if (! preg_match('/^[a-z0-9_]+$/', (string) $table)) {
                throw new RuntimeException('不正なテーブル名です: '.$table);
            }

            $process = $this->runPostgresProcess('psql', [
                '--no-psqlrc',
                '--tuples-only',
                '--no-align',
                '--host', $this->databaseConfig('host'),
                '--port', (string) $this->databaseConfig('port'),
                '--username', $this->databaseConfig('username'),
                '--dbname', $database,
                '--command', 'select count(*) from '.$table,
            ]);
            $counts[$table] = (int) trim($process->getOutput());
        }

        return $counts;
    }

    private function dropRestoreDatabase(string $database): void
    {
        $this->assertRestoreDatabaseNameIsSafe($database);
        $this->runPostgresProcess('dropdb', [
            '--if-exists',
            '--host', $this->databaseConfig('host'),
            '--port', (string) $this->databaseConfig('port'),
            '--username', $this->databaseConfig('username'),
            $database,
        ]);
    }

    private function runPostgresProcess(string $binaryKey, array $arguments, int $timeout = 300): Process
    {
        $binary = (string) config('jpba_backup.binaries.'.$binaryKey, $binaryKey);
        $process = new Process(
            array_merge([$binary], $arguments),
            base_path(),
            ['PGPASSWORD' => (string) $this->databaseConfig('password')]
        );
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                $binaryKey.' に失敗しました: '.trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        return $process;
    }

    private function databaseCounts(): array
    {
        $tables = [
            'pro_bowlers',
            'tournaments',
            'approved_balls',
            'game_scores',
            'tournament_results',
            'users',
        ];
        $counts = [];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    private function inventory(string $path): array
    {
        if (! is_dir($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $files++;
                $bytes += $file->getSize();
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    private function firstImage(string $path): array
    {
        if (! is_dir($path)) {
            return ['relative_path' => null, 'valid_image' => false];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $image = @getimagesize($file->getPathname());
            if (is_array($image)) {
                return [
                    'relative_path' => str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($path, '\\/')) + 1)),
                    'width' => $image[0],
                    'height' => $image[1],
                    'mime' => $image['mime'] ?? null,
                    'valid_image' => true,
                ];
            }
        }

        return ['relative_path' => null, 'valid_image' => false];
    }

    private function inventoryFromManifest(array $manifest, string $component): array
    {
        return [
            'files' => (int) ($manifest['components'][$component]['files'] ?? -1),
            'bytes' => (int) ($manifest['components'][$component]['bytes'] ?? -1),
        ];
    }

    private function archiveManifest(string $path, string $entry, array $extra = []): array
    {
        return array_merge([
            'archive' => basename($path),
            'entry' => $entry,
            'archive_bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ], $extra);
    }

    private function readManifest(string $generationPath): array
    {
        $path = $generationPath.DIRECTORY_SEPARATOR.self::MANIFEST;
        if (! is_file($path)) {
            throw new RuntimeException('manifest.json がありません: '.$generationPath);
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function resolveGenerationPath(?string $path): string
    {
        if ($path) {
            $resolved = realpath($path);
            if ($resolved === false || ! is_dir($resolved)) {
                throw new RuntimeException('バックアップ世代フォルダが見つかりません: '.$path);
            }

            return $resolved;
        }

        $candidates = array_values(array_filter(
            glob($this->backupRoot().DIRECTORY_SEPARATOR.'backup_*') ?: [],
            fn (string $candidate) => is_file($candidate.DIRECTORY_SEPARATOR.self::MANIFEST)
        ));
        rsort($candidates, SORT_STRING);

        if ($candidates === []) {
            throw new RuntimeException('検証できる自動バックアップ世代がありません。');
        }

        return $candidates[0];
    }

    private function readKey(bool $initialize): string
    {
        $path = $this->keyPath();

        if (! is_file($path)) {
            if (! $initialize) {
                throw new RuntimeException('バックアップ鍵がありません。初回だけ --initialize-key を指定してください。');
            }

            File::ensureDirectoryExists(dirname($path));
            $key = base64_encode(random_bytes(32));
            if (file_put_contents($path, $key.PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('バックアップ鍵を作成できません: '.$path);
            }
            @chmod($path, 0600);
        }

        $key = trim((string) file_get_contents($path));
        if (strlen($key) < 32) {
            throw new RuntimeException('バックアップ鍵が短すぎます。32文字以上必要です。');
        }

        return $key;
    }

    private function rotateGenerations(string $currentPath): array
    {
        $root = $this->backupRoot();
        $candidates = array_values(array_filter(
            glob($root.DIRECTORY_SEPARATOR.'backup_*') ?: [],
            fn (string $candidate) => is_dir($candidate)
                && is_file($candidate.DIRECTORY_SEPARATOR.self::MANIFEST)
        ));
        rsort($candidates, SORT_STRING);

        $removed = [];
        foreach (array_slice($candidates, $this->retentionGenerations()) as $candidate) {
            if ($this->samePath($candidate, $currentPath)) {
                continue;
            }

            $this->removeDirectorySafely($candidate, $root);
            $removed[] = basename($candidate);
        }

        return $removed;
    }

    private function removeDirectorySafely(string $path, string $allowedRoot): void
    {
        $realPath = realpath($path);
        $realRoot = realpath($allowedRoot);

        if ($realPath === false) {
            return;
        }
        if ($realRoot === false
            || $this->samePath($realPath, $realRoot)
            || ! $this->isWithin($realPath, $realRoot)) {
            throw new RuntimeException('安全範囲外のフォルダ削除を拒否しました: '.$path);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($realPath);
    }

    private function assertBackupRootIsSafe(string $root): void
    {
        $normalized = $this->normalizePath($root);
        if ($normalized === '' || in_array($normalized, ['/', 'c:/', 'c:'], true)) {
            throw new RuntimeException('バックアップ保存先が広すぎるため拒否しました。');
        }

        foreach ([storage_path('app/public'), storage_path('app/private')] as $source) {
            if ($this->samePath($root, $source) || $this->isWithin($root, $source)) {
                throw new RuntimeException('バックアップ保存先をバックアップ対象内には設定できません。');
            }
        }
    }

    private function assertRestoreDatabaseNameIsSafe(string $database): void
    {
        $prefix = $this->restoreDatabasePrefix();
        $current = (string) $this->databaseConfig('database');

        if (! preg_match('/^[a-z][a-z0-9_]{2,62}$/', $database)
            || ! str_starts_with($database, $prefix)
            || $database === $current) {
            throw new RuntimeException('安全でない復元先DB名を拒否しました: '.$database);
        }
    }

    private function assertZipEncryptionAvailable(): void
    {
        if (! class_exists(ZipArchive::class) || ! defined(ZipArchive::class.'::EM_AES_256')) {
            throw new RuntimeException('ZIP AES-256を利用できるPHP zip拡張が必要です。');
        }
    }

    private function gitCommit(): ?string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    private function databaseConfig(string $key): string|int|null
    {
        return config('database.connections.pgsql.'.$key);
    }

    private function backupRoot(): string
    {
        return $this->operatingSystemPath((string) config('jpba_backup.root'));
    }

    private function restoreRoot(): string
    {
        return $this->operatingSystemPath((string) config('jpba_backup.restore_root'));
    }

    private function keyPath(): string
    {
        return $this->operatingSystemPath((string) config('jpba_backup.key_path'));
    }

    private function restoreDatabasePrefix(): string
    {
        return (string) config('jpba_backup.restore_database_prefix', 'jpba_restore_');
    }

    private function retentionGenerations(): int
    {
        return max(2, (int) config('jpba_backup.retention_generations', 14));
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedRoot = rtrim($this->normalizePath($root), '/');

        return str_starts_with($normalizedPath, $normalizedRoot.'/');
    }

    private function samePath(string $left, string $right): bool
    {
        return $this->normalizePath($left) === $this->normalizePath($right);
    }

    private function normalizePath(string $path): string
    {
        return strtolower(str_replace('\\', '/', rtrim($path, '\\/')));
    }

    private function operatingSystemPath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
