<?php

namespace App\Console\Commands;

use App\Services\Official2026StandardDetailImportService;
use Illuminate\Console\Command;
use Throwable;

final class RepairOfficial2026StandardTournamentsCommand extends Command
{
    protected $signature = 'jpba:repair-official-2026-standard-tournaments
        {--force : Replace game scores, recalculate snapshots, and republish all 12 tournaments}
        {--admin-email=yamaguchi@jpba.or.jp : Administrator recorded as the publisher}
        {--json : Output the full validation report as JSON}';

    protected $description = 'Restore official 2026 standard-tournament per-game scores with publication completeness checks.';

    public function handle(Official2026StandardDetailImportService $service): int
    {
        try {
            $report = $service->import(
                (bool) $this->option('force'),
                (string) $this->option('admin-email'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            $this->info('JPBA official 2026 standard-tournament detail repair: '.$report['mode']);
            $this->line(sprintf(
                'events=%d expected_scores=%d errors=%d repaired=%d',
                (int) $report['event_count'],
                (int) $report['expected_score_count'],
                count($report['errors']),
                count($report['repaired']),
            ));
            foreach ($report['events'] as $event) {
                $comparison = $event['existing_score_comparison'] ?? [];
                $this->line(sprintf(
                    '%s tournament=%s expected=%d actual=%d differences=%d',
                    $event['key'],
                    $event['tournament_id'] ?? 'missing',
                    (int) $event['expected_score_count'],
                    (int) ($comparison['actual_count'] ?? 0),
                    (int) ($comparison['difference_count'] ?? 0),
                ));
            }
            if (! $this->option('force') && $report['errors'] === []) {
                $this->warn('Dry-run only. Re-run with --force after reviewing this report.');
            }
        }

        foreach ($report['errors'] as $error) {
            $this->error($error);
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
