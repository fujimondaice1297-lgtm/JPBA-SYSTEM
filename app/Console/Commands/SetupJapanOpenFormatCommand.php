<?php

namespace App\Console\Commands;

use App\Services\JapanOpenFormatService;
use Illuminate\Console\Command;

final class SetupJapanOpenFormatCommand extends Command
{
    protected $signature = 'jpba:setup-japan-open-format
        {year : Tournament year}
        {--edition-no= : Edition number}
        {--name= : Edition name}
        {--start-date= : Start date (Y-m-d)}
        {--end-date= : End date (Y-m-d)}
        {--venue-name= : Venue name}
        {--venue-address= : Venue address}
        {--ball-limit=12 : Ball registration limit}
        {--force : Write changes; otherwise dry-run}
        {--json : Output JSON}';

    protected $description = 'Create the reusable Japan Open edition with team, doubles, singles and all-events aggregation.';

    public function handle(JapanOpenFormatService $service): int
    {
        $report = $service->setup([
            'year' => (int) $this->argument('year'),
            'edition_no' => $this->option('edition-no'),
            'name' => $this->option('name'),
            'start_date' => $this->option('start-date'),
            'end_date' => $this->option('end-date'),
            'venue_name' => $this->option('venue-name'),
            'venue_address' => $this->option('venue-address'),
            'ball_registration_limit' => (int) $this->option('ball-limit'),
        ], (bool) $this->option('force'));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->info('Japan Open format: '.$report['mode']);
            $this->line('Components: '.$report['component_count']);
            if ($report['mode'] === 'dry-run') {
                $this->warn('確認のみです。登録する場合は --force を付けてください。');
            } else {
                $this->line('Edition ID: '.$report['edition_id']);
            }
        }

        return self::SUCCESS;
    }
}
