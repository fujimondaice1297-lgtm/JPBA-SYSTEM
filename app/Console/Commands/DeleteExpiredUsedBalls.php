<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteExpiredUsedBalls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-used-balls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = \App\Models\UsedBall::where('expires_at', '<', now())->delete();
        $this->info("$count 件の期限切れボールを削除しました。");
    }

}
