<?php

namespace App\Console\Commands;

use App\Services\BallRegistrationRetentionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AuditBallRegistrationRetention extends Command
{
    protected $signature = 'balls:audit-retention {--date= : 判定日（YYYY-MM-DD）} {--json : JSONで出力する}';

    protected $description = '検量証未登録・期限切れボールを、履歴を削除せず監査する';

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

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('ボール登録保持監査（判定日: '.$report['as_of_date'].'）');
        $this->table(
            ['対象', '総数', '検量証待ち', '期限切れ', '大会履歴あり期限切れ'],
            [
                [
                    '本登録ボール',
                    $report['registered_balls']['total'],
                    $report['registered_balls']['provisional'],
                    $report['registered_balls']['expired'],
                    '-',
                ],
                [
                    'マイボール',
                    $report['used_balls']['total'],
                    $report['used_balls']['provisional'],
                    $report['used_balls']['expired'],
                    $report['used_balls']['expired_with_tournament_history'],
                ],
            ]
        );
        $this->line('履歴保持方針により削除件数は0件です。状態は一覧画面の期限表示で運用します。');

        return self::SUCCESS;
    }
}
