<?php

namespace App\Console\Commands;

use App\Services\TournamentAutoDrawService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunTournamentAutoDraws extends Command
{
    protected $signature = 'tournament:auto-draw-pending {--datetime=} {--tournament_id=} {--dry-run}';

    protected $description = '締切到来後の未抽選エントリーを事務局側で自動一括抽選する';

    public function handle(TournamentAutoDrawService $service): int
    {
        $baseTime = $this->option('datetime')
            ? Carbon::parse((string) $this->option('datetime'))
            : null;

        $tournamentId = $this->option('tournament_id')
            ? (int) $this->option('tournament_id')
            : null;

        $dryRun = (bool) $this->option('dry-run');

        $result = $service->runDueAutoDraws($baseTime, $tournamentId ?: null, $dryRun);
        $summary = $result['summary'];
        $details = $result['details'];

        $this->info('基準日時: ' . $summary['target_datetime']);
        $this->line('確認大会数: ' . $summary['checked_tournaments']);
        $this->line('締切到来ターゲット数: ' . $summary['due_targets']);
        $this->line('抽選対象件数: ' . $summary['target_entries']);
        $this->line('抽選成功: ' . $summary['success']);
        $this->line('抽選失敗: ' . $summary['failed']);
        $this->line('対象なしスキップ: ' . $summary['skipped_no_targets']);

        if ($dryRun) {
            $this->line('DRY-RUN候補: ' . $summary['dry_run_candidates']);
        }

        foreach (array_slice($details, 0, 30) as $detail) {
            $this->line($detail);
        }

        if (count($details) > 30) {
            $this->line('...以下省略: ' . (count($details) - 30) . ' 件');
        }

        return self::SUCCESS;
    }
}