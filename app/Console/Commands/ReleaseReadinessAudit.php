<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ReleaseReadinessAudit extends Command
{
    protected $signature = 'jpba:release-readiness
        {--production : 本番公開に必要な設定を必須条件として判定する}
        {--json : JSONで結果を出力する}';

    protected $description = 'JPBA新サイトのDB・公開設定・本番環境設定を非破壊で監査する';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $checks = array_merge($this->coreChecks(), $this->environmentChecks($production));

        if ($this->option('json')) {
            $this->line(json_encode([
                'mode' => $production ? 'production' : 'preparation',
                'checks' => $checks,
                'summary' => $this->summary($checks),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['判定', '監査項目', '現在値／結果'],
                array_map(fn (array $check) => [
                    $check['status'],
                    $check['label'],
                    $check['detail'],
                ], $checks)
            );

            $summary = $this->summary($checks);
            $message = "監査結果: OK {$summary['ok']} / WARN {$summary['warn']} / NG {$summary['ng']}";
            $summary['ng'] > 0 ? $this->error($message) : $this->info($message);
        }

        return collect($checks)->contains(fn (array $check) => $check['status'] === 'NG')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @return array<int, array{status: string, label: string, detail: string}> */
    private function coreChecks(): array
    {
        $requiredTables = [
            'users',
            'pro_bowlers',
            'tournaments',
            'tournament_entries',
            'tournament_results',
            'ball_info',
            'used_balls',
            'training_sessions',
            'record_types',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table) => ! Schema::hasTable($table)
        ));

        $pendingMigrations = $this->pendingMigrations();
        $legacyLinks = $this->legacyPublicLinks(config('jpba_public', []));
        $storageWritable = collect([
            storage_path(),
            storage_path('framework'),
            storage_path('logs'),
        ])->every(fn (string $path) => $this->canWriteDirectory($path));

        return [
            $this->check(
                filled(config('app.key')),
                'APP_KEY',
                filled(config('app.key')) ? '設定済み' : '未設定'
            ),
            $this->check(
                $pendingMigrations === 0,
                '未適用migration',
                $pendingMigrations === null ? 'migrationsテーブルなし' : "{$pendingMigrations}件"
            ),
            $this->check(
                $missingTables === [],
                '主要業務テーブル',
                $missingTables === [] ? count($requiredTables).'表を確認' : '不足: '.implode(', ', $missingTables)
            ),
            $this->check(
                $legacyLinks === [],
                '一般公開の旧JPBAサイト依存',
                $legacyLinks === [] ? '0件' : implode(', ', $legacyLinks)
            ),
            $this->check(
                $storageWritable,
                'storage書込権限',
                $storageWritable ? 'framework/logsとも書込可能' : '書込不可の保存先あり'
            ),
        ];
    }

    /** @return array<int, array{status: string, label: string, detail: string}> */
    private function environmentChecks(bool $production): array
    {
        $appUrl = (string) config('app.url');
        $mailHost = (string) config('mail.mailers.smtp.host');
        $pgsql = config('database.connections.pgsql', []);
        $backupKeyPath = (string) config('jpba_backup.key_path');
        $checks = [
            ['本番環境', app()->environment('production'), (string) app()->environment()],
            ['デバッグ無効', config('app.debug') === false, config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false'],
            ['HTTPS URL', str_starts_with($appUrl, 'https://') && ! preg_match('/(?:localhost|127\\.0\\.0\\.1|example\\.)/i', $appUrl), $appUrl],
            ['日本時間', config('app.timezone') === 'Asia/Tokyo', (string) config('app.timezone')],
            ['日本語ロケール', config('app.locale') === 'ja', (string) config('app.locale')],
            ['secure cookie', config('session.secure') === true, var_export(config('session.secure'), true)],
            [
                '本番メール送信',
                ! in_array(config('mail.default'), ['log', 'array'], true)
                    && filled($mailHost)
                    && ! preg_match('/(?:localhost|127\\.0\\.0\\.1|example\\.)/i', $mailHost)
                    && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false,
                (string) config('mail.default').' / '.$mailHost,
            ],
            ['非同期queue', config('queue.default') !== 'sync', (string) config('queue.default')],
            ['永続cache', config('cache.default') !== 'array', (string) config('cache.default')],
            ['永続session', config('session.driver') !== 'array', (string) config('session.driver')],
            ['PostgreSQL', config('database.default') === 'pgsql', (string) config('database.default')],
            [
                'PostgreSQL認証情報',
                filled($pgsql['database'] ?? null)
                    && filled($pgsql['username'] ?? null)
                    && filled($pgsql['password'] ?? null),
                filled($pgsql['password'] ?? null) ? 'DB名・利用者・パスワード設定済み' : 'DBパスワード未設定',
            ],
            [
                'バックアップ暗号鍵',
                $backupKeyPath !== '' && is_file($backupKeyPath) && is_readable($backupKeyPath),
                $backupKeyPath !== '' && is_file($backupKeyPath) ? '読込可能' : '鍵ファイル未配置',
            ],
            [
                'Vite本番資産',
                File::exists(public_path('build/manifest.json')) && ! File::exists(public_path('hot')),
                File::exists(public_path('hot'))
                    ? '開発用public/hotあり（本番配置から除外）'
                    : (File::exists(public_path('build/manifest.json')) ? 'manifestあり' : 'npm run buildが必要'),
            ],
            ['公認記録切替日', filled(config('achievements.cutover_date')), filled(config('achievements.cutover_date')) ? (string) config('achievements.cutover_date') : '公開日確定後に設定'],
        ];

        return array_map(function (array $check) use ($production): array {
            [$label, $passed, $detail] = $check;

            if ($passed) {
                return $this->result('OK', $label, $detail);
            }

            return $this->result($production ? 'NG' : 'WARN', $label, $detail);
        }, $checks);
    }

    private function pendingMigrations(): ?int
    {
        if (! Schema::hasTable('migrations')) {
            return null;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->all();

        return count(array_diff($files, $ran));
    }

    private function canWriteDirectory(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $probe = @tempnam($path, 'jpba_readiness_');
        if ($probe === false) {
            return false;
        }

        return @unlink($probe);
    }

    /** @return array<int, string> */
    private function legacyPublicLinks(mixed $value, string $path = 'jpba_public'): array
    {
        if (is_string($value)) {
            return preg_match('/\\b(?:www\\.)?(?:jpba1\\.jp|jpba\\.or\\.jp)\\b/i', $value)
                ? ["{$path}={$value}"]
                : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $found = [];
        foreach ($value as $key => $child) {
            $found = array_merge($found, $this->legacyPublicLinks($child, "{$path}.{$key}"));
        }

        return $found;
    }

    /** @return array{status: string, label: string, detail: string} */
    private function check(bool $passed, string $label, string $detail): array
    {
        return $this->result($passed ? 'OK' : 'NG', $label, $detail);
    }

    /** @return array{status: string, label: string, detail: string} */
    private function result(string $status, string $label, string $detail): array
    {
        return compact('status', 'label', 'detail');
    }

    /** @param array<int, array{status: string, label: string, detail: string}> $checks
     * @return array{ok: int, warn: int, ng: int}
     */
    private function summary(array $checks): array
    {
        return [
            'ok' => collect($checks)->where('status', 'OK')->count(),
            'warn' => collect($checks)->where('status', 'WARN')->count(),
            'ng' => collect($checks)->where('status', 'NG')->count(),
        ];
    }
}
