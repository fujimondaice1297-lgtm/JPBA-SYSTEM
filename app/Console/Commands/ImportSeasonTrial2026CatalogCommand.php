<?php

namespace App\Console\Commands;

use App\Services\SeasonTrial2026CatalogService;
use Illuminate\Console\Command;

class ImportSeasonTrial2026CatalogCommand extends Command
{
    protected $signature = 'jpba:import-season-trial-2026-catalog
        {--force : Write editions and tournament shells. Without this option, the command is dry-run only}
        {--json : Output the full report as JSON}';

    protected $description = 'Import the official 2026 JPBA season-trial editions and venue tournament shells.';

    public function handle(SeasonTrial2026CatalogService $service): int
    {
        $report = $service->import((bool) $this->option('force'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->info('JPBA 2026 season-trial catalog: '.$report['mode']);
            $this->line(sprintf(
                'editions create=%d update=%d | tournaments create=%d existing=%d conflicts=%d',
                $report['edition_create_count'],
                $report['edition_update_count'],
                $report['tournament_create_count'],
                $report['tournament_existing_count'],
                $report['conflict_count'],
            ));
            $this->line(sprintf(
                'final-result sources published=%d pending=%d',
                $report['published_result_source_count'],
                $report['pending_result_source_count'],
            ));

            if (! $this->option('force')) {
                $this->warn('Dry-run only. Re-run with --force after reviewing this report.');
            }
        }

        if ($report['conflict_count'] > 0) {
            $this->error('Existing tournament conflicts were found. No database write was performed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
