<?php

namespace App\Console\Commands;

use App\Services\OfficialAnnualScheduleImportService;
use Illuminate\Console\Command;

class ImportOfficialAnnualScheduleCommand extends Command
{
    protected $signature = 'annual-schedules:import-2026 {--replace : 既存の2026年予定表を公式PDFの内容で置き換える}';

    protected $description = 'JPBA公式PDF（2026年7月1日現在）の年間予定表を取り込みます。';

    public function handle(OfficialAnnualScheduleImportService $service): int
    {
        $schedule = $service->import2026((bool) $this->option('replace'));
        $this->info(sprintf('%d年の年間予定表を%d行で登録しました。', $schedule->year, $schedule->rows->count()));

        return self::SUCCESS;
    }
}
