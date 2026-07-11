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
        {--license=* : Limit candidates to the specified license number(s)}
        {--force : Store candidates. Without this option, the command is dry-run only}
        {--promote : Promote only bowlers whose candidate detail count exactly matches their aggregate title count}
        {--sleep-ms=250 : Sleep between official-site requests}
        {--json : Output JSON report}';

    protected $description = 'Collect and safely promote historical title candidates from current JPBA official tournament pages.';

    public function handle(JpbaOfficialTitleCandidateService $service): int
    {
        $urls = array_values(array_filter(array_map('trim', (array) $this->option('url'))));
        if ($urls === []) {
            $this->error('At least one --url is required.');
            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $promote = (bool) $this->option('promote');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $licenses = $this->normalizedLicenses((array) $this->option('license'));

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'pages' => 0,
            'candidates' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'stored' => 0,
            'would_store' => 0,
            'promoted' => 0,
            'would_promote' => 0,
            'promotion_blocked' => 0,
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
                    OfficialTitleImportCandidate::updateOrCreate(
                        ['candidate_hash' => $candidate['candidate_hash']],
                        $candidate
                    );
                    $report['stored']++;
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
            foreach (['promoted', 'would_promote', 'promotion_blocked'] as $key) {
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
                $expected = $category === 'season_trial'
                    ? (int) ($bowler->season_trial_win_count ?? 0)
                    : (int) ($bowler->official_win_count ?? $bowler->titles_count ?? 0);

                if ($expected <= 0) {
                    continue;
                }

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

                $currentTitleCount = $this->currentTitleCount($bowlerId, $category);
                $candidateCount = $candidateRows->count();

                if ($currentTitleCount === $expected) {
                    continue;
                }

                if ($currentTitleCount > 0 || $candidateCount !== $expected) {
                    $report['promotion_blocked']++;
                    $this->pushSample($report['blocked_samples'], [
                        'license_no' => $bowler->license_no,
                        'name' => $bowler->name_kanji,
                        'category' => $category,
                        'expected' => $expected,
                        'candidate_count' => $candidateCount,
                        'current_title_count' => $currentTitleCount,
                    ]);
                    continue;
                }

                foreach ($candidateRows as $candidate) {
                    if ($force) {
                        $title = $this->createTitleFromCandidate($candidate);
                        $candidate->forceFill([
                            'status' => 'promoted',
                            'promoted_pro_bowler_title_id' => $title->id,
                            'error' => null,
                        ])->save();
                        $report['promoted']++;
                    } else {
                        $report['would_promote']++;
                    }
                }

                if ($force) {
                    $this->refreshBowlerTitleCounter($bowler);
                }
            }
        }

        return $report;
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

    private function refreshBowlerTitleCounter(ProBowler $bowler): void
    {
        $officialTitleCount = $this->currentTitleCount((int) $bowler->id, 'normal');
        $aggregateCount = (int) ($bowler->official_win_count ?? $bowler->titles_count ?? 0);
        $count = max($officialTitleCount, $aggregateCount);

        $bowler->forceFill([
            'titles_count' => $count,
            'has_title' => $count > 0,
        ])->save();
    }
}
