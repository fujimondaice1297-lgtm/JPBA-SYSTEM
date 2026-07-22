<?php

namespace App\Console\Commands;

use App\Services\Official2026TournamentResultsImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportOfficial2026TournamentResultsCommand extends Command
{
    protected $signature = 'jpba:import-official-2026-results
        {--force : Create tournaments and publish the official results. Without this option, the command is dry-run only}
        {--admin-email=yamaguchi@jpba.or.jp : Administrator recorded as the result publisher}
        {--json : Output the full report as JSON}';

    protected $description = 'Import completed 2026 JPBA tournaments and verify published point/prize totals against official rankings.';

    public function handle(Official2026TournamentResultsImportService $service): int
    {
        try {
            $report = $service->import(
                (bool) $this->option('force'),
                (string) $this->option('admin-email'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            $this->info('JPBA official 2026 tournament results: '.$report['mode']);
            $this->line(sprintf(
                'events=%d snapshots=%d rows=%d',
                $report['event_count'],
                $report['snapshot_count'],
                $report['snapshot_row_count'],
            ));
            $this->line(sprintf(
                'missing bowlers=%d venues=%d conflicts=%d dataset differences=%d',
                count($report['missing_bowlers']),
                count($report['missing_venues']),
                count($report['conflicts']),
                (int) $report['dataset_ranking_audit']['difference_count'],
            ));
            if ($report['database_ranking_audit'] !== null) {
                $this->line(sprintf(
                    'existing publications=%d database differences=%d',
                    (int) $report['existing_publication_count'],
                    (int) $report['database_ranking_audit']['difference_count'],
                ));
            }

            if ($report['mode'] === 'write' && $report['errors'] === []) {
                $this->line(sprintf(
                    'published=%d ranking snapshots=%d database differences=%d',
                    count($report['tournaments']),
                    count($report['ranking_snapshots']),
                    (int) ($report['database_ranking_audit']['difference_count'] ?? -1),
                ));
            } elseif (! $this->option('force')) {
                $this->warn('Dry-run only. Re-run with --force after reviewing this report.');
            }
        }

        foreach ($report['errors'] as $error) {
            $this->error($error);
        }

        if ($report['errors'] !== []) {
            return self::FAILURE;
        }
        if (($report['database_ranking_audit']['difference_count'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
