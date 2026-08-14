<?php

namespace App\Console\Commands;

use App\Models\OfficialProfileStatSnapshot;
use App\Models\ProBowler;
use App\Services\JpbaOfficialPlayerProfileService;
use App\Services\OfficialPlayerHistoryImportService;
use App\Services\ProBowlerSearchScopeService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportOfficialPlayerProfileStatsCommand extends Command
{
    protected $signature = 'jpba:import-official-player-profile-stats
        {--license=* : Import only the specified license number(s)}
        {--limit= : Limit number of players}
        {--offset=0 : Skip this many players after ordering by license number}
        {--license-no-from= : Minimum numeric license number}
        {--license-no-to= : Maximum numeric license number}
        {--all-visible : Include all visible players instead of active players only}
        {--missing-only : Import only players without an official profile import timestamp}
        {--snapshot-missing-only : Import only players without an official profile snapshot}
        {--snapshot-existing-only : Import only players with an official profile snapshot}
        {--retry-errors : Retry already attempted profiles that still have no snapshot}
        {--network-errors-only : Retry only profiles whose last attempt failed at the network layer}
        {--season-trial-missing-only : Import only players with official wins and no season trial win count yet}
        {--with-history : Also import annual records and tournament participation histories}
        {--history-pending-only : Import only players without a completed official history import}
        {--history-year-from= : Minimum tournament participation year}
        {--history-year-to= : Maximum tournament participation year}
        {--history-missing-only : Skip participation years that already have imported rows}
        {--history-concurrency=1 : Concurrent official-site year requests per player (1-8; use 1 for JPBA)}
        {--continue-after-history-error : Continue to the next player after an official-site history error}
        {--sleep-ms=250 : Sleep between official-site requests}
        {--force : Actually update DB. Without this option, the command is dry-run only}
        {--json : Output JSON report}';

    protected $description = 'One-time import of current JPBA official-site profile aggregates such as wins, career stats, and award counts.';

    public function handle(
        JpbaOfficialPlayerProfileService $officialProfiles,
        OfficialPlayerHistoryImportService $historyImporter,
        ProBowlerSearchScopeService $searchScope
    ): int
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

        if ($this->option('license-no-from') !== null) {
            $query->where(
                'license_no_num',
                '>=',
                max(0, (int) $this->option('license-no-from'))
            );
        }

        if ($this->option('license-no-to') !== null) {
            $query->where(
                'license_no_num',
                '<=',
                max(0, (int) $this->option('license-no-to'))
            );
        }

        if ($this->option('snapshot-missing-only')) {
            $query->whereDoesntHave('officialProfileStatSnapshots');
            if (! $this->option('retry-errors')) {
                $query->whereNull('official_profile_imported_at');
            }
        }

        if ($this->option('snapshot-existing-only')) {
            $query->whereHas('officialProfileStatSnapshots');
        }

        if ($this->option('history-pending-only')) {
            $query->whereDoesntHave('officialHistoryImport');
        }

        if ($this->option('season-trial-missing-only')) {
            $query->where('official_win_count', '>', 0)
                ->whereNull('season_trial_win_count');
        }

        if ($this->option('network-errors-only')) {
            $query->whereDoesntHave('officialProfileStatSnapshots')
                ->where('official_profile_import_error', 'like', 'cURL error%');
        }

        $limit = $this->option('limit');
        $offset = max(0, (int) $this->option('offset'));
        if ($offset > 0) {
            $query->offset($offset);
        }

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
            'snapshots_created' => 0,
            'lower_award_counts_ignored' => 0,
            'history_annual_rows_seen' => 0,
            'history_annual_rows_changed' => 0,
            'history_tournament_years_seen' => 0,
            'history_tournament_years_skipped' => 0,
            'history_tournament_years_completed' => 0,
            'history_tournament_rows_seen' => 0,
            'history_tournament_rows_changed' => 0,
            'history_errors' => 0,
            'history_players_completed' => 0,
            'stopped_early' => false,
            'field_changes' => [],
            'samples' => [],
            'error_samples' => [],
        ];

        foreach ($query->get() as $bowler) {
            $stopAfterPlayer = false;
            $report['checked']++;

            try {
                $profile = $officialProfiles->fetch((string) $bowler->license_no);

                if ($this->option('with-history')) {
                    $history = $historyImporter->import(
                        bowler: $bowler,
                        baseProfile: $profile,
                        force: $force,
                        yearFrom: $this->nullableYearOption('history-year-from'),
                        yearTo: $this->nullableYearOption('history-year-to'),
                        missingOnly: (bool) $this->option('history-missing-only'),
                        sleepMs: $sleepMs,
                        historyConcurrency: max(
                            1,
                            min(8, (int) $this->option('history-concurrency'))
                        )
                    );
                    $report['history_annual_rows_seen'] += (int) $history['annual_rows_seen'];
                    $report['history_annual_rows_changed'] += (int) $history['annual_rows_changed'];
                    $report['history_tournament_years_seen'] += (int) $history['tournament_years_seen'];
                    $report['history_tournament_years_skipped'] += (int) $history['tournament_years_skipped'];
                    $report['history_tournament_years_completed'] += (int) $history['tournament_years_completed'];
                    $report['history_tournament_rows_seen'] += (int) $history['tournament_rows_seen'];
                    $report['history_tournament_rows_changed'] += (int) $history['tournament_rows_changed'];
                    $report['history_errors'] += count((array) $history['errors']);
                    $report['history_players_completed'] += (int) $history['player_completed'];
                    if (
                        $history['errors'] !== []
                        && ! $this->option('continue-after-history-error')
                    ) {
                        $stopAfterPlayer = true;
                    }

                    foreach ((array) $history['errors'] as $historyError) {
                        if (count($report['error_samples']) >= 5) {
                            break;
                        }
                        $report['error_samples'][] = [
                            'license_no' => $bowler->license_no,
                            'name' => $bowler->name_kanji,
                            'message' => 'History '
                                . ($historyError['year'] ?? '?')
                                . ': '
                                . ($historyError['message'] ?? 'unknown error'),
                        ];
                    }
                }

                $rawPayload = $this->payloadFromProfile($profile);
                $ignoredLowerFields = array_values(array_filter(
                    ['perfect_count', 'eight_hundred_count', 'seven_ten_count'],
                    fn (string $field): bool => (int) ($rawPayload[$field] ?? 0)
                        < (int) $bowler->{$field}
                ));
                $payload = $this->monotonicAwardPayload(
                    $bowler,
                    $rawPayload
                );
                [$changes] = $this->diffPayload($bowler, $payload);

                if ($force) {
                    DB::transaction(function () use (
                        &$bowler,
                        &$payload,
                        &$changes,
                        $rawPayload
                    ): void {
                        $locked = ProBowler::query()
                            ->lockForUpdate()
                            ->findOrFail($bowler->id);
                        $payload = $this->monotonicAwardPayload(
                            $locked,
                            $rawPayload
                        );
                        [$changes] = $this->diffPayload($locked, $payload);
                        if ($changes !== []) {
                            $locked->forceFill($changes)->save();
                        }
                        $bowler = $locked;
                    });
                }

                $report['lower_award_counts_ignored'] += count($ignoredLowerFields);

                if ($changes === []) {
                    $report['unchanged']++;
                } else {
                    $report['would_update']++;
                    foreach (array_keys($changes) as $field) {
                        $report['field_changes'][$field] = ($report['field_changes'][$field] ?? 0) + 1;
                    }

                    if ($force) {
                        $report['updated']++;
                    }
                }

                if ($force && $this->storeSnapshot($bowler, $profile, $payload)) {
                    $report['snapshots_created']++;
                }

                if (count($report['samples']) < 5) {
                    $report['samples'][] = [
                        'license_no' => $bowler->license_no,
                        'name' => $bowler->name_kanji,
                        'official_win_count' => $payload['official_win_count'] ?? null,
                        'season_trial_win_count' => $payload['season_trial_win_count'] ?? null,
                        'perfect_count' => $payload['perfect_count'] ?? null,
                        'eight_hundred_count' => $payload['eight_hundred_count'] ?? null,
                        'seven_ten_count' => $payload['seven_ten_count'] ?? null,
                        'changed_fields' => array_keys($changes),
                    ];
                }
            } catch (Throwable $e) {
                $report['errors']++;
                if (
                    $this->option('with-history')
                    && ! $this->option('continue-after-history-error')
                ) {
                    $stopAfterPlayer = true;
                }

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

            if ($stopAfterPlayer) {
                $report['stopped_early'] = true;

                break;
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

        return ($report['errors'] + $report['history_errors']) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function nullableYearOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === ''
            ? null
            : max(1900, min(2100, (int) $value));
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
     * The new JPBA profile is authoritative. Legacy values may only raise the
     * three totals, never lower them.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function monotonicAwardPayload(ProBowler $bowler, array $payload): array
    {
        foreach (['perfect_count', 'eight_hundred_count', 'seven_ten_count'] as $field) {
            $payload[$field] = max(
                (int) $bowler->{$field},
                (int) ($payload[$field] ?? 0)
            );
        }
        $payload['award_total_count'] = (int) $payload['perfect_count']
            + (int) $payload['eight_hundred_count']
            + (int) $payload['seven_ten_count'];

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    private function diffPayload(ProBowler $bowler, array $payload): array
    {
        $changes = [];
        $ignoredLowerFields = [];
        $monotonicFields = [
            'perfect_count',
            'eight_hundred_count',
            'seven_ten_count',
            'award_total_count',
        ];

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

            if (
                in_array($field, $monotonicFields, true)
                && (int) $value < (int) $current
            ) {
                $ignoredLowerFields[] = $field;

                continue;
            }

            if ((string) ($current ?? '') === (string) ($value ?? '')) {
                continue;
            }

            $changes[$field] = $value;
        }

        return [$changes, $ignoredLowerFields];
    }

    /**
     * The legacy official profile is a migration source only.  Preserve each
     * distinct response so the new system keeps an auditable baseline after
     * the old site is closed.
     *
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $payload
     */
    private function storeSnapshot(ProBowler $bowler, array $profile, array $payload): bool
    {
        if (! Schema::hasTable('official_profile_stat_snapshots')) {
            return false;
        }

        $snapshotPayload = [
            'title' => $profile['title'] ?? null,
            'summary' => $profile['summary'] ?? [],
            'awards' => $profile['awards'] ?? [],
            'annual_records' => $profile['annual_records'] ?? [],
            'participation_years' => $profile['participation_years'] ?? [],
            'tournament_records' => $profile['tournament_records'] ?? [],
        ];
        $hashPayload = $snapshotPayload;
        unset(
            $hashPayload['summary']['official_profile_imported_at'],
            $hashPayload['summary']['official_profile_import_error']
        );
        $encoded = json_encode(
            $hashPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        $hash = hash('sha256', $encoded === false ? serialize($hashPayload) : $encoded);
        $sourceUrl = (string) (
            $payload['official_profile_url']
            ?? JpbaOfficialPlayerProfileService::BASE_URL
                . '/player1/detail.html?id='
                . rawurlencode((string) $bowler->license_no)
        );

        $snapshot = OfficialProfileStatSnapshot::query()->firstOrCreate(
            [
                'pro_bowler_id' => $bowler->id,
                'payload_hash' => $hash,
            ],
            [
                'license_no' => (string) $bowler->license_no,
                'source_url' => $sourceUrl,
                'captured_at' => now(),
                'perfect_count' => max(
                    (int) $bowler->perfect_count,
                    (int) ($payload['perfect_count'] ?? 0)
                ),
                'eight_hundred_count' => max(
                    (int) $bowler->eight_hundred_count,
                    (int) ($payload['eight_hundred_count'] ?? 0)
                ),
                'seven_ten_count' => max(
                    (int) $bowler->seven_ten_count,
                    (int) ($payload['seven_ten_count'] ?? 0)
                ),
                'payload' => $snapshotPayload,
            ]
        );

        return $snapshot->wasRecentlyCreated;
    }
}
