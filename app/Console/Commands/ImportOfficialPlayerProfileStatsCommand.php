<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Services\JpbaOfficialPlayerProfileService;
use App\Services\ProBowlerSearchScopeService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class ImportOfficialPlayerProfileStatsCommand extends Command
{
    protected $signature = 'jpba:import-official-player-profile-stats
        {--license=* : Import only the specified license number(s)}
        {--limit= : Limit number of players}
        {--all-visible : Include all visible players instead of active players only}
        {--missing-only : Import only players without an official profile import timestamp}
        {--sleep-ms=250 : Sleep between official-site requests}
        {--force : Actually update DB. Without this option, the command is dry-run only}
        {--json : Output JSON report}';

    protected $description = 'One-time import of current JPBA official-site profile aggregates such as wins, career stats, and award counts.';

    public function handle(JpbaOfficialPlayerProfileService $officialProfiles, ProBowlerSearchScopeService $searchScope): int
    {
        $force = (bool) $this->option('force');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $query = ProBowler::query()
            ->whereNotNull('license_no')
            ->where('is_visible', true)
            ->whereRaw("license_no ~ '^[MF][0-9]+$'")
            ->orderBy('license_no_num')
            ->orderBy('license_no');

        $licenses = array_values(array_filter(array_map(
            fn ($value) => strtoupper(trim((string) $value)),
            (array) $this->option('license')
        )));

        if ($licenses !== []) {
            $query->whereIn('license_no', $licenses);
        } elseif (! $this->option('all-visible')) {
            $searchScope->applyStatus($query, ProBowlerSearchScopeService::STATUS_ACTIVE);
        }

        if ($this->option('missing-only')) {
            $query->whereNull('official_profile_imported_at');
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit(max(1, (int) $limit));
        }

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'checked' => 0,
            'would_update' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'field_changes' => [],
            'samples' => [],
            'error_samples' => [],
        ];

        foreach ($query->get() as $bowler) {
            $report['checked']++;

            try {
                $profile = $officialProfiles->fetch((string) $bowler->license_no);
                $payload = $this->payloadFromProfile($profile);
                $changes = $this->diffPayload($bowler, $payload);

                if ($changes === []) {
                    $report['unchanged']++;
                } else {
                    $report['would_update']++;
                    foreach (array_keys($changes) as $field) {
                        $report['field_changes'][$field] = ($report['field_changes'][$field] ?? 0) + 1;
                    }

                    if ($force) {
                        $bowler->forceFill($changes)->save();
                        $report['updated']++;
                    }
                }

                if (count($report['samples']) < 5) {
                    $report['samples'][] = [
                        'license_no' => $bowler->license_no,
                        'name' => $bowler->name_kanji,
                        'official_win_count' => $payload['official_win_count'] ?? null,
                        'perfect_count' => $payload['perfect_count'] ?? null,
                        'eight_hundred_count' => $payload['eight_hundred_count'] ?? null,
                        'seven_ten_count' => $payload['seven_ten_count'] ?? null,
                        'changed_fields' => array_keys($changes),
                    ];
                }
            } catch (Throwable $e) {
                $report['errors']++;

                if ($force) {
                    $bowler->forceFill([
                        'official_profile_url' => JpbaOfficialPlayerProfileService::BASE_URL . '/player1/detail.html?id=' . rawurlencode((string) $bowler->license_no),
                        'official_profile_imported_at' => now(),
                        'official_profile_import_error' => mb_strimwidth($e->getMessage(), 0, 1000),
                    ])->save();
                }

                if (count($report['error_samples']) < 5) {
                    $report['error_samples'][] = [
                        'license_no' => $bowler->license_no,
                        'name' => $bowler->name_kanji,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        ksort($report['field_changes']);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('JPBA official player profile stats import: ' . $report['mode']);
            foreach (Arr::except($report, ['field_changes', 'samples', 'error_samples']) as $key => $value) {
                $this->line($key . ': ' . $value);
            }
            foreach ($report['field_changes'] as $field => $count) {
                $this->line($field . ': ' . $count);
            }
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private function payloadFromProfile(array $profile): array
    {
        $summary = (array) ($profile['summary'] ?? []);
        $awards = (array) ($profile['awards'] ?? []);

        $payload = array_merge($summary, $awards);

        if (array_key_exists('official_win_count', $payload)) {
            $payload['titles_count'] = (int) ($payload['official_win_count'] ?? 0);
            $payload['has_title'] = ((int) ($payload['official_win_count'] ?? 0)) > 0;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function diffPayload(ProBowler $bowler, array $payload): array
    {
        $changes = [];

        foreach ($payload as $field => $value) {
            if (! in_array($field, $bowler->getFillable(), true)) {
                continue;
            }

            $current = $bowler->{$field};
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d H:i:s');
            }
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if ((string) ($current ?? '') === (string) ($value ?? '')) {
                continue;
            }

            $changes[$field] = $value;
        }

        return $changes;
    }
}
