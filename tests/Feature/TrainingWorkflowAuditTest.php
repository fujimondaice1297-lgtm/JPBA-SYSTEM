<?php

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('training workflow smoke audit rolls back every temporary record', function () {
    User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => User::STATUS_ACTIVE,
    ]);

    $before = [
        'trainings' => DB::table('trainings')->count(),
        'sessions' => TrainingSession::query()->count(),
        'participants' => DB::table('training_session_participants')->count(),
        'records' => DB::table('pro_bowler_trainings')->count(),
        'notifications' => DB::table('training_compliance_notifications')->count(),
        'bowlers' => DB::table('pro_bowlers')->count(),
    ];

    $exitCode = Artisan::call('jpba:audit-training-workflow', ['--smoke' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('開催回作成→対象者登録→出欠→確定→3年期限→CSV→通知候補→資格判定')
        ->and($output)->toContain('メールは送信せず、試験データはすべてロールバック');

    expect([
        'trainings' => DB::table('trainings')->count(),
        'sessions' => TrainingSession::query()->count(),
        'participants' => DB::table('training_session_participants')->count(),
        'records' => DB::table('pro_bowler_trainings')->count(),
        'notifications' => DB::table('training_compliance_notifications')->count(),
        'bowlers' => DB::table('pro_bowlers')->count(),
    ])->toBe($before);
});
