<?php

namespace App\Console\Commands;

use App\Services\StSummer2026BOfficialImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportStSummer2026BOfficialResultCommand extends Command
{
    protected $signature = 'jpba:import-st-summer-2026-b
        {--source-dir=tmp/pdfs/st_summer_b_2026/text : Directory containing extracted official PDF text files}
        {--dry-run : Parse and summarize the extracted official PDF text without writing to the database}';

    protected $description = 'Import JPBA Season Trial 2026 Summer Series B venue official PDF text into the tournament flow.';

    public function handle(StSummer2026BOfficialImportService $service): int
    {
        $sourceDir = (string) $this->option('source-dir');

        try {
            $summary = $this->option('dry-run')
                ? $service->preview($sourceDir)
                : $service->import($sourceDir);

            $this->info($this->option('dry-run') ? 'Official PDF text preview completed.' : 'Official PDF text import completed.');
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
