<?php

namespace App\Console\Commands;

use App\Models\OfficialTitleImportCandidate;
use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use App\Services\JpbaOfficialTitleCandidateService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class ImportOfficialTitleHistoryCommand extends Command
{
    protected $signature = 'jpba:import-official-title-history
        {--url=* : JPBA official tournament page URL(s) to scan}
        {--discover-season-trials= : JPBA tournament index URL used to discover season-trial pages}
        {--license=* : Limit candidates to the specified license number(s)}
        {--force : Store candidates. Without this option, the command is dry-run only}
        {--promote : Promote every matched official-page candidate and raise aggregate counts when needed}
        {--sleep-ms=250 : Sleep between official-site requests}
        {--json : Output JSON report}';

    protected $description = 'Collect and safely promote historical title candidates from current JPBA official tournament pages.';

    public function handle(JpbaOfficialTitleCandidateService $service): int
    {
        $urls = array_values(array_filter(array_map('trim', (array) $this->option('url'))));
        $discoverSeasonTrials = trim((string) $this->option('discover-season-trials'));
        if ($discoverSeasonTrials !== '') {
            try {
                $urls = array_merge($urls, $service->discoverSeasonTrialUrls($discoverSeasonTrials));
            } catch (Throwable $e) {
                $this->error('Season-trial discovery failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $urls = array_values(array_unique($urls));
        if ($urls === []) {
            $this->error('At least one --url or --discover-season-trials is required.');
            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $promote = (bool) $this->option('promote');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $licenses = $this->normalizedLicenses((array) $this->option('license'));

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'pages' => 0,
            'target_urls' => count($urls),
            'candidates' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'stored' => 0,
            'would_store' => 0,
            'promoted' => 0,
            'would_promote' => 0,
            'promotion_blocked' => 0,
            'promotion_state_synced' => 0,
            'aggregate_count_updated' => 0,
            'would_update_aggregate_count' => 0,
            'errors' => 0,
            'samples' => [],
            'blocked_samples' => [],
            'error_samples' => [],
        ];

        $collected = collect();

        foreach ($urls as $url) {
            $report['pages']++;

            try {
                $candidates = $service->fetchCandidates($url);
            } catch (Throwable $e) {
                $report['errors']++;
                $this->pushSample($report['error_samples'], [
                    'url' => $url,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($candidates as $candidate) {
                if ($licenses !== [] && ! in_array((string) ($candidate['license_no'] ?? ''), $licenses, true)) {
                    continue;
                }

                $report['candidates']++;
                ((int) ($candidate['pro_bowler_id'] ?? 0) > 0) ? $report['matched']++ : $report['unmatched']++;
                $collected->push($candidate);

                $this->pushSample($report['samples'], [
                    'license_no' => $candidate['license_no'] ?? null,
                    'name' => $candidate['name_kanji'] ?? null,
                    'title_name' => $candidate['title_name'] ?? null,
                    'title_category' => $candidate['title_category'] ?? null,
                    'year' => $candidate['year'] ?? null,
                    'won_date' => $candidate['won_date'] ?? null,
                    'venue_name' => $candidate['venue_name'] ?? null,
                ]);

                if ($force) {
                    $row = $this->storeCandidate($candidate);
                    $report['stored']++;
                    if ($this->syncExistingPromotionState($row)) {
                        $report['promotion_state_synced']++;
                    }
                } else {
                    $report['would_store']++;
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if ($promote) {
            $promotion = $this->promoteEligible($collected, $force);
            foreach (['promoted', 'would_promote', 'promotion_blocked', 'aggregate_count_updated', 'would_update_aggregate_count'] as $key) {
                $report[$key] += $promotion[$key] ?? 0;
            }
            $report['blocked_samples'] = array_slice($promotion['blocked_samples'] ?? [], 0, 10);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            foreach ($report as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $this->line($key . ': ' . $value);
            }
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<int,string> $values
     * @return array<int,string>
     */
    private function normalizedLicenses(array $values): array
    {
        return array_values(array_filter(array_map(function ($value) {
            $value = strtoupper(trim((string) $value));
            if ($value === '') {
                return null;
            }
            if (preg_match('/^([MF])(\d+)$/', $value, $m) === 1) {
                return $m[1] . str_pad((string) ((int) $m[2]), 8, '0', STR_PAD_LEFT);
            }

            return $value;
        }, $values)));
    }

    /**
     * @param array<int,array<string,mixed>> $samples
     * @param array<string,mixed> $sample
     */
    private function pushSample(array &$samples, array $sample): void
    {
        if (count($samples) >= 10) {
            return;
        }

        $samples[] = $sample;
    }

    /**
     * @param \Illuminate\Support\Collection<int,array<string,mixed>> $collected
     * @return array<string,mixed>
     */
    private function promoteEligible(Collection $collected, bool $force): array
    {
        $report = [
            'promoted' => 0,
            'would_promote' => 0,
            'promotion_blocked' => 0,
            'aggregate_count_updated' => 0,
            'would_update_aggregate_count' => 0,
            'blocked_samples' => [],
        ];

        $affectedIds = $collected
            ->pluck('pro_bowler_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        foreach ($affectedIds as $bowlerId) {
            $bowler = ProBowler::find($bowlerId);
            if (! $bowler) {
                continue;
            }

            foreach (['normal', 'season_trial'] as $category) {
                $candidateRows = $force
                    ? OfficialTitleImportCandidate::query()
                        ->where('pro_bowler_id', $bowlerId)
                        ->where('title_category', $category)
                        ->whereIn('status', ['candidate', 'promoted'])
                        ->orderBy('year')
                        ->orderBy('won_date')
                        ->get()
                    : $collected
                        ->where('pro_bowler_id', $bowlerId)
                        ->where('title_category', $category)
                        ->values();

                if ($candidateRows->isEmpty()) {
                    continue;
                }

                foreach ($candidateRows as $candidate) {
                    if ($force) {
                        $title = $this->createTitleFromCandidate($candidate);
                        $wasPromoted = $candidate->status === 'promoted'
                            && (int) $candidate->promoted_pro_bowler_title_id === (int) $title->id;
                        $candidate->forceFill([
                            'status' => 'promoted',
                            'promoted_pro_bowler_title_id' => $title->id,
                            'error' => null,
                        ])->save();
                        if (! $wasPromoted) {
                            $report['promoted']++;
                        }
                    } elseif (($candidate['status'] ?? 'candidate') !== 'promoted') {
                        $report['would_promote']++;
                    }
                }

                if ($force) {
                    if ($this->refreshBowlerTitleCounter($bowler->refresh(), $category)) {
                        $report['aggregate_count_updated']++;
                    }
                } elseif ($this->aggregateCountNeedsUpdate($bowler, $category, $candidateRows->count())) {
                    $report['would_update_aggregate_count']++;
                }
            }
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function storeCandidate(array $candidate): OfficialTitleImportCandidate
    {
        $row = OfficialTitleImportCandidate::query()
            ->where('candidate_hash', $candidate['candidate_hash'])
            ->first();

        if (! $row) {
            $row = OfficialTitleImportCandidate::query()
                ->where('pro_bowler_id', $candidate['pro_bowler_id'])
                ->where('title_name', $candidate['title_name'])
                ->where('year', $candidate['year'])
                ->where('source_url', $candidate['source_url'])
                ->when(
                    $candidate['won_date'] ?? null,
                    fn ($query, $date) => $query->whereDate('won_date', $date),
                    fn ($query) => $query->whereNull('won_date')
                )
                ->orderByRaw("case when status = 'promoted' then 0 else 1 end")
                ->orderBy('id')
                ->first();
        }

        if (! $row) {
            $row = OfficialTitleImportCandidate::query()
                ->where('pro_bowler_id', $candidate['pro_bowler_id'])
                ->where('title_name', $candidate['title_name'])
                ->where('source_url', $candidate['source_url'])
                ->where('venue_name', $candidate['venue_name'])
                ->orderByRaw("case when status = 'promoted' then 0 else 1 end")
                ->orderBy('id')
                ->first();
        }

        if (! $row) {
            $venueName = $this->normalizeVenueName((string) $candidate['venue_name']);
            $row = OfficialTitleImportCandidate::query()
                ->where('pro_bowler_id', $candidate['pro_bowler_id'])
                ->where('title_name', $candidate['title_name'])
                ->where('source_url', $candidate['source_url'])
                ->orderByRaw("case when status = 'promoted' then 0 else 1 end")
                ->orderBy('id')
                ->get()
                ->first(function (OfficialTitleImportCandidate $existing) use ($venueName) {
                    return $this->normalizeVenueName((string) $existing->venue_name) === $venueName;
                });
        }

        if ($row) {
            if ($row->status === 'promoted' && $row->promoted_pro_bowler_title_id) {
                $candidate['status'] = 'promoted';
                $candidate['promoted_pro_bowler_title_id'] = $row->promoted_pro_bowler_title_id;
                $candidate['error'] = null;
            }
            $row->forceFill($candidate)->save();
            return $this->removeDuplicateCandidates($row->refresh());
        }

        return $this->removeDuplicateCandidates(OfficialTitleImportCandidate::create($candidate));
    }

    private function removeDuplicateCandidates(OfficialTitleImportCandidate $candidate): OfficialTitleImportCandidate
    {
        OfficialTitleImportCandidate::query()
            ->whereKeyNot($candidate->id)
            ->where('pro_bowler_id', $candidate->pro_bowler_id)
            ->where('title_name', $candidate->title_name)
            ->where('year', $candidate->year)
            ->where('source_url', $candidate->source_url)
            ->when(
                $candidate->won_date,
                fn ($query, $date) => $query->whereDate('won_date', $date),
                fn ($query) => $query->whereNull('won_date')
            )
            ->delete();

        return $candidate;
    }

    private function normalizeVenueName(string $venueName): string
    {
        $venueName = preg_replace('/^[\[【\s]+/u', '', trim($venueName)) ?: $venueName;

        return trim($venueName, " \t\n\r\0\x0B[]【】");
    }

    private function currentTitleCount(int $bowlerId, string $category): int
    {
        $query = ProBowlerTitle::query()->where('pro_bowler_id', $bowlerId);

        if ($category === 'season_trial') {
            return (int) $query
                ->where(function ($q) {
                    $q->where('source', 'sync_from_results_season_trial')
                        ->orWhere('title_name', 'like', '%シーズントライアル%')
                        ->orWhere('tournament_name', 'like', '%シーズントライアル%');
                })
                ->count();
        }

        return (int) $query
            ->where(function ($q) {
                $q->whereNull('source')
                    ->orWhere('source', '<>', 'sync_from_results_season_trial');
            })
            ->where('title_name', 'not like', '%シーズントライアル%')
            ->where(function ($q) {
                $q->whereNull('tournament_name')
                    ->orWhere('tournament_name', 'not like', '%シーズントライアル%');
            })
            ->count();
    }

    private function createTitleFromCandidate(OfficialTitleImportCandidate $candidate): ProBowlerTitle
    {
        $source = $candidate->isSeasonTrial()
            ? 'sync_from_results_season_trial'
            : 'official_site_title_import';

        return ProBowlerTitle::firstOrCreate(
            [
                'pro_bowler_id' => $candidate->pro_bowler_id,
                'title_name' => $candidate->title_name,
                'year' => $candidate->year,
                'won_date' => $candidate->won_date,
                'source_url' => $candidate->source_url,
            ],
            [
                'tournament_id' => null,
                'tournament_name' => $candidate->title_name,
                'source' => $source,
                'source_label' => $candidate->source_label,
            ]
        );
    }

    private function syncExistingPromotionState(OfficialTitleImportCandidate $candidate): bool
    {
        if (! $candidate->pro_bowler_id) {
            return false;
        }

        $query = ProBowlerTitle::query()
            ->where('pro_bowler_id', $candidate->pro_bowler_id)
            ->where('title_name', $candidate->title_name)
            ->where('year', $candidate->year)
            ->where('source_url', $candidate->source_url);

        if ($candidate->won_date) {
            $query->whereDate('won_date', $candidate->won_date);
        } else {
            $query->whereNull('won_date');
        }

        $title = $query->first();
        if (! $title && $candidate->won_date) {
            $title = ProBowlerTitle::query()
                ->where('pro_bowler_id', $candidate->pro_bowler_id)
                ->where('title_name', $candidate->title_name)
                ->where('year', $candidate->year)
                ->where('source_url', $candidate->source_url)
                ->whereNull('won_date')
                ->first();

            if ($title) {
                $title->forceFill(['won_date' => $candidate->won_date])->save();
            }
        }

        if (! $title) {
            return false;
        }

        if ($candidate->status === 'promoted'
            && (int) $candidate->promoted_pro_bowler_title_id === (int) $title->id
            && $candidate->error === null) {
            return false;
        }

        $candidate->forceFill([
            'status' => 'promoted',
            'promoted_pro_bowler_title_id' => $title->id,
            'error' => null,
        ])->save();

        return true;
    }

    private function refreshBowlerTitleCounter(ProBowler $bowler, string $category): bool
    {
        $detailCount = $this->currentTitleCount((int) $bowler->id, $category);

        if ($category === 'season_trial') {
            $current = (int) ($bowler->season_trial_win_count ?? 0);
            $count = max($detailCount, $current);
            if ($count === $current) {
                return false;
            }

            $bowler->forceFill(['season_trial_win_count' => $count])->save();
            return true;
        }

        $officialWinCount = (int) ($bowler->official_win_count ?? 0);
        $titlesCount = (int) ($bowler->titles_count ?? 0);
        $count = max($detailCount, $officialWinCount, $titlesCount);
        if ($count === $officialWinCount
            && $count === $titlesCount
            && (bool) $bowler->has_title === ($count > 0)) {
            return false;
        }

        $bowler->forceFill([
            'official_win_count' => $count,
            'titles_count' => $count,
            'has_title' => $count > 0,
        ])->save();

        return true;
    }

    private function aggregateCountNeedsUpdate(ProBowler $bowler, string $category, int $candidateCount): bool
    {
        if ($category === 'season_trial') {
            return $candidateCount > (int) ($bowler->season_trial_win_count ?? 0);
        }

        return $candidateCount > max(
            (int) ($bowler->official_win_count ?? 0),
            (int) ($bowler->titles_count ?? 0)
        );
    }
}
