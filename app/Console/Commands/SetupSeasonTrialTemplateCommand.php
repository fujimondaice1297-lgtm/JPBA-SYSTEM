<?php

namespace App\Console\Commands;

use App\Services\SeasonTrialTemplateSetupService;
use Illuminate\Console\Command;

final class SetupSeasonTrialTemplateCommand extends Command
{
    protected $signature = 'jpba:setup-season-trial-template
        {tournament=61 : Source season-trial tournament ID}
        {--season-key=summer : Edition season key}
        {--edition-name= : Edition display name}
        {--edition-start= : Edition start date (Y-m-d)}
        {--edition-end= : Edition end date (Y-m-d)}
        {--edition-status=in_progress : Edition status}
        {--force : Write changes. Without this option, the command is dry-run only}
        {--json : Output the full report as JSON}';

    protected $description = 'Assign a season-trial tournament to its edition and create a reusable data-free standard template.';

    public function handle(SeasonTrialTemplateSetupService $service): int
    {
        $report = $service->setup(
            tournamentId: (int) $this->argument('tournament'),
            write: (bool) $this->option('force'),
            options: [
                'season_key' => $this->option('season-key'),
                'edition_name' => $this->option('edition-name'),
                'edition_start' => $this->option('edition-start'),
                'edition_end' => $this->option('edition-end'),
                'edition_status' => $this->option('edition-status'),
            ],
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('JPBA season-trial template setup: '.$report['mode']);
        $this->line('Tournament: '.$report['tournament_id'].' '.$report['tournament_name']);
        $this->line(sprintf(
            'Edition: %s (%s to %s)',
            $report['target']['edition_name'],
            $report['target']['edition_start'] ?? '-',
            $report['target']['edition_end'] ?? '-',
        ));

        if ($report['mode'] === 'write') {
            $this->line(sprintf(
                'series=%d edition=%d template=%d version=%d',
                $report['entities']['series_id'],
                $report['entities']['edition_id'],
                $report['entities']['template_id'],
                $report['entities']['template_version'],
            ));
            $this->line('Protected data unchanged: '.($report['protected_data_unchanged'] ? 'yes' : 'no'));
        } else {
            $this->warn('Dry-run only. Re-run with --force after reviewing this report.');
        }

        return self::SUCCESS;
    }
}
