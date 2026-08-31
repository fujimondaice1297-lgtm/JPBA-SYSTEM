<?php

namespace App\Console\Commands;

use App\Http\Controllers\TpRegistrationController;
use App\Models\ProBowler;
use App\Models\ProBowlerTraining;
use App\Models\Tournament;
use App\Models\TrainingSession;
use App\Models\TrainingSessionParticipant;
use App\Models\User;
use App\Services\TournamentEntryEligibilityService;
use App\Services\TrainingComplianceService;
use App\Services\TrainingExpiryNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AuditTrainingWorkflow extends Command
{
    protected $signature = 'jpba:audit-training-workflow
        {--smoke : 一時データで開催回作成から資格反映まで確認し、最後に全件ロールバックする}';

    protected $description = 'TP講習会の開催回・受講結果・資格履歴の整合性を監査する';

    public function handle(
        TrainingComplianceService $compliance,
        TrainingExpiryNotificationService $notifications,
        TournamentEntryEligibilityService $eligibility,
    ): int {
        $summary = $this->summary();
        $this->table(['監査項目', '件数'], collect($summary)->map(
            fn ($count, $label) => [$label, $count]
        )->values()->all());

        $inconsistencies = [
            '確定済みで受講予定が残る開催回' => $summary['確定済みで受講予定が残る開催回'],
            '確定日時がない確定済み開催回' => $summary['確定日時がない確定済み開催回'],
            '受講者と受講履歴の選手不一致' => $summary['受講者と受講履歴の選手不一致'],
            '受講者と受講履歴の開催回不一致' => $summary['受講者と受講履歴の開催回不一致'],
        ];

        foreach ($inconsistencies as $label => $count) {
            if ($count > 0) {
                $this->error("NG: {$label} が {$count}件あります。");
            }
        }

        if (array_sum($inconsistencies) > 0) {
            return self::FAILURE;
        }

        $this->info('OK: 現存するTP講習会データに結線不一致はありません。');

        if (! $this->option('smoke')) {
            return self::SUCCESS;
        }

        return $this->runRollbackSmoke($compliance, $notifications, $eligibility);
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        return [
            '開催回' => DB::table('training_sessions')->count(),
            '受講者' => DB::table('training_session_participants')->count(),
            '個別受講履歴' => DB::table('pro_bowler_trainings')->count(),
            '期限通知履歴' => DB::table('training_compliance_notifications')->count(),
            '確定済みで受講予定が残る開催回' => DB::table('training_sessions as sessions')
                ->join('training_session_participants as participants', 'participants.training_session_id', '=', 'sessions.id')
                ->where('sessions.status', TrainingSession::STATUS_COMPLETED)
                ->where('participants.attendance_status', TrainingSessionParticipant::STATUS_REGISTERED)
                ->distinct('sessions.id')
                ->count('sessions.id'),
            '確定日時がない確定済み開催回' => DB::table('training_sessions')
                ->where('status', TrainingSession::STATUS_COMPLETED)
                ->whereNull('finalized_at')
                ->count(),
            '受講者と受講履歴の選手不一致' => DB::table('training_session_participants as participants')
                ->join('pro_bowler_trainings as records', 'records.id', '=', 'participants.pro_bowler_training_id')
                ->whereColumn('participants.pro_bowler_id', '<>', 'records.pro_bowler_id')
                ->count(),
            '受講者と受講履歴の開催回不一致' => DB::table('training_session_participants as participants')
                ->join('pro_bowler_trainings as records', 'records.id', '=', 'participants.pro_bowler_training_id')
                ->whereColumn('participants.training_session_id', '<>', 'records.training_session_id')
                ->count(),
        ];
    }

    private function runRollbackSmoke(
        TrainingComplianceService $compliance,
        TrainingExpiryNotificationService $notifications,
        TournamentEntryEligibilityService $eligibility,
    ): int {
        $before = $this->mutableCounts();
        DB::beginTransaction();

        try {
            $operator = User::query()->whereIn('role', ['admin', 'editor'])->orderBy('id')->firstOrFail();
            $training = $compliance->mandatoryTraining();
            $token = Str::upper(Str::random(10));
            $heldOn = today();

            $attendedBowler = $this->createSmokeBowler('A'.$token, '講習監査・受講済');
            $absentBowler = $this->createSmokeBowler('B'.$token, '講習監査・未受講');

            $session = TrainingSession::query()->create([
                'training_id' => $training->id,
                'session_year' => (int) $heldOn->year,
                'name' => 'TP講習会ロールバック監査（自動削除）',
                'held_on' => $heldOn,
                'venue' => '監査用会場',
                'status' => TrainingSession::STATUS_OPEN,
                'created_by_user_id' => $operator->id,
                'updated_by_user_id' => $operator->id,
            ]);

            $attended = $session->participants()->create([
                'pro_bowler_id' => $attendedBowler->id,
                'attendance_status' => TrainingSessionParticipant::STATUS_ATTENDED,
                'notes' => 'ロールバック監査',
            ]);
            $session->participants()->create([
                'pro_bowler_id' => $absentBowler->id,
                'attendance_status' => TrainingSessionParticipant::STATUS_ABSENT,
                'notes' => 'ロールバック監査',
            ]);

            $this->assertFalse($eligibility->evaluate($attendedBowler)['allowed'], '確定前の出場資格');

            $totals = $compliance->finalizeSession($session, (int) $operator->id);
            $this->assertSame(1, $totals['attended'], '受講済み集計');
            $this->assertSame(1, $totals['absent'], '未受講集計');

            $session->refresh();
            $attended->refresh();
            $record = ProBowlerTraining::query()->findOrFail($attended->pro_bowler_training_id);
            $expectedExpiry = $compliance->calculateExpiry($heldOn, $training);
            $this->assertSame(TrainingSession::STATUS_COMPLETED, $session->status, '開催回確定');
            $this->assertSame($expectedExpiry->toDateString(), $record->expires_at?->toDateString(), '3年期限');
            $this->assertTrue($eligibility->evaluate($attendedBowler->refresh())['allowed'], '受講後の出場資格');
            $this->assertFalse($eligibility->evaluate($absentBowler->refresh())['allowed'], '未受講者の出場資格');

            $beforeTrainingTournament = new Tournament([
                'start_date' => $heldOn->copy()->subDay(),
            ]);
            $validPeriodTournament = new Tournament([
                'start_date' => $heldOn->copy(),
            ]);
            $afterExpiryTournament = new Tournament([
                'start_date' => $expectedExpiry->copy()->addDay(),
            ]);
            $this->assertFalse(
                $eligibility->evaluate($attendedBowler->refresh(), $beforeTrainingTournament)['allowed'],
                '受講日前に始まる大会の出場資格',
            );
            $this->assertTrue(
                $eligibility->evaluate($attendedBowler->refresh(), $validPeriodTournament)['allowed'],
                '受講日以降に始まる大会の出場資格',
            );
            $this->assertFalse(
                $eligibility->evaluate($attendedBowler->refresh(), $afterExpiryTournament)['allowed'],
                '受講期限後に始まる大会の出場資格',
            );
            $this->assertSame(
                1,
                $notifications->countCandidatesForExpiryYear((int) $expectedExpiry->year, [$attendedBowler->id]),
                '期限通知候補',
            );

            $csv = $this->captureCsv($session);
            $this->assertContains('ライセンスNo', $csv, '受講者CSV見出し');
            $this->assertContains($attendedBowler->license_no, $csv, '受講者CSV内容');

            $session->forceFill([
                'status' => TrainingSession::STATUS_OPEN,
                'finalized_at' => null,
                'finalized_by_user_id' => null,
                'updated_by_user_id' => $operator->id,
            ])->save();
            $attended->forceFill(['attendance_status' => TrainingSessionParticipant::STATUS_ABSENT])->save();
            $session->unsetRelation('participants');

            $corrected = $compliance->finalizeSession($session, (int) $operator->id);
            $this->assertSame(0, $corrected['attended'], '確定解除後の受講済み集計');
            $this->assertSame(2, $corrected['absent'], '確定解除後の未受講集計');
            $this->assertSame('revoked', $record->refresh()->record_status, '訂正前受講履歴の無効化');
            $this->assertFalse($eligibility->evaluate($attendedBowler->refresh())['allowed'], '訂正後の出場資格');
            $this->assertFalse(
                $eligibility->evaluate($attendedBowler->refresh(), $validPeriodTournament)['allowed'],
                '未受講へ訂正後の大会初日時点の出場資格',
            );

            DB::rollBack();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('NG: ロールバック試験に失敗しました。'.$exception->getMessage());

            return self::FAILURE;
        }

        if ($before !== $this->mutableCounts()) {
            $this->error('NG: ロールバック後の件数が試験前と一致しません。');

            return self::FAILURE;
        }

        $this->info('OK: 開催回作成→対象者登録→出欠→確定→3年期限→CSV→通知候補→資格判定を確認しました。');
        $this->info('OK: 大会初日が受講日前・有効期間内・期限後の場合の出場資格切替を確認しました。');
        $this->info('OK: 確定解除後の訂正で受講履歴を無効化し、出場資格なしへ戻ることを確認しました。');
        $this->info('OK: メールは送信せず、試験データはすべてロールバックしました。');

        return self::SUCCESS;
    }

    private function createSmokeBowler(string $licenseNo, string $name): ProBowler
    {
        return ProBowler::query()->create([
            'license_no' => $licenseNo,
            'name_kanji' => $name,
            'sex' => 1,
            'email' => Str::lower($licenseNo).'@example.invalid',
            'is_active' => true,
            'is_visible' => false,
            'member_class' => 'player',
            'can_enter_official_tournament' => true,
            'training_compliance_status' => TrainingComplianceService::MISSING,
        ]);
    }

    private function captureCsv(TrainingSession $session): string
    {
        $response = app(TpRegistrationController::class)->export($session->fresh());
        ob_start();

        try {
            $response->sendContent();

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    /** @return array<string, int> */
    private function mutableCounts(): array
    {
        return [
            'trainings' => DB::table('trainings')->count(),
            'training_sessions' => DB::table('training_sessions')->count(),
            'training_session_participants' => DB::table('training_session_participants')->count(),
            'pro_bowler_trainings' => DB::table('pro_bowler_trainings')->count(),
            'training_compliance_notifications' => DB::table('training_compliance_notifications')->count(),
            'pro_bowlers' => DB::table('pro_bowlers')->count(),
        ];
    }

    private function assertSame(int|string $expected, int|string|null $actual, string $label): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException("{$label}が一致しません。（期待 {$expected} / 実際 {$actual}）");
        }
    }

    private function assertTrue(bool $condition, string $label): void
    {
        if (! $condition) {
            throw new RuntimeException("{$label}を確認できませんでした。");
        }
    }

    private function assertFalse(bool $condition, string $label): void
    {
        $this->assertTrue(! $condition, $label);
    }

    private function assertContains(string $needle, string $haystack, string $label): void
    {
        if (! str_contains($haystack, $needle)) {
            throw new RuntimeException("{$label}を確認できませんでした。");
        }
    }
}
