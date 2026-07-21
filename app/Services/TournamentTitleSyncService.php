<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use App\Models\Tournament;
use App\Models\TournamentResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentTitleSyncService
{
    public function __construct(
        private readonly JpbaOfficialPlayerTitleHistoryService $titleHistoryService,
    ) {}

    public function sync(Tournament $tournament, ?int $year = null): array
    {
        $summary = [
            'eligible' => $this->isEligibleTitleTournament($tournament),
            'winner_count' => 0,
            'created' => 0,
            'linked_existing' => 0,
            'already_linked' => 0,
            'removed_stale' => 0,
            'missing_bowler' => 0,
            'ambiguous_existing' => 0,
            'recalculated' => 0,
        ];

        if (! $summary['eligible']) {
            return $summary;
        }

        $rankingColumn = collect(['ranking', 'rank', 'position', 'placing', 'result_rank', 'order_no'])
            ->first(fn (string $column): bool => Schema::hasColumn('tournament_results', $column));

        if ($rankingColumn === null) {
            return $summary;
        }

        $winners = TournamentResult::query()
            ->where('tournament_id', $tournament->id)
            ->where($rankingColumn, 1)
            ->when($year !== null, function ($query) use ($year): void {
                if (Schema::hasColumn('tournament_results', 'ranking_year')) {
                    $query->where('ranking_year', $year);
                } elseif (Schema::hasColumn('tournament_results', 'year')) {
                    $query->where('year', $year);
                }
            })
            ->get();

        $summary['winner_count'] = $winners->count();
        $affectedBowlerIds = [];
        $winnerBowlerIds = [];

        DB::transaction(function () use ($tournament, $winners, &$summary, &$affectedBowlerIds, &$winnerBowlerIds): void {
            foreach ($winners as $winner) {
                $bowler = $this->resolveBowler($winner);
                if ($bowler === null) {
                    $summary['missing_bowler']++;

                    continue;
                }

                $winnerBowlerIds[] = (int) $bowler->id;

                $titleYear = $this->resolveTitleYear($winner, $tournament);
                $titleName = $this->canonicalTitleName($tournament, $titleYear);
                $wonDate = $this->resolveWonDate($winner, $tournament);
                $source = $this->isSeasonTrial($tournament)
                    ? 'sync_from_results_season_trial'
                    : 'sync_from_results';

                $existing = ProBowlerTitle::query()
                    ->where('pro_bowler_id', $bowler->id)
                    ->where('tournament_id', $tournament->id)
                    ->first();

                if ($existing !== null) {
                    $summary['already_linked']++;
                    $affectedBowlerIds[] = (int) $bowler->id;

                    continue;
                }

                $matches = $this->matchingUnlinkedTitles($bowler, $tournament, $titleName, $titleYear);

                if ($matches->count() > 1) {
                    $summary['ambiguous_existing']++;

                    continue;
                }

                if ($matches->count() === 1) {
                    $matches->first()->forceFill(['tournament_id' => $tournament->id])->save();
                    $summary['linked_existing']++;
                    $affectedBowlerIds[] = (int) $bowler->id;

                    continue;
                }

                ProBowlerTitle::query()->create([
                    'pro_bowler_id' => $bowler->id,
                    'tournament_id' => $tournament->id,
                    'title_name' => $titleName,
                    'tournament_name' => $titleName,
                    'year' => $titleYear,
                    'won_date' => $wonDate,
                    'source' => $source,
                ]);

                $summary['created']++;
                $affectedBowlerIds[] = (int) $bowler->id;
            }

            if ($summary['winner_count'] > 0 && $summary['missing_bowler'] === 0) {
                $staleTitles = ProBowlerTitle::query()
                    ->where('tournament_id', $tournament->id)
                    ->whereNotIn('pro_bowler_id', array_values(array_unique($winnerBowlerIds)))
                    ->get();

                foreach ($staleTitles as $staleTitle) {
                    $affectedBowlerIds[] = (int) $staleTitle->pro_bowler_id;
                    $staleTitle->delete();
                    $summary['removed_stale']++;
                }
            }

            $summary['recalculated'] = $this->refreshBowlerTitleCounters($affectedBowlerIds);
        });

        return $summary;
    }

    public function canonicalTitleName(Tournament $tournament, ?int $year = null): string
    {
        $name = trim((string) $tournament->name);

        if ($this->isSeasonTrial($tournament)) {
            $name = preg_replace('/\s+[A-DＡ-Ｄ]会場(?:\s*[:：].*)?$/u', '', $name) ?: $name;
        }

        return $this->titleHistoryService->titleDisplayName(
            $name,
            $year ?? $this->resolveTournamentYear($tournament),
        );
    }

    public function isEligibleTitleTournament(Tournament $tournament): bool
    {
        if ($this->isSeasonTrial($tournament)) {
            return true;
        }

        if (in_array((string) $tournament->title_category, ['excluded', 'none', 'non_title'], true)) {
            return false;
        }

        if (in_array((string) $tournament->official_type, ['approved', 'approval'], true)) {
            return false;
        }

        $name = preg_replace('/[\s　]+/u', '', (string) $tournament->name) ?: (string) $tournament->name;

        if (preg_match('/(?:予選ラウンド|選抜大会|出場優先順位|順位決定戦|三団体(?:グランドチャンピオン)?(?:大会|ファイナル)|3団体(?:大会|ファイナル))/u', $name) === 1) {
            return false;
        }

        return preg_match('/プレイヤーズドリームマッチ2022[A-Z]$/iu', $name) !== 1;
    }

    private function matchingUnlinkedTitles(
        ProBowler $bowler,
        Tournament $tournament,
        string $titleName,
        int $titleYear,
    ) {
        $seasonTrial = $this->isSeasonTrial($tournament);
        $fingerprint = $this->titleHistoryService->titleFingerprint($titleName);

        return ProBowlerTitle::query()
            ->with('tournament')
            ->where('pro_bowler_id', $bowler->id)
            ->whereNull('tournament_id')
            ->where('year', $titleYear)
            ->get()
            ->filter(function (ProBowlerTitle $title) use ($seasonTrial, $fingerprint): bool {
                if ($title->isSeasonTrialTitle() !== $seasonTrial) {
                    return false;
                }

                foreach ([$title->title_name, $title->tournament_name] as $candidateName) {
                    if ($candidateName !== null
                        && $this->titleHistoryService->titleFingerprint((string) $candidateName) === $fingerprint) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function resolveBowler(TournamentResult $winner): ?ProBowler
    {
        $bowlerId = $winner->getAttribute('pro_bowler_id');
        if ($bowlerId !== null) {
            $bowler = ProBowler::query()->find((int) $bowlerId);
            if ($bowler !== null) {
                return $bowler;
            }
        }

        $licenseNo = $winner->getAttribute('pro_bowler_license_no')
            ?? $winner->getAttribute('license_no');

        if ($licenseNo === null || trim((string) $licenseNo) === '') {
            return null;
        }

        return ProBowler::query()->where('license_no', trim((string) $licenseNo))->first();
    }

    private function resolveTitleYear(TournamentResult $winner, Tournament $tournament): int
    {
        foreach (['ranking_year', 'year'] as $column) {
            $value = $winner->getAttribute($column);
            if ($value !== null && (int) $value > 0) {
                return (int) $value;
            }
        }

        return $this->resolveTournamentYear($tournament);
    }

    private function resolveTournamentYear(Tournament $tournament): int
    {
        if ($tournament->year !== null && (int) $tournament->year > 0) {
            return (int) $tournament->year;
        }

        foreach ([$tournament->start_date, $tournament->end_date] as $date) {
            if ($date !== null && $date !== '') {
                return Carbon::parse($date)->year;
            }
        }

        return (int) now()->format('Y');
    }

    private function resolveWonDate(TournamentResult $winner, Tournament $tournament): ?string
    {
        foreach ([$winner->getAttribute('date'), $tournament->end_date, $tournament->start_date] as $date) {
            if ($date !== null && $date !== '') {
                return Carbon::parse($date)->toDateString();
            }
        }

        return null;
    }

    private function isSeasonTrial(Tournament $tournament): bool
    {
        return (string) $tournament->title_category === 'season_trial'
            || str_contains((string) $tournament->name, 'シーズントライアル');
    }

    private function refreshBowlerTitleCounters(array $bowlerIds): int
    {
        $ids = collect($bowlerIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        foreach ($ids as $bowlerId) {
            $bowler = ProBowler::query()->find($bowlerId);
            if ($bowler === null) {
                continue;
            }

            $titles = ProBowlerTitle::query()
                ->with('tournament')
                ->where('pro_bowler_id', $bowlerId)
                ->get();
            $seasonTrialCount = $titles->filter(fn (ProBowlerTitle $title): bool => $title->isSeasonTrialTitle())->count();
            $officialCount = $titles->count() - $seasonTrialCount;

            $bowler->forceFill([
                'official_win_count' => $officialCount,
                'titles_count' => $officialCount,
                'season_trial_win_count' => $seasonTrialCount,
                'has_title' => $officialCount > 0,
            ])->save();
        }

        return $ids->count();
    }
}
