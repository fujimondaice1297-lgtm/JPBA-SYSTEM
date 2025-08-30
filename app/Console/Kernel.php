<?php

namespace App\Console;


use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\RegisteredBall;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ImportProBowlers::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // 既存のコマンドスケジュール（そのまま残す）
        $schedule->command('usedballs:delete-expired')->daily();

        // 年末（12/31）に「検量証なし」の登録を自動削除
        $schedule->call(function () {
            \App\Models\RegisteredBall::whereNull('certificate_number')
                ->whereDate('registered_at', '<=', \Carbon\Carbon::now()->endOfYear())
                ->delete();
        })->yearlyOn(12, 31, '00:00');

        $schedule->command('training:notify --days=60')->dailyAt('08:00');

    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
