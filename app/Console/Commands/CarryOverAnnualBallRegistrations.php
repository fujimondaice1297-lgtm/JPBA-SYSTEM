<?php

namespace App\Console\Commands;

use App\Services\BallAnnualRegistrationService;
use Illuminate\Console\Command;

class CarryOverAnnualBallRegistrations extends Command
{
    protected $signature = 'balls:carry-over-annual-registrations
        {year? : 引継ぎ先の年度（省略時は現在年度）}
        {--force : 年度申請へ実際に反映する}
        {--json : JSONで結果を表示する}';

    protected $description = '前年度承認ボールのうち、年度初日に検量証が有効なボールを次年度へ引き継ぐ';

    public function handle(BallAnnualRegistrationService $service): int
    {
        $year = (int) ($this->argument('year') ?: now()->year);
        if ($year < 2001 || $year > ((int) now()->year + 1)) {
            $this->error('引継ぎ先年度は2001年から翌年度までで指定してください。');

            return self::FAILURE;
        }

        $report = $service->carryOverYear($year, (bool) $this->option('force'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('年度ボール自動引継ぎ: '.$report['mode']);
        $this->table(
            ['対象年度', '対象予定', '作成', '既存申請あり', '有効検量証なし'],
            [[
                $report['target_year'],
                $report['planned_count'],
                $report['created_count'],
                $report['skipped_existing_count'],
                $report['skipped_no_valid_inspection_count'],
            ]]
        );

        return self::SUCCESS;
    }
}
