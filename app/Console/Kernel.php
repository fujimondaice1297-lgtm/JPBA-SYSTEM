<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ImportProBowlers::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('usedballs:delete-expired')->daily();

        $schedule->call(function () {
            \App\Models\RegisteredBall::whereNull('certificate_number')
                ->whereDate('registered_at', '<=', \Carbon\Carbon::now()->endOfYear())
                ->delete();
        })->yearlyOn(12, 31, '00:00');

        $schedule->command('training:notify --days=60')->dailyAt('08:00');

        $schedule->command('tournament:send-draw-reminders')->dailyAt('09:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}