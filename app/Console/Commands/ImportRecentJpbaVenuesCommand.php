<?php

namespace App\Console\Commands;

use App\Services\VenueMasterImportService;
use Illuminate\Console\Command;

class ImportRecentJpbaVenuesCommand extends Command
{
    protected $signature = 'jpba:import-recent-venues
        {--force : Write the curated venue master. Without this option, the command is dry-run only}
        {--json : Output the full report as JSON}';

    protected $description = 'Import active domestic venues used on official JPBA tournament pages from 2022 through 2026.';

    public function handle(VenueMasterImportService $service): int
    {
        $report = $service->import((bool) $this->option('force'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('JPBA recent venue import: '.$report['mode']);
        $this->line(sprintf(
            'dataset=%d created=%d updated=%d unchanged=%d tournament_links=%d',
            $report['dataset_count'],
            $report['created_count'],
            $report['updated_count'],
            $report['unchanged_count'],
            $report['linked_tournament_count'],
        ));
        $this->line('Closed venues excluded: '.count($report['excluded_closed_venues']));

        if (! $this->option('force')) {
            $this->warn('Dry-run only. Re-run with --force after reviewing this report.');
        }

        return self::SUCCESS;
    }
}
