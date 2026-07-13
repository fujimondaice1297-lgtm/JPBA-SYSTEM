<?php

namespace App\Console\Commands;

use App\Models\OfficialTitleImportCandidate;
use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use App\Services\JpbaOfficialHallOfFameTitleService;
use App\Services\JpbaOfficialPlayerTitleHistoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class ImportOfficialPlayerTitleHistoryCommand extends Command
{
    protected $signature = 'jpba:import-official-player-title-history
        {--license=* : Limit processing to the specified license number(s)}
        {--limit= : Limit the number of gap players}
        {--offset=0 : Skip this many gap players after ordering by license number}
        {--min-year= : Ignore profile history before this year}
        {--max-year= : Ignore profile history after this year}
        {--sleep-ms=250 : Sleep between official-site year-page requests}
        {--refresh : Ignore locally cached official profile pages}
        {--connect-timeout=20 : Official-site connection timeout in seconds}
        {--request-attempts=3 : Attempts per official-site request}
        {--hall-of-fame-index= : JPBA Hall of Fame index used as the authoritative title list for inducted bowlers}
        {--hall-of-fame-only : Process only bowlers with a complete Hall of Fame MainTitle list}
        {--force : Store profile-history candidates. Without this option, the command is dry-run only}
        {--promote : Promote verified candidates without exceeding official aggregate counts}
        {--json : Output JSON report}';

    protected $description = 'Restore missing title details from each JPBA player profile year whose final rank is first.';

    public function handle(
        JpbaOfficialPlayerTitleHistoryService $history,
        JpbaOfficialHallOfFameTitleService $hallOfFame
    ): int {
        $force = (bool) $this->option('force');
        $promote = (bool) $this->option('promote');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $refresh = (bool) $this->option('refresh');
        $history->configureNetwork(
            (int) $this->option('connect-timeout'),
            (int) $this->option('request-attempts')
        );
        $minYear = $this->yearOption('min-year');
        $maxYear = $this->yearOption('max-year');
        $licenses = $this->normalizedLicenses((array) $this->option('license'));
        $hallCandidates = collect();
        $hallReport = ['pages' => 0, 'candidates' => [], 'mismatches' => []];
        $hallIndexUrl = trim((string) $this->option('hall-of-fame-index'));
        if ($hallIndexUrl !== '') {
            try {
                $hallReport = $hallOfFame->fetchCandidates($hallIndexUrl);
                $hallCandidates = collect($hallReport['candidates'])->groupBy('pro_bowler_id');
            } catch (Throwable $e) {
                $this->error('Hall of Fame title discovery failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $bowlers = ProBowler::query()
            ->whereNotNull('license_no')
            ->whereRaw("license_no ~ '^[MF][0-9]+$'")
            ->where(function ($query) {
                $query->where('official_win_count', '>', 0)
                    ->orWhere('season_trial_win_count', '>', 0);
            })
            ->withCount([
                'officialTitles as normal_detail_count',
                'seasonTrialTitles as season_trial_detail_count',
            ])
            ->orderBy('license_no_num')
            ->orderBy('license_no')
            ->get()
            ->filter(function (ProBowler $bowler) use ($licenses, $hallCandidates) {
                if ($licenses !== [] && ! in_array((string) $bowler->license_no, $licenses, true)) {
                    return false;
                }
                if ($this->option('hall-of-fame-only')) {
                    if (! $hallCandidates->has((int) $bowler->id)) {
                        return false;
                    }
                    $candidateCount = collect($hallCandidates->get((int) $bowler->id, []))->count();
                    if ($candidateCount !== $this->expectedCount($bowler, 'normal')) {
                        return false;
                    }
                }

                return $this->expectedCount($bowler, 'normal') > (int) $bowler->normal_detail_count
                    || $this->expectedCount($bowler, 'season_trial') > (int) $bowler->season_trial_detail_count;
            })
            ->values();

        $offset = max(0, (int) $this->option('offset'));
        if ($offset > 0) {
            $bowlers = $bowlers->slice($offset)->values();
        }
        if ($this->option('limit') !== null && $this->option('limit') !== '') {
            $bowlers = $bowlers->take(max(1, (int) $this->option('limit')))->values();
        }

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'target_bowlers' => $bowlers->count(),
            'checked_bowlers' => 0,
            'year_pages' => 0,
            'hall_of_fame_pages' => (int) ($hallReport['pages'] ?? 0),
            'hall_of_fame_candidates' => count($hallReport['candidates'] ?? []),
            'hall_of_fame_mismatches' => count($hallReport['mismatches'] ?? []),
            'profile_wins' => 0,
            'matched_existing_titles' => 0,
            'new_candidates' => 0,
            'stored_candidates' => 0,
            'would_promote' => 0,
            'promoted' => 0,
            'reconciled_categories' => 0,
            'unresolved_categories' => 0,
            'blocked_categories' => 0,
            'errors' => 0,
            'samples' => [],
            'unresolved_samples' => [],
            'blocked_samples' => [],
            'error_samples' => [],
        ];

        foreach ($bowlers as $bowler) {
            $report['checked_bowlers']++;
            $neededCategories = collect(['normal', 'season_trial'])
                ->filter(fn (string $category) => $this->expectedCount($bowler, $category) > $this->detailCount($bowler, $category))
                ->values();

            try {
                $wins = collect();
                $bowlerHallCandidates = collect($hallCandidates->get((int) $bowler->id, []));
                $hallNormalIsComplete = $bowlerHallCandidates->count() === $this->expectedCount($bowler, 'normal');
                $hallLatestYear = $bowlerHallCandidates->isNotEmpty()
                    ? (int) $bowlerHallCandidates->max('year')
                    : null;
                $profileMinimumYear = $hallLatestYear !== null
                    && ! $neededCategories->contains('season_trial')
                    && $bowlerHallCandidates->count() <= $this->expectedCount($bowler, 'normal')
                        ? $hallLatestYear + 1
                        : null;
                $profileNeeded = (! $this->option('hall-of-fame-only') && $neededCategories->contains('season_trial'))
                    || ($neededCategories->contains('normal') && ! $hallNormalIsComplete);

                if ($profileNeeded) {
                    $yearUrls = collect($history->fetchYearUrls((string) $bowler->license_no, $refresh))
                        ->filter(fn (string $url, int $year) => ($minYear === null || $year >= $minYear)
                            && ($maxYear === null || $year <= $maxYear)
                            && ($profileMinimumYear === null || $year >= $profileMinimumYear));
                    $report['year_pages'] += $yearUrls->count();
                    $wins = $wins->concat($history->fetchWinsForYears(
                        $bowler,
                        $yearUrls->all(),
                        $sleepMs,
                        $refresh
                    ));
                }

                if ($bowlerHallCandidates->isNotEmpty()
                    && $bowlerHallCandidates->count() <= $this->expectedCount($bowler, 'normal')
                    && $neededCategories->contains('normal')) {
                    $postHallProfileWins = $wins
                        ->where('title_category', 'normal')
                        ->filter(fn (array $candidate) => (int) $candidate['year'] > $hallLatestYear);
                    $wins = $wins->reject(fn (array $candidate) => $candidate['title_category'] === 'normal')
                        ->concat($bowlerHallCandidates)
                        ->concat($postHallProfileWins);
                }

                $wins = $wins
                    ->filter(fn (array $candidate) => $neededCategories->contains((string) $candidate['title_category']))
                    ->unique('candidate_hash')
                    ->values();
                $report['profile_wins'] += $wins->count();

                foreach ($neededCategories as $category) {
                    $this->reconcileCategory($bowler, $category, $wins, $history, $force, $promote, $report);
                }
            } catch (Throwable $e) {
                $report['errors']++;
                $this->pushSample($report['error_samples'], [
                    'license_no' => $bowler->license_no,
                    'name' => $bowler->name_kanji,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            foreach ($report as $key => $value) {
                if (! is_array($value)) {
                    $this->line($key.': '.$value);
                }
            }
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $wins
     * @param  array<string,mixed>  $report
     */
    private function reconcileCategory(
        ProBowler $bowler,
        string $category,
        Collection $wins,
        JpbaOfficialPlayerTitleHistoryService $history,
        bool $force,
        bool $promote,
        array &$report
    ): void {
        $expected = $this->expectedCount($bowler, $category);
        $existingTitles = $category === 'season_trial'
            ? $bowler->seasonTrialTitles()->get()
            : $bowler->officialTitles()->get();
        $categoryWins = $wins->where('title_category', $category)->values();
        $availableTitles = $existingTitles->keyBy('id');
        $resolved = collect();
        $unmatched = collect();

        foreach ($categoryWins as $candidate) {
            $existing = $this->matchExistingTitle($candidate, $availableTitles, $history);
            if ($existing) {
                $resolved->push(['candidate' => $candidate, 'title' => $existing]);
                $availableTitles->forget($existing->id);
                $report['matched_existing_titles']++;
            } else {
                $unmatched->push($candidate);
            }
        }

        $projectedCount = $existingTitles->count() + $unmatched->count();
        $blocked = $projectedCount > $expected;
        if ($blocked) {
            $report['blocked_categories']++;
            $this->pushSample($report['blocked_samples'], [
                'license_no' => $bowler->license_no,
                'name' => $bowler->name_kanji,
                'category' => $category,
                'expected' => $expected,
                'existing' => $existingTitles->count(),
                'profile_wins' => $categoryWins->count(),
                'projected' => $projectedCount,
            ]);
        }

        foreach ($resolved as $item) {
            if ($force) {
                $this->storeCandidate($item['candidate'], $item['title'], null);
                $report['stored_candidates']++;
            }
        }

        foreach ($unmatched as $candidate) {
            $report['new_candidates']++;
            $error = $blocked ? 'Profile title reconciliation would exceed official aggregate count' : null;
            $row = $force ? $this->storeCandidate($candidate, null, $error) : null;
            if ($force) {
                $report['stored_candidates']++;
            }

            if ($promote && ! $blocked) {
                if ($force) {
                    $title = $this->createTitle($candidate);
                    $row?->forceFill([
                        'status' => 'promoted',
                        'promoted_pro_bowler_title_id' => $title->id,
                        'error' => null,
                    ])->save();
                    $report['promoted']++;
                } else {
                    $report['would_promote']++;
                }
            }
        }

        $finalCount = $promote && ! $blocked ? $projectedCount : $existingTitles->count();
        if ($finalCount === $expected) {
            $report['reconciled_categories']++;
        } elseif (! $blocked) {
            $report['unresolved_categories']++;
            $this->pushSample($report['unresolved_samples'], [
                'license_no' => $bowler->license_no,
                'name' => $bowler->name_kanji,
                'category' => $category,
                'expected' => $expected,
                'existing' => $existingTitles->count(),
                'profile_wins' => $categoryWins->count(),
                'projected' => $projectedCount,
                'remaining_gap' => max(0, $expected - $projectedCount),
            ]);
        }

        $this->pushSample($report['samples'], [
            'license_no' => $bowler->license_no,
            'name' => $bowler->name_kanji,
            'category' => $category,
            'expected' => $expected,
            'existing' => $existingTitles->count(),
            'matched_existing' => $resolved->count(),
            'new' => $unmatched->count(),
            'projected' => $projectedCount,
        ]);
    }

    /**
     * @param  array<string,mixed>  $candidate
     * @param  Collection<int,ProBowlerTitle>  $availableTitles
     */
    private function matchExistingTitle(
        array $candidate,
        Collection $availableTitles,
        JpbaOfficialPlayerTitleHistoryService $history
    ): ?ProBowlerTitle {
        $candidateFingerprint = $history->titleFingerprint((string) $candidate['title_name']);
        $matches = $availableTitles
            ->filter(fn (ProBowlerTitle $title) => (int) $title->year === (int) $candidate['year'])
            ->map(function (ProBowlerTitle $title) use ($candidateFingerprint, $history) {
                $titleFingerprint = $history->titleFingerprint((string) ($title->title_name ?: $title->tournament_name));
                $score = $candidateFingerprint === $titleFingerprint ? 100 : 0;
                if ($score === 0 && min(mb_strlen($candidateFingerprint), mb_strlen($titleFingerprint)) >= 6
                    && (str_contains($candidateFingerprint, $titleFingerprint)
                        || str_contains($titleFingerprint, $candidateFingerprint))) {
                    $score = 90;
                }

                return ['title' => $title, 'score' => $score];
            })
            ->filter(fn (array $match) => $match['score'] >= 90)
            ->sortByDesc('score')
            ->values();

        return $matches->count() === 1 ? $matches->first()['title'] : null;
    }

    /**
     * @param  array<string,mixed>  $candidate
     */
    private function storeCandidate(
        array $candidate,
        ?ProBowlerTitle $title,
        ?string $error
    ): OfficialTitleImportCandidate {
        $row = OfficialTitleImportCandidate::query()->firstOrNew([
            'candidate_hash' => $candidate['candidate_hash'],
        ]);
        $row->forceFill($candidate);

        if ($title) {
            $row->forceFill([
                'status' => 'promoted',
                'promoted_pro_bowler_title_id' => $title->id,
                'error' => null,
            ]);
        } else {
            $row->forceFill([
                'status' => $row->status === 'promoted' ? 'promoted' : 'candidate',
                'error' => $error,
            ]);
        }

        $row->save();

        return $row;
    }

    /**
     * @param  array<string,mixed>  $candidate
     */
    private function createTitle(array $candidate): ProBowlerTitle
    {
        return ProBowlerTitle::firstOrCreate(
            [
                'pro_bowler_id' => $candidate['pro_bowler_id'],
                'title_name' => $candidate['title_name'],
                'year' => $candidate['year'],
                'won_date' => $candidate['won_date'],
                'source_url' => $candidate['source_url'],
            ],
            [
                'tournament_id' => null,
                'tournament_name' => $candidate['title_name'],
                'source' => $candidate['title_category'] === 'season_trial'
                    ? 'sync_from_results_season_trial'
                    : 'official_site_player_history_import',
                'source_label' => $candidate['source_label'],
            ]
        );
    }

    private function expectedCount(ProBowler $bowler, string $category): int
    {
        return $category === 'season_trial'
            ? (int) ($bowler->season_trial_win_count ?? 0)
            : max((int) ($bowler->official_win_count ?? 0), (int) ($bowler->titles_count ?? 0));
    }

    private function detailCount(ProBowler $bowler, string $category): int
    {
        return $category === 'season_trial'
            ? (int) ($bowler->season_trial_detail_count ?? $bowler->seasonTrialTitles()->count())
            : (int) ($bowler->normal_detail_count ?? $bowler->officialTitles()->count());
    }

    /**
     * @param  array<int,string>  $values
     * @return array<int,string>
     */
    private function normalizedLicenses(array $values): array
    {
        return array_values(array_filter(array_map(function ($value) {
            $value = strtoupper(trim((string) $value));
            if (preg_match('/^([MF])(\d+)$/', $value, $matches) === 1) {
                return $matches[1].str_pad((string) ((int) $matches[2]), 8, '0', STR_PAD_LEFT);
            }

            return $value === '' ? null : $value;
        }, $values)));
    }

    private function yearOption(string $name): ?int
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }

        return max(1900, min(2100, (int) $value));
    }

    /**
     * @param  array<int,array<string,mixed>>  $samples
     * @param  array<string,mixed>  $sample
     */
    private function pushSample(array &$samples, array $sample): void
    {
        if (count($samples) < 15) {
            $samples[] = $sample;
        }
    }
}
