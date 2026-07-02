<?php

namespace App\Console\Commands;

use App\Services\SingleEliminationFixtureDataService;
use Illuminate\Console\Command;
use Throwable;

class RestoreSingleEliminationFixtureCommand extends Command
{
    protected $signature = 'tournament:restore-single-elimination-fixture
        {--force : Replace the existing fixture tournament before recreating it}
        {--json : Output the restore summary as JSON}';

    protected $description = 'Restore a persistent single-elimination fixture tournament for end-to-end result-flow checks.';

    public function handle(SingleEliminationFixtureDataService $fixtureService): int
    {
        try {
            $summary = $fixtureService->restore((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['key', 'value'],
            collect($summary)
                ->map(fn ($value, string $key): array => [
                    $key,
                    is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                ])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }
}
