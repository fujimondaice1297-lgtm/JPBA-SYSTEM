<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegisteredBall;

class DeleteBallsWithoutCertificate extends Command
{
    protected $signature = 'balls:delete-without-certificate';

    protected $description = '検量証番号がないボールを削除';

    public function handle()
    {
        $deleted = RegisteredBall::whereNull('certificate_number')
            ->orWhere('certificate_number', '')
            ->delete();

        $this->info("{$deleted} 件の検量証なしボールを削除しました。");
    }
}
