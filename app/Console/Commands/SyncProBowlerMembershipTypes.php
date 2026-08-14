<?php

namespace App\Console\Commands;

use App\Services\ProBowlerMembershipClassificationService;
use Illuminate\Console\Command;

final class SyncProBowlerMembershipTypes extends Command
{
    protected $signature = 'pro-bowlers:sync-membership-types
        {--year= : 判定対象年度（未指定時は現在年度）}
        {--dry-run : DBを変更せず判定結果だけ表示する}';

    protected $description = 'シード、TP講習、当年度公式戦出場、海外表記から会員種別を再判定する';

    public function handle(ProBowlerMembershipClassificationService $service): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        if ($year < 2000 || $year > 2100) {
            $this->error('年度は2000～2100で指定してください。');

            return self::FAILURE;
        }

        $summary = $service->syncAll($year, (bool) $this->option('dry-run'));

        $this->table(
            ['年度', '処理', '変更', '変更なし', 'モード'],
            [[
                $year,
                $summary['processed'],
                $summary['changed'],
                $summary['unchanged'],
                $this->option('dry-run') ? '確認のみ' : '更新',
            ]],
        );

        $this->table(
            ['会員種別', '人数'],
            collect($summary['counts'])->map(fn (int $count, string $type): array => [$type, $count])->values()->all(),
        );

        if ($summary['change_samples'] !== []) {
            $this->newLine();
            $this->line('変更例（最大30名）');
            foreach ($summary['change_samples'] as $sample) {
                $this->line(sprintf(
                    '%s %s %s: %s',
                    $sample['license_no'],
                    $sample['name'] ?: '-',
                    json_encode($sample['changes'], JSON_UNESCAPED_UNICODE),
                    $sample['reason'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
