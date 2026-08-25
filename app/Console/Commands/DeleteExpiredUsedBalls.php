<?php

namespace App\Console\Commands;

use App\Services\BallRegistrationRetentionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class DeleteExpiredUsedBalls extends Command
{
    protected $signature = 'usedballs:delete-expired {--date= : 判定日（YYYY-MM-DD）}';

    protected $aliases = ['app:delete-expired-used-balls'];

    protected $description = '【廃止済み】期限切れマイボールを削除せず件数確認する';

    public function handle(BallRegistrationRetentionAuditService $service): int
    {
        try {
            $asOf = $this->option('date')
                ? Carbon::createFromFormat('Y-m-d', (string) $this->option('date'))->startOfDay()
                : null;
        } catch (Throwable) {
            $this->error('--date は YYYY-MM-DD 形式で指定してください。');

            return self::INVALID;
        }

        $report = $service->build($asOf);

        $this->warn('この削除コマンドは廃止済みです。大会登録履歴を保全するためデータは削除しません。');
        $this->line('期限切れマイボール: '.$report['used_balls']['expired'].'件');
        $this->line('うち大会履歴あり: '.$report['used_balls']['expired_with_tournament_history'].'件');
        $this->line('削除件数: 0件');

        return self::SUCCESS;
    }
}
