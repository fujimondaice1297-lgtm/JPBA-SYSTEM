<?php

namespace App\Console\Commands;

use App\Services\JpbaBackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyJpbaBackup extends Command
{
    protected $signature = 'jpba:backup-verify
        {path? : 省略時は最新世代を検証する}
        {--restore-test : 別DB・別フォルダへ実復元し、件数と写真を検査する}
        {--keep : 復元試験用DB・フォルダを検査後も残す}';

    protected $description = '暗号化バックアップのハッシュ検証と安全な別環境復元試験を行う';

    public function handle(JpbaBackupService $service): int
    {
        if ($this->option('keep') && ! $this->option('restore-test')) {
            $this->error('--keep は --restore-test と同時に指定してください。');

            return self::INVALID;
        }

        try {
            $path = $this->argument('path') ? (string) $this->argument('path') : null;
            $result = $this->option('restore-test')
                ? $service->restoreTest($path, (bool) $this->option('keep'))
                : $service->verify($path);

            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error('バックアップ検証失敗: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
