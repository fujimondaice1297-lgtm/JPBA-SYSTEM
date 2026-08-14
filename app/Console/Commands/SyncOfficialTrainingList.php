<?php

namespace App\Console\Commands;

use App\Services\TrainingOfficialListImportService;
use Illuminate\Console\Command;

class SyncOfficialTrainingList extends Command
{
    protected $signature = 'training:sync-official-list
        {--dataset= : 公式修了者一覧の抽出済みJSON}
        {--dry-run : DBを変更せず照合結果だけ表示する}';

    protected $description = 'JPBA公式TP講習会修了者一覧を選手マスタと照合し、根拠付きの資格情報として取り込む。';

    public function handle(TrainingOfficialListImportService $service): int
    {
        $result = $this->option('dry-run')
            ? $service->preview($this->option('dataset'))
            : $service->import($this->option('dataset'));
        $summary = $result['summary'];

        $this->table(
            ['公式掲載', '男性', '女性', '照合済み', '有効会員', '非アクティブ', '未照合', '重複候補'],
            [[
                $summary['total'],
                $summary['male'],
                $summary['female'],
                $summary['matched'],
                $summary['active'],
                $summary['inactive'],
                $summary['unmatched'],
                $summary['ambiguous'],
            ]],
        );

        if ($this->option('dry-run')) {
            $this->info('照合のみ実行しました。データベースは変更していません。');

            return ($summary['unmatched'] + $summary['ambiguous']) > 0 ? self::FAILURE : self::SUCCESS;
        }

        if (! $result['created']) {
            $this->info('同じ公式PDFは取り込み済みです。重複登録は行いませんでした。');
        } else {
            $this->info(sprintf(
                '公式一覧ID %dを保存し、%d名の受講状態を更新しました。',
                $result['official_list']->id,
                $result['synced_bowlers'],
            ));
        }

        return self::SUCCESS;
    }
}
