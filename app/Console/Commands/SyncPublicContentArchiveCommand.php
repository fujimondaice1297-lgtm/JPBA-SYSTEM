<?php

namespace App\Console\Commands;

use App\Services\JpbaPublicContentArchiveService;
use Illuminate\Console\Command;
use Throwable;

class SyncPublicContentArchiveCommand extends Command
{
    protected $signature = 'jpba:sync-public-content-archive
        {--refresh : 現行JPBAサイトを再取得してJSON・画像・添付を更新する}
        {--only=all : all / information / topics}
        {--skip-assets : HTMLだけを調査し、画像・添付を保存しない}
        {--dry-run : DBを変更せず差分件数だけ表示する}';

    protected $description = '現行JPBAサイトの過去INFORMATION・トピックスを新サイト内へ保存してDBへ反映する';

    public function handle(JpbaPublicContentArchiveService $archive): int
    {
        $only = strtolower(trim((string) $this->option('only')));
        $sources = match ($only) {
            'all' => ['information', 'topics'],
            'information', 'topics' => [$only],
            default => null,
        };

        if ($sources === null) {
            $this->error('--only は all / information / topics のいずれかです。');

            return self::FAILURE;
        }

        try {
            if ($this->option('refresh')) {
                $snapshot = $archive->refresh(
                    sources: $sources,
                    downloadAssets: ! $this->option('skip-assets'),
                    progress: fn (string $message) => $this->line($message),
                );
                $this->newLine();
                $this->info('アーカイブ取得完了');
                $this->table(
                    ['記事', 'INFORMATION', 'トピックス', '保存ファイル', '容量', '取得不能'],
                    [[
                        $snapshot['summary']['article_count'],
                        $snapshot['summary']['information_count'],
                        $snapshot['summary']['topic_count'],
                        $snapshot['summary']['asset_count'],
                        $this->formatBytes((int) $snapshot['summary']['asset_bytes']),
                        $snapshot['summary']['missing_asset_count'],
                    ]],
                );
            }

            $stats = $archive->applySnapshot((bool) $this->option('dry-run'));
            $this->newLine();
            $this->info($this->option('dry-run') ? 'DB差分確認（変更なし）' : 'DB反映完了');
            $this->table(
                ['新規', '更新', '変更なし', '手修正を保護', '添付'],
                [array_values($stats)],
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
