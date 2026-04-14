<?php

namespace App\Console\Commands;

use App\Services\TournamentDrawReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendTournamentDrawReminders extends Command
{
    protected $signature = 'tournament:send-draw-reminders {--date=} {--tournament_id=} {--dry-run}';

    protected $description = '未抽選者への自動DM送信を実行する';

    public function handle(TournamentDrawReminderService $service): int
    {
        $baseDate = $this->option('date') ? Carbon::parse((string) $this->option('date')) : null;
        $tournamentId = $this->option('tournament_id') ? (int) $this->option('tournament_id') : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $service->sendAutomatic($baseDate, $tournamentId ?: null, $dryRun);
        $summary = $result['summary'];
        $details = $result['details'];

        $this->info('対象日: ' . $summary['target_date']);
        $this->line('確認大会数: ' . $summary['checked_tournaments']);
        $this->line('送信対象大会数: ' . $summary['due_tournaments']);
        $this->line('送信対象件数: ' . $summary['target_entries']);
        $this->line('送信成功: ' . $summary['sent']);
        $this->line('送信失敗: ' . $summary['failed']);
        $this->line('既送スキップ: ' . $summary['skipped_logged']);
        $this->line('日付不一致スキップ: ' . $summary['skipped_not_due']);
        $this->line('対象なしスキップ: ' . $summary['skipped_no_targets']);

        if ($dryRun) {
            $this->line('DRY-RUN候補: ' . $summary['dry_run_candidates']);
        }

        foreach (array_slice($details, 0, 20) as $detail) {
            $this->line($detail);
        }

        if (count($details) > 20) {
            $this->line('...以下省略: ' . (count($details) - 20) . ' 件');
        }

        return self::SUCCESS;
    }
}