<?php

namespace App\Console\Commands;

use App\Services\Official2026RankingReconciliationService;
use Illuminate\Console\Command;

final class AuditOfficial2026RankingsCommand extends Command
{
    protected $signature = 'jpba:audit-official-2026-rankings
        {--json : Output the complete reconciliation report as JSON}';

    protected $description = 'Reconcile current 2026 publications with official points, prize, games, pins, and averages.';

    public function handle(Official2026RankingReconciliationService $service): int
    {
        $report = $service->audit();

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            $this->line(sprintf(
                'publications=%d official_licenses=%d aggregated_licenses=%d differences=%d',
                (int) $report['publication_count'],
                (int) $report['official_license_count'],
                (int) $report['aggregated_license_count'],
                (int) $report['difference_count'],
            ));
            foreach (array_slice($report['differences'], 0, 30) as $difference) {
                $this->error(sprintf(
                    '%s %s expected=%s actual=%s',
                    $difference['license_no'],
                    $difference['name'] ?? '',
                    json_encode($difference['expected'], JSON_UNESCAPED_UNICODE),
                    json_encode($difference['actual'], JSON_UNESCAPED_UNICODE),
                ));
            }
        }

        if ($report['is_complete']) {
            $this->info('Official 2026 ranking reconciliation: OK');

            return self::SUCCESS;
        }

        $this->error('Official 2026 ranking reconciliation: NG');

        return self::FAILURE;
    }
}
