<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Services\ProBowlerProfileNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeProBowlerProfilesCommand extends Command
{
    protected $signature = 'jpba:normalize-pro-bowler-profiles
        {--force : Actually update data. Without this option, the command is dry-run only}
        {--json : Output JSON report}';

    protected $description = 'Normalize clear pro bowler profile drift such as years, postal codes, and district labels.';

    public function handle(ProBowlerProfileNormalizer $normalizer): int
    {
        $force = (bool) $this->option('force');
        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'district_label_updates' => 0,
            'pro_bowler_rows_checked' => 0,
            'pro_bowler_rows_changed' => 0,
            'field_changes' => [],
        ];

        $runner = function () use ($normalizer, &$report): void {
            $report['district_label_updates'] = DB::table('districts')
                ->where('label', '九州南')
                ->update(['label' => '九州・南／沖縄']);

            ProBowler::query()
                ->orderBy('id')
                ->chunkById(200, function ($bowlers) use ($normalizer, &$report): void {
                    foreach ($bowlers as $bowler) {
                        $report['pro_bowler_rows_checked']++;

                        $original = $bowler->getAttributes();
                        $normalized = $normalizer->normalizeData($original);
                        $changed = [];

                        foreach ($normalized as $field => $value) {
                            if (! array_key_exists($field, $original)) {
                                continue;
                            }

                            $before = $original[$field];
                            if ((string) ($before ?? '') === (string) ($value ?? '')) {
                                continue;
                            }

                            $changed[$field] = $value;
                            $report['field_changes'][$field] = ($report['field_changes'][$field] ?? 0) + 1;
                        }

                        if ($changed === []) {
                            continue;
                        }

                        $report['pro_bowler_rows_changed']++;
                        $bowler->forceFill($changed)->save();
                    }
                });
        };

        if ($force) {
            DB::transaction($runner);
        } else {
            DB::beginTransaction();
            try {
                $runner();
            } finally {
                DB::rollBack();
            }
        }

        ksort($report['field_changes']);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('JPBA pro bowler profile normalization: ' . $report['mode']);
            $this->line('district_label_updates: ' . $report['district_label_updates']);
            $this->line('pro_bowler_rows_checked: ' . $report['pro_bowler_rows_checked']);
            $this->line('pro_bowler_rows_changed: ' . $report['pro_bowler_rows_changed']);
            foreach ($report['field_changes'] as $field => $count) {
                $this->line($field . ': ' . $count);
            }
        }

        return self::SUCCESS;
    }
}
