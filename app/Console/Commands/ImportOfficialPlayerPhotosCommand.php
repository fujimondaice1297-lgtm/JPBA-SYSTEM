<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Services\ProBowlerPhotoService;
use Illuminate\Console\Command;

class ImportOfficialPlayerPhotosCommand extends Command
{
    protected $signature = 'jpba:import-official-player-photos
        {--license=* : Import only the specified license number(s)}
        {--limit= : Limit number of players}
        {--offset=0 : Skip this many players}
        {--refresh : Re-import even when a local photo already exists}
        {--sleep-ms=100 : Delay between official-site requests}
        {--force : Download, store, and update the database}
        {--json : Output a JSON report}';

    protected $description = 'Copy official JPBA player profile photos into local permanent storage.';

    public function handle(ProBowlerPhotoService $photos): int
    {
        $force = (bool) $this->option('force');
        $refresh = (bool) $this->option('refresh');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $query = ProBowler::query()
            ->where('is_visible', true)
            ->whereNotNull('license_no')
            ->orderBy('license_no_num')
            ->orderBy('license_no');

        $licenses = array_values(array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            (array) $this->option('license')
        )));
        if ($licenses !== []) {
            $query->whereIn('license_no', $licenses);
        }

        $offset = max(0, (int) $this->option('offset'));
        if ($offset > 0) {
            $query->offset($offset);
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit(max(1, (int) $limit));
        }

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'checked' => 0,
            'candidates' => 0,
            'imported' => 0,
            'already_local' => 0,
            'no_source' => 0,
            'errors' => 0,
            'error_rows' => [],
        ];

        foreach ($query->cursor() as $bowler) {
            $report['checked']++;

            if (! $refresh && $photos->localPath($bowler) !== null) {
                $report['already_local']++;
                continue;
            }

            $source = $photos->officialSourceUrl($bowler);
            $report['candidates']++;

            if (! $force) {
                if ($source === null) {
                    $report['no_source']++;
                }
                continue;
            }

            try {
                $result = $photos->importOfficialPhoto($bowler, $source);
                $report['imported']++;
                if (! $this->option('json')) {
                    $this->line(sprintf(
                        '[%d] %s %s -> %s',
                        $report['checked'],
                        $bowler->license_no,
                        $bowler->name_kanji,
                        $result['path']
                    ));
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'was not found')) {
                    $report['no_source']++;
                } else {
                    $report['errors']++;
                }
                $report['error_rows'][] = [
                    'id' => (int) $bowler->id,
                    'license_no' => (string) $bowler->license_no,
                    'name' => (string) $bowler->name_kanji,
                    'source_url' => $source,
                    'error' => $message,
                ];
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->newLine();
            $this->table(
                ['mode', 'checked', 'candidates', 'imported', 'already_local', 'no_source', 'errors'],
                [[
                    $report['mode'],
                    $report['checked'],
                    $report['candidates'],
                    $report['imported'],
                    $report['already_local'],
                    $report['no_source'],
                    $report['errors'],
                ]]
            );
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
