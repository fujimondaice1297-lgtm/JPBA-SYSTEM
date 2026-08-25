<?php

namespace App\Console\Commands;

use App\Services\JpbaBackupService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;

class CreateJpbaBackup extends Command implements Isolatable
{
    protected $signature = 'jpba:backup
        {--initialize-key : 鍵がない場合にGit管理外の専用鍵を作成する}
        {--dry-run : 対象件数と設定だけを表示する}';

    protected $description = 'PostgreSQLと公開・非公開ファイルを暗号化して世代バックアップする';

    public function handle(JpbaBackupService $service): int
    {
        try {
            if ($this->option('dry-run')) {
                $this->line((string) json_encode($service->plan(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $this->info('JPBAバックアップを開始します。');
            $result = $service->create((bool) $this->option('initialize-key'));
            $manifest = $result['manifest'];

            $this->info('バックアップ完了: '.$result['path']);
            $this->line('DB SHA-256: '.$manifest['components']['database']['sha256']);
            $this->line('公開ファイル: '.$manifest['components']['public_storage']['files'].'件');
            $this->line('非公開ファイル: '.$manifest['components']['private_storage']['files'].'件');
            $this->line('選手写真: '.$manifest['components']['profile_photos']['files'].'件');
            $this->line('世代削除: '.count($result['rotated_generations']).'件');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('バックアップ失敗: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
