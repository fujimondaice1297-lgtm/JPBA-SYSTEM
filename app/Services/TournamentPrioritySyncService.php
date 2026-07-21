<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerTitle;
use App\Models\Tournament;
use App\Models\TournamentEntryRule;
use App\Models\TournamentResult;
use App\Models\TournamentSeedPlayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentPrioritySyncService
{
    private const AUTO_NOTE_PREFIX = '[AUTO:entry_rule:';

    public function __construct(
        private readonly ProBowlerSeedService $seedService,
    ) {}

    public function sync(Tournament $tournament): array
    {
        $summary = [
            'annual_seed_count' => $this->annualSeedCount($tournament),
            'rule_count' => 0,
            'candidate_count' => 0,
            'synced_count' => 0,
            'missing_bowler_count' => 0,
        ];

        if (! $tournament->auto_sync_priority_rules) {
            return $summary;
        }

        return DB::transaction(function () use ($tournament, $summary): array {
            TournamentSeedPlayer::query()
                ->where('tournament_id', $tournament->id)
                ->where('note', 'like', self::AUTO_NOTE_PREFIX.'%')
                ->update(['is_active' => false]);

            $rules = $tournament->entryRules()
                ->where('is_active', true)
                ->where('auto_sync', true)
                ->orderByRaw('priority_order is null')
                ->orderBy('priority_order')
                ->orderBy('id')
                ->get();

            $summary['rule_count'] = $rules->count();

            foreach ($rules as $rule) {
                $candidates = $this->candidatesForRule($tournament, $rule);
                $summary['candidate_count'] += $candidates->count();

                foreach ($candidates as $index => $candidate) {
                    $bowler = $candidate['bowler'] ?? null;
                    if (! $bowler instanceof ProBowler) {
                        $summary['missing_bowler_count']++;

                        continue;
                    }

                    $this->seedService->addTournamentSeed(
                        tournament: $tournament,
                        bowler: $bowler,
                        seedSourceType: $candidate['source_type'],
                        attributes: [
                            'source_tournament_id' => $candidate['source_tournament_id'] ?? null,
                            'pro_bowler_title_id' => $candidate['pro_bowler_title_id'] ?? null,
                            'ranking_rank' => $candidate['ranking_rank'] ?? null,
                            'priority_order' => ((int) ($rule->priority_order ?? 9000)) + $index,
                            'display_label' => $candidate['display_label'],
                            'note' => self::AUTO_NOTE_PREFIX.$rule->id.'] '.$candidate['reason'],
                            'is_active' => true,
                        ],
                    );
                    $summary['synced_count']++;
                }
            }

            return $summary;
        });
    }

    private function candidatesForRule(Tournament $tournament, TournamentEntryRule $rule): Collection
    {
        return match ($rule->rule_type) {
            TournamentEntryRule::PAST_CHAMPIONS => $this->pastChampionCandidates($tournament, $rule),
            TournamentEntryRule::CURRENT_YEAR_WINNERS => $this->currentYearWinnerCandidates($tournament),
            TournamentEntryRule::PERMANENT_SEEDS => $this->permanentSeedCandidates($tournament),
            TournamentEntryRule::SOURCE_TOURNAMENT_TOP_N => $this->sourceTournamentCandidates($tournament, $rule),
            default => collect(),
        };
    }

    private function pastChampionCandidates(Tournament $tournament, TournamentEntryRule $rule): Collection
    {
        $seriesId = $rule->source_series_id ?: $tournament->tournament_series_id;
        if (! $seriesId) {
            return collect();
        }

        return ProBowlerTitle::query()
            ->with(['bowler', 'tournament'])
            ->whereHas('tournament', fn ($query) => $query->where('tournament_series_id', $seriesId))
            ->orderBy('year')
            ->get()
            ->filter(fn (ProBowlerTitle $title): bool => $title->isOfficialTitle())
            ->filter(fn (ProBowlerTitle $title): bool => $this->eligibleBowler($title->bowler, $tournament))
            ->unique('pro_bowler_id')
            ->values()
            ->map(fn (ProBowlerTitle $title): array => [
                'bowler' => $title->bowler,
                'source_type' => ProBowlerSeedService::SOURCE_PAST_CHAMPION,
                'source_tournament_id' => $title->tournament_id,
                'pro_bowler_title_id' => $title->id,
                'display_label' => '歴代優勝者',
                'reason' => '大会シリーズの歴代優勝者',
            ]);
    }

    private function currentYearWinnerCandidates(Tournament $tournament): Collection
    {
        $year = (int) ($tournament->year ?: now()->year);

        return ProBowlerTitle::query()
            ->with(['bowler', 'tournament'])
            ->where('year', $year)
            ->where(function ($query) use ($tournament): void {
                $query->whereNull('tournament_id')
                    ->orWhere('tournament_id', '<>', $tournament->id);
            })
            ->orderBy('won_date')
            ->get()
            ->filter(fn (ProBowlerTitle $title): bool => $title->isOfficialTitle())
            ->filter(fn (ProBowlerTitle $title): bool => $this->eligibleBowler($title->bowler, $tournament))
            ->unique('pro_bowler_id')
            ->values()
            ->map(fn (ProBowlerTitle $title): array => [
                'bowler' => $title->bowler,
                'source_type' => ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER,
                'source_tournament_id' => $title->tournament_id,
                'pro_bowler_title_id' => $title->id,
                'display_label' => '当該年度優勝者',
                'reason' => $year.'年度公式大会優勝者',
            ]);
    }

    private function permanentSeedCandidates(Tournament $tournament): Collection
    {
        return ProBowler::query()
            ->whereNotNull('permanent_seed_date')
            ->where('is_active', true)
            ->orderBy('permanent_seed_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProBowler $bowler): bool => $this->eligibleBowler($bowler, $tournament))
            ->values()
            ->map(fn (ProBowler $bowler): array => [
                'bowler' => $bowler,
                'source_type' => ProBowlerSeedService::SOURCE_PERMANENT_SEED,
                'display_label' => '永久シード',
                'reason' => '永久シード資格',
            ]);
    }

    private function sourceTournamentCandidates(
        Tournament $tournament,
        TournamentEntryRule $rule,
    ): Collection {
        if (! $rule->source_tournament_id || ! $rule->max_count) {
            return collect();
        }

        return TournamentResult::query()
            ->with(['bowler', 'player'])
            ->where('tournament_id', $rule->source_tournament_id)
            ->whereBetween('ranking', [1, (int) $rule->max_count])
            ->orderBy('ranking')
            ->get()
            ->map(function (TournamentResult $result) use ($rule): array {
                $bowler = $result->bowler ?: $result->player;
                if (! $bowler && $result->pro_bowler_id) {
                    $bowler = ProBowler::query()->find($result->pro_bowler_id);
                }

                return [
                    'bowler' => $bowler,
                    'source_type' => ProBowlerSeedService::SOURCE_TOURNAMENT_QUALIFIER,
                    'source_tournament_id' => $rule->source_tournament_id,
                    'ranking_rank' => $result->ranking,
                    'display_label' => '前段階通過',
                    'reason' => '前段階大会上位'.$rule->max_count.'名',
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['bowler'] instanceof ProBowler
                && $this->eligibleBowler($candidate['bowler'], $tournament))
            ->values();
    }

    private function eligibleBowler(?ProBowler $bowler, Tournament $tournament): bool
    {
        if (! $bowler || ! $bowler->is_active) {
            return false;
        }

        $gender = strtoupper(trim((string) $tournament->gender));
        if ($gender === 'M') {
            return (int) $bowler->sex === 1 || str_starts_with((string) $bowler->license_no, 'M');
        }
        if ($gender === 'F') {
            return (int) $bowler->sex === 2 || str_starts_with((string) $bowler->license_no, 'F');
        }

        return true;
    }

    private function annualSeedCount(Tournament $tournament): int
    {
        if (! $tournament->include_annual_seeds) {
            return 0;
        }

        $seedYear = (int) ($tournament->year ?: now()->year);
        $query = DB::table('pro_bowler_seed_list_players as players')
            ->join('pro_bowler_seed_lists as lists', 'lists.id', '=', 'players.seed_list_id')
            ->where('lists.seed_year', $seedYear)
            ->where('lists.seed_list_type', 'tournament_seed')
            ->where('lists.is_active', true)
            ->where('players.is_active', true);

        $gender = strtoupper(trim((string) $tournament->gender));
        if (in_array($gender, ['M', 'F'], true)) {
            $query->where(function ($query) use ($gender): void {
                $query->where('lists.gender', $gender)->orWhereNull('lists.gender');
            });
        }

        if ($tournament->annual_seed_rank_limit) {
            $limit = (int) $tournament->annual_seed_rank_limit;
            $query->where(function ($query) use ($limit): void {
                $query->where('players.seed_rank', '<=', $limit)
                    ->orWhere(function ($query) use ($limit): void {
                        $query->whereNull('players.seed_rank')
                            ->where('players.ranking_rank', '<=', $limit);
                    });
            });
        }

        return $query->count();
    }
}
