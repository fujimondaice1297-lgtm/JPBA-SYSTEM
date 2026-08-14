<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('balls:sync-usbc-approved --force')
    ->weeklyOn(2, '03:15')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();

// 次年度に期限が切れる会員へ、通知履歴で重複を防ぎながら前年度中に1回案内する。
Schedule::command('training:notify')
    ->dailyAt('08:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();

// 年度替わり・シード更新・当年度成績追加を会員種別へ自動反映する。
Schedule::command('pro-bowlers:sync-membership-types')
    ->dailyAt('02:30')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();
