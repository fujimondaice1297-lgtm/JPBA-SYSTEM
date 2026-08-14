<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Services\TrainingComplianceService;
use App\Services\TrainingOfficialListImportService;
use Illuminate\Console\Command;

class SyncOfficialTrainingHistory extends Command
{
    protected $signature = 'training:sync-official-history
        {--dry-run : DBを変更せず、各公式資料の照合件数だけ確認する}';

    protected $description = '2015～2024年のJPBA公式TP講習会資料を取り込み、全選手の出場資格を確定する';

    /** @var list<string> */
    private const DATASETS = [
        'database/data/jpba_official_tp_training_history_2015.json',
        'database/data/jpba_official_tp_training_history_2016.json',
        'database/data/jpba_official_tp_training_history_2017.json',
        'database/data/jpba_official_tp_training_history_2018.json',
        'database/data/jpba_official_tp_training_history_2019.json',
        'database/data/jpba_official_tp_training_history_2024.json',
    ];

    public function handle(
        TrainingOfficialListImportService $importer,
        TrainingComplianceService $compliance,
    ): int {
        $rows = [];
        foreach (self::DATASETS as $dataset) {
            $result = $this->option('dry-run')
                ? $importer->preview($dataset)
                : $importer->import($dataset);
            $summary = $result['summary'];
            $rows[] = [
                $result['payload']['edition_number'],
                $summary['total'],
                $summary['active'],
                $summary['inactive'],
                $summary['unmatched'],
                $summary['ambiguous'],
                $this->option('dry-run') ? '確認のみ' : ($result['created'] ? '取込' : '取込済'),
            ];
        }

        $this->table(
            ['回', '掲載', '現役照合', '非アクティブ', '未照合', '重複候補', '処理'],
            $rows,
        );

        if ($this->option('dry-run')) {
            $this->info('2014年資料は受講申込者リストのため、受講証明から除外しています。');

            return self::SUCCESS;
        }

        $synced = 0;
        ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->orderBy('id')
            ->chunkById(100, function ($bowlers) use ($compliance, &$synced): void {
                foreach ($bowlers as $bowler) {
                    $compliance->syncBowler($bowler);
                    $synced++;
                }
            });

        $counts = ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->selectRaw('training_compliance_status, count(*) as total')
            ->groupBy('training_compliance_status')
            ->pluck('total', 'training_compliance_status');

        $this->newLine();
        $this->info("{$synced}名を再判定しました。");
        $this->table(
            ['公式一覧有効', '過去受講・期限切れ', '未受講・権利なし', '個別有効', '期限通知対象', '免除'],
            [[
                (int) ($counts[TrainingComplianceService::OFFICIAL_LIST_VALID] ?? 0),
                (int) ($counts[TrainingComplianceService::EXPIRED] ?? 0),
                (int) ($counts[TrainingComplianceService::MISSING] ?? 0),
                (int) ($counts[TrainingComplianceService::VALID] ?? 0),
                (int) ($counts[TrainingComplianceService::EXPIRING_THIS_YEAR] ?? 0)
                    + (int) ($counts[TrainingComplianceService::EXPIRING_NEXT_YEAR] ?? 0),
                (int) ($counts[TrainingComplianceService::EXEMPT] ?? 0),
            ]],
        );
        $this->info('2014年資料は申込者一覧のため除外し、他の過去資料にもない方は未受講として確定しました。');

        return self::SUCCESS;
    }
}
