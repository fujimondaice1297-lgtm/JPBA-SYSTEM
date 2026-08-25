<?php

namespace App\Console\Commands;

use App\Services\BallRegistrationRetentionAuditService;
use Illuminate\Console\Command;

class DeleteBallsWithoutCertificate extends Command
{
    protected $signature = 'balls:delete-without-certificate';

    protected $description = '【廃止済み】検量証未登録ボールを削除せず件数確認する';

    public function handle(BallRegistrationRetentionAuditService $service): int
    {
        $report = $service->build();

        $this->warn('この削除コマンドは廃止済みです。履歴保全のためデータは削除しません。');
        $this->line('検量証待ちの本登録ボール: '.$report['registered_balls']['provisional'].'件');
        $this->line('削除件数: 0件');

        return self::SUCCESS;
    }
}
