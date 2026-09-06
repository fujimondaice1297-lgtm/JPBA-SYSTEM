<?php

namespace App\Console\Commands;

use App\Services\JpbaTournamentArchiveService;
use Illuminate\Console\Command;
use Throwable;

class SyncTournamentArchiveCommand extends Command
{
    protected $signature = 'jpba:sync-tournament-archive
        {--refresh : 現行JPBAサイトを再取得してJSON・大会資料を更新する}
        {--from=2016 : 取得開始年}
        {--to= : 取得終了年（省略時は当年）}
        {--skip-assets : HTMLだけを取得し、資料・画像を保存しない}
        {--dry-run : DBを変更せず差分件数だけ表示する}';

    protected $description = '2016年以降の公式トーナメントページと大会資料・成績を新サイトへ保存する';

    public function handle(JpbaTournamentArchiveService $archive): int
    {
        try {
            if ($this->option('refresh')) {
                $snapshot = $archive->refresh(
                    yearFrom: max(2016, (int) $this->option('from')),
                    yearTo: $this->option('to') ? (int) $this->option('to') : null,
                    downloadAssets: ! $this->option('skip-assets'),
                    progress: fn (string $message) => $this->line($message),
                );
                $summary = $snapshot['summary'];
                $this->newLine();
                $this->info('大会アーカイブ取得完了');
                $this->table(
                    ['発見ページ', '保存大会', 'ページ失敗', '保存資料', '容量', '資料失敗'],
                    [[
                        $summary['discovered_page_count'], $summary['archive_count'],
                        $summary['page_failure_count'], $summary['asset_count'],
                        $this->formatBytes((int) $summary['asset_bytes']), $summary['asset_failure_count'],
                    ]],
                );
            }

            $stats = $archive->applySnapshot((bool) $this->option('dry-run'));
            $this->newLine();
            $this->info($this->option('dry-run') ? 'DB差分確認（変更なし）' : 'DB反映完了');
            $this->table(['新規', '更新', '変更なし', '手修正を保護'], [array_values($stats)]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? number_format($bytes / 1024, 1).' KB'
            : number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
