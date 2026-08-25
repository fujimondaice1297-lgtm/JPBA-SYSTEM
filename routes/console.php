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

// 指定日に残っている未抽選者へ通知する。送信履歴のdispatch_keyでも二重送信を防止する。
Schedule::command('tournament:send-draw-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Tokyo')
    ->name('tournament-draw-reminders')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/scheduled-tournament-draw-reminders.log'));

// 締切後の未抽選者を毎時確認する。処理結果は大会運用ログと実行ログへ残す。
Schedule::command('tournament:auto-draw-pending')
    ->hourly()
    ->timezone('Asia/Tokyo')
    ->name('tournament-auto-draw-pending')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/scheduled-tournament-auto-draw.log'));

// 年末は削除せず、検量証待ち・期限切れ・大会履歴あり件数だけを監査する。
Schedule::command('balls:audit-retention')
    ->yearlyOn(12, 31, '00:05')
    ->timezone('Asia/Tokyo')
    ->name('ball-registration-retention-audit')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/scheduled-ball-retention-audit.log'));

// DB・公開／非公開ファイルを暗号化し、完了済み14世代を保持する。
Schedule::command('jpba:backup --isolated')
    ->dailyAt('01:15')
    ->timezone('Asia/Tokyo')
    ->name('jpba-encrypted-backup')
    ->withoutOverlapping(720)
    ->appendOutputTo(storage_path('logs/scheduled-jpba-backup.log'));
