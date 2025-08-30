<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProBowler;
use Illuminate\Support\Facades\Storage;

class ImportProBowlersFromCsv extends Command
{
    protected $signature = 'import:pro_bowlers_csv';
    protected $description = 'CSVファイルからプロボウラー情報をインポート';

    public function handle()
    {
        $path = base_path('OLD_JPBA/csv/Pro_colum.csv');

        if (!file_exists($path)) {
            $this->error("CSVファイルが見つかりません: $path");
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error("CSVファイルを開けませんでした。");
            return;
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($data = fgetcsv($handle)) !== FALSE) {
            // 空行・ヘッダー除外（必要に応じて調整）
            if (empty($data[0]) || $data[0] === 'ライセンスNo') continue;

            ProBowler::updateOrCreate(
                ['license_no' => $data[0]],
                [
                    'name' => $data[9],
                    'kana' => $data[10],
                    'romaji' => $data[11],
                    'birthdate' => $data[12],
                    'gender' => $data[1],
                    'region' => $data[2],
                    // 他の項目も必要に応じて追加
                ]
            );

            $bar->advance();
        }

        fclose($handle);
        $bar->finish();

        $this->info("\nCSVインポート完了！");
    }
}
