<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ImportProBowlers::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Laravel 12の定期処理は routes/console.php に集約する。
        // この旧Kernelへは重複実行や履歴削除につながる処理を登録しない。
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
