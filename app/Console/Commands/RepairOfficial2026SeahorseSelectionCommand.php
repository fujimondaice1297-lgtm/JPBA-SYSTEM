<?php

namespace App\Console\Commands;

use App\Services\Official2026SeahorseSelectionImportService;
use Illuminate\Console\Command;

final class RepairOfficial2026SeahorseSelectionCommand extends Command
{
    protected $signature = 'jpba:repair-official-2026-seahorse-selection
        {--force : Persist the official selection scores}
        {--admin-email=yamaguchi@jpba.or.jp : Administrator email used for audit fields}
        {--json : Print the complete report as JSON}';

    protected $description = 'Audit or restore the 2026 Seahorse Cup selection tournament from the official PDF';

    public function handle(Official2026SeahorseSelectionImportService $service): int
    {
        $report = $service->import(
            write: (bool) $this->option('force'),
            adminEmail: (string) $this->option('admin-email'),
        );

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            $this->line(sprintf(
                'mode=%s attempts=%d participants=%d scores=%d total_pin=%d errors=%d',
                $report['mode'],
                $report['attempt_count'],
                $report['participant_count'],
                $report['expected_score_count'],
                $report['expected_total_pin'],
                count($report['errors']),
            ));
            foreach ($report['errors'] as $error) {
                $this->error($error);
            }
            if (is_array($report['repaired'])) {
                $this->info(sprintf(
                    'tournament=%d publication=%d game_scores=%d total_pin=%d',
                    $report['repaired']['tournament_id'],
                    $report['repaired']['publication_id'],
                    $report['repaired']['game_score_count'],
                    $report['repaired']['game_score_total_pin'],
                ));
            }
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
