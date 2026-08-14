<?php

namespace App\Console\Commands;

use App\Services\TrainingExpiryNotificationService;
use Illuminate\Console\Command;

class SendTrainingExpiryNotifications extends Command
{
    protected $signature = 'training:notify
        {--expiry-year= : 有効期限が切れる年。省略時は次年度}
        {--dry-run : 送信せず対象人数だけ確認する}';

    protected $description = 'トーナメントプレイヤー講習会の期限が次年度に切れる会員へ、重複を防いで更新案内を送信する。';

    public function handle(TrainingExpiryNotificationService $service): int
    {
        $expiryYear = (int) ($this->option('expiry-year') ?: now()->addYear()->year);
        if ($expiryYear < 2000 || $expiryYear > 2100) {
            $this->error('expiry-year は2000～2100で指定してください。');

            return self::INVALID;
        }

        if ($this->option('dry-run')) {
            $count = $service->countCandidatesForExpiryYear($expiryYear);
            $this->info("{$expiryYear}年期限の通知候補：{$count}名（送信なし）");

            return self::SUCCESS;
        }

        $summary = $service->sendForExpiryYear($expiryYear);
        $this->info(sprintf(
            '候補%d名／送信%d名／スキップ%d名／失敗%d名',
            $summary['candidates'],
            $summary['sent'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
