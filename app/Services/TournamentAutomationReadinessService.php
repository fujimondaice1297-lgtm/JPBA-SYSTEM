<?php

namespace App\Services;

use App\Models\Tournament;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentAutomationReadinessService
{
    public function __construct(
        private readonly TournamentResultCarryService $carryService
    ) {
    }

    public function build(Tournament $tournament): array
    {
        $tournamentId = (int) $tournament->id;
        $tournamentYear = $this->resolveTournamentYear($tournament);
        $gender = $this->normalizeGender($tournament->gender ?? null);

        $entries = $this->entrySummary($tournamentId);
        $scores = $this->scoreSummary($tournamentId);
        $snapshots = $this->snapshotSummary($tournamentId);
        $results = $this->resultSummary($tournamentId);
        $awards = $this->awardSummary($tournamentId);
        $titles = $this->titleSummary($tournamentId);
        $seeds = $this->seedSummary($tournamentId, $tournamentYear, $gender);
        $scoreFlow = $this->scoreFlowSummary($tournament);
        $diagnostics = $this->diagnostics($entries, $scores, $snapshots, $results, $awards, $titles, $seeds);

        return [
            'tournament_year' => $tournamentYear,
            'gender' => $gender,
            'entries' => $entries,
            'scores' => $scores,
            'snapshots' => $snapshots,
            'results' => $results,
            'awards' => $awards,
            'titles' => $titles,
            'seeds' => $seeds,
            'score_flow' => $scoreFlow,
            'diagnostics' => $diagnostics,
            'readiness' => $this->readiness($entries, $scores, $snapshots, $results, $awards, $titles, $seeds, $diagnostics),
        ];
    }

    private function readiness(
        array $entries,
        array $scores,
        array $snapshots,
        array $results,
        array $awards,
        array $titles,
        array $seeds,
        array $diagnostics
    ): array {
        $hasFinalResults = $results['final_results_count'] > 0;
        $hasDistribution = $awards['point_distribution_count'] > 0 || $awards['prize_distribution_count'] > 0;
        $hasAppliedAward = $awards['point_applied_rows'] > 0 || $awards['prize_applied_rows'] > 0;
        $hasSeedSource = $seeds['annual_seed_player_count'] > 0 || $seeds['tournament_seed_player_count'] > 0;
        $hasScoreGaps = $diagnostics['score_entry_gap'] > 0 || ! empty($diagnostics['incomplete_stage_rows']);
        $hasFinalSyncGap = $diagnostics['final_sync_gap'] !== 0;
        $hasAwardPending = $diagnostics['award_pending'];
        $hasTitlePending = $diagnostics['title_pending'];

        return [
            'entries' => $entries['entry_count'] > 0 ? 'done' : 'waiting',
            'scores' => $scores['score_count'] > 0 ? ($hasScoreGaps ? 'warning' : 'done') : 'waiting',
            'snapshots' => $snapshots['full_final_snapshot'] !== null
                ? ($hasFinalSyncGap ? 'warning' : 'done')
                : ($snapshots['current_snapshot_count'] > 0 ? 'warning' : 'waiting'),
            'results' => $hasFinalResults ? ($hasFinalSyncGap ? 'warning' : 'done') : 'waiting',
            'awards' => ! $hasFinalResults
                ? 'waiting'
                : ($hasDistribution ? ($hasAppliedAward ? ($hasAwardPending ? 'warning' : 'done') : 'ready') : 'warning'),
            'titles' => ! $hasFinalResults ? 'waiting' : ($titles['title_count'] > 0 ? ($hasTitlePending ? 'warning' : 'done') : 'ready'),
            'seeds' => $hasSeedSource ? 'done' : 'warning',
            'pdf' => $hasFinalResults ? 'ready' : 'waiting',
        ];
    }

    private function entrySummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_entries')) {
            return [
                'entry_count' => 0,
                'checked_in_count' => 0,
            ];
        }

        $base = DB::table('tournament_entries')->where('tournament_id', $tournamentId);

        return [
            'entry_count' => (int) (clone $base)->where('status', 'entry')->count(),
            'checked_in_count' => Schema::hasColumn('tournament_entries', 'checked_in_at')
                ? (int) (clone $base)->whereNotNull('checked_in_at')->count()
                : 0,
        ];
    }

    private function scoreSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('game_scores')) {
            return [
                'score_count' => 0,
                'scored_player_count' => 0,
                'stage_rows' => [],
                'stage_settings' => [],
                'incomplete_stage_rows' => [],
                'missing_player_count' => 0,
                'missing_player_rows' => [],
            ];
        }

        $base = DB::table('game_scores')->where('tournament_id', $tournamentId);
        $stageSettings = $this->stageSettings($tournamentId);
        $missingScorePlayers = $this->missingScorePlayers($tournamentId);

        $identityRows = (clone $base)
            ->select(['tournament_participant_id', 'pro_bowler_id', 'license_number', 'name'])
            ->get();

        $scoredPlayerCount = $identityRows
            ->map(function ($row): string {
                if (! empty($row->tournament_participant_id)) {
                    return 'participant:' . $row->tournament_participant_id;
                }

                if (! empty($row->pro_bowler_id)) {
                    return 'pro:' . $row->pro_bowler_id;
                }

                $license = trim((string) ($row->license_number ?? ''));
                if ($license !== '') {
                    return 'license:' . strtoupper($license);
                }

                return 'name:' . trim((string) ($row->name ?? ''));
            })
            ->filter(fn (string $key): bool => $key !== 'name:')
            ->unique()
            ->count();

        $stageRows = (clone $base)
            ->select([
                'stage',
                DB::raw('COUNT(*) as rows_count'),
                DB::raw('COUNT(DISTINCT game_number) as games_count'),
                DB::raw('MAX(game_number) as max_game'),
            ])
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn ($row): array => [
                'stage' => trim((string) ($row->stage ?? '')) ?: '未設定',
                'rows_count' => (int) $row->rows_count,
                'games_count' => (int) $row->games_count,
                'max_game' => (int) $row->max_game,
            ])
            ->all();

        $stageRowsByStage = collect($stageRows)->keyBy('stage');
        $incompleteStageRows = collect($stageSettings)
            ->filter(function (array $setting) use ($stageRowsByStage): bool {
                if (! $setting['enabled'] || $setting['total_games'] <= 0) {
                    return false;
                }

                $actual = $stageRowsByStage->get($setting['stage']);

                return $actual === null || (int) ($actual['max_game'] ?? 0) < $setting['total_games'];
            })
            ->map(function (array $setting) use ($stageRowsByStage): array {
                $actual = $stageRowsByStage->get($setting['stage']);

                return [
                    'stage' => $setting['stage'],
                    'expected_games' => $setting['total_games'],
                    'actual_max_game' => $actual ? (int) ($actual['max_game'] ?? 0) : 0,
                    'rows_count' => $actual ? (int) ($actual['rows_count'] ?? 0) : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'score_count' => (int) (clone $base)->count(),
            'scored_player_count' => (int) $scoredPlayerCount,
            'stage_rows' => $stageRows,
            'stage_settings' => $stageSettings,
            'incomplete_stage_rows' => $incompleteStageRows,
            'missing_player_count' => $missingScorePlayers['count'],
            'missing_player_rows' => $missingScorePlayers['rows'],
        ];
    }

    private function scoreFlowSummary(Tournament $tournament): array
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? 'legacy_standard')) ?: 'legacy_standard';
        $carryPreset = $this->carryService->canonicalPresetKey((string) ($tournament->result_carry_preset ?? 'default'));
        $carryOptions = $this->carryService->presetOptions();
        $carrySettings = $this->carryService->settingsForTournament($tournament);

        return [
            'flow' => [
                'type' => $flowType,
                'label' => $this->resultFlowLabel($flowType),
                'carry_preset' => $carryPreset,
                'carry_label' => $carryOptions[$carryPreset]['label'] ?? $carryPreset,
                'carry_description' => $carryOptions[$carryPreset]['description'] ?? '',
            ],
            'ranking_rule_rows' => $this->rankingRuleRows(),
            'qualifier_rows' => $this->qualifierRows($tournament, $flowType),
            'carry_source_rows' => $this->carrySourceRows($carrySettings),
            'score_scope_rows' => $this->scoreScopeRows((int) $tournament->id),
            'snapshot_scope_rows' => $this->snapshotScopeRows((int) $tournament->id),
            'confirmation_rows' => $this->confirmationRows(),
        ];
    }

    private function rankingRuleRows(): array
    {
        return [
            [
                'label' => '同スコア時',
                'value' => '合計ピン → 対象ステージのローハイ小 → 対象ステージ合計 → ID順',
            ],
            [
                'label' => '通過ライン',
                'value' => '速報画面の通過人数指定と、大会設定の方式別進出人数を確認',
            ],
            [
                'label' => '集計範囲',
                'value' => 'carry設定、シフト指定、男女指定を `game_scores` から計算',
            ],
        ];
    }

    private function qualifierRows(Tournament $tournament, string $flowType): array
    {
        return [
            [
                'label' => 'ラウンドロビン',
                'enabled' => str_contains($flowType, '_rr_'),
                'qualifier_count' => (int) ($tournament->round_robin_qualifier_count ?? 0),
                'details' => '勝ち ' . (int) ($tournament->round_robin_win_bonus ?? 30)
                    . 'P / 引分 ' . (int) ($tournament->round_robin_tie_bonus ?? 15) . 'P',
            ],
            [
                'label' => 'トーナメント',
                'enabled' => str_contains($flowType, 'single_elimination'),
                'qualifier_count' => (int) ($tournament->single_elimination_qualifier_count ?? 0),
                'details' => 'seed: ' . (trim((string) ($tournament->single_elimination_seed_source_result_code ?? '')) ?: '未設定'),
            ],
            [
                'label' => 'シュートアウト',
                'enabled' => str_contains($flowType, 'shootout'),
                'qualifier_count' => (int) ($tournament->shootout_qualifier_count ?? 0),
                'details' => 'seed: ' . (trim((string) ($tournament->shootout_seed_source_result_code ?? '')) ?: '未設定'),
            ],
        ];
    }

    private function carrySourceRows(array $carrySettings): array
    {
        $rows = [];

        foreach ($carrySettings as $resultCode => $setting) {
            if (! is_array($setting)) {
                continue;
            }

            $sourceStages = $setting['source_stages'] ?? [];
            if (! is_array($sourceStages)) {
                $sourceStages = [];
            }

            $sourceStages = array_values(array_filter(array_map(
                fn ($stage): string => trim((string) $stage),
                $sourceStages
            ), fn (string $stage): bool => $stage !== ''));

            $rows[] = [
                'result_code' => (string) $resultCode,
                'result_label' => $this->resultCodeLabel((string) $resultCode),
                'source_stages' => $sourceStages,
                'source_label' => $sourceStages !== [] ? implode(' + ', $sourceStages) : '未設定',
            ];
        }

        return $rows;
    }

    private function scoreScopeRows(int $tournamentId): array
    {
        if (! Schema::hasTable('game_scores')) {
            return [];
        }

        $hasGender = Schema::hasColumn('game_scores', 'gender');
        $hasShift = Schema::hasColumn('game_scores', 'shift');

        $query = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->select([
                'stage',
                DB::raw('COUNT(*) as rows_count'),
                DB::raw('COUNT(DISTINCT game_number) as games_count'),
                DB::raw('MAX(game_number) as max_game'),
            ]);

        if ($hasGender) {
            $query->addSelect('gender')->groupBy('gender');
        } else {
            $query->selectRaw('NULL as gender');
        }

        if ($hasShift) {
            $query->addSelect('shift')->groupBy('shift');
        } else {
            $query->selectRaw('NULL as shift');
        }

        return $query
            ->groupBy('stage')
            ->orderBy('stage')
            ->orderBy('gender')
            ->orderBy('shift')
            ->get()
            ->map(fn ($row): array => [
                'stage' => trim((string) ($row->stage ?? '')) ?: '未設定',
                'gender' => trim((string) ($row->gender ?? '')),
                'gender_label' => $this->genderLabel($row->gender ?? null),
                'shift' => trim((string) ($row->shift ?? '')),
                'shift_label' => $this->blankLabel($row->shift ?? null),
                'rows_count' => (int) $row->rows_count,
                'games_count' => (int) $row->games_count,
                'max_game' => (int) $row->max_game,
            ])
            ->all();
    }

    private function snapshotScopeRows(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_result_snapshots')) {
            return [];
        }

        $snapshots = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournamentId)
            ->where('is_current', true)
            ->orderBy('result_code')
            ->orderBy('gender')
            ->orderBy('shift')
            ->orderByDesc('reflected_at')
            ->get([
                'id',
                'result_code',
                'result_name',
                'gender',
                'shift',
                'is_final',
                'reflected_at',
            ]);

        if ($snapshots->isEmpty()) {
            return [];
        }

        $rowCounts = [];

        if (Schema::hasTable('tournament_result_snapshot_rows')) {
            $rowCounts = DB::table('tournament_result_snapshot_rows')
                ->whereIn('snapshot_id', $snapshots->pluck('id')->all())
                ->select(['snapshot_id', DB::raw('COUNT(*) as rows_count')])
                ->groupBy('snapshot_id')
                ->pluck('rows_count', 'snapshot_id')
                ->map(fn ($count): int => (int) $count)
                ->all();
        }

        return $snapshots
            ->map(fn ($snapshot): array => [
                'id' => (int) $snapshot->id,
                'result_code' => (string) $snapshot->result_code,
                'result_name' => (string) $snapshot->result_name,
                'gender' => trim((string) ($snapshot->gender ?? '')),
                'gender_label' => $this->genderLabel($snapshot->gender ?? null),
                'shift' => trim((string) ($snapshot->shift ?? '')),
                'shift_label' => $this->blankLabel($snapshot->shift ?? null),
                'is_final' => (bool) $snapshot->is_final,
                'reflected_at' => $snapshot->reflected_at,
                'rows_count' => (int) ($rowCounts[$snapshot->id] ?? 0),
            ])
            ->all();
    }

    private function confirmationRows(): array
    {
        return [
            [
                'step' => '取込',
                'from' => '紙/PDF/CSV/OCR',
                'to' => 'score_import_rows',
                'trigger' => '一時取込',
                'note' => '警告・候補を確認してから確定',
            ],
            [
                'step' => '確定',
                'from' => 'score_import_rows',
                'to' => 'game_scores',
                'trigger' => '確定反映ボタン',
                'note' => '`game_scores` が速報の正本',
            ],
            [
                'step' => '正式反映',
                'from' => 'game_scores',
                'to' => 'tournament_result_snapshots',
                'trigger' => '正式成績反映ボタン',
                'note' => '男女/シフト条件付きはsnapshot止まり',
            ],
            [
                'step' => '公開確定',
                'from' => 'final snapshot',
                'to' => 'tournament_results / titles / PDF',
                'trigger' => '同期・反映ボタン',
                'note' => '全体finalのみ最終成績へ同期',
            ],
        ];
    }

    private function missingScorePlayers(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_entries') || ! Schema::hasTable('game_scores')) {
            return [
                'count' => 0,
                'rows' => [],
            ];
        }

        $scoreKeys = [];
        DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->select(['pro_bowler_id', 'license_number', 'name'])
            ->get()
            ->each(function ($row) use (&$scoreKeys): void {
                foreach ($this->identityKeys(
                    $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                    $row->license_number ?? null,
                    $row->name ?? null
                ) as $key) {
                    $scoreKeys[$key] = true;
                }
            });

        $entryRows = DB::table('tournament_entries as entries')
            ->leftJoin('pro_bowlers as bowlers', 'bowlers.id', '=', 'entries.pro_bowler_id')
            ->where('entries.tournament_id', $tournamentId)
            ->where('entries.status', 'entry')
            ->select([
                'entries.id',
                'entries.pro_bowler_id',
                'entries.shift',
                'entries.lane',
                'bowlers.license_no',
                'bowlers.name_kanji',
                'bowlers.name_kana',
            ])
            ->orderBy('entries.id')
            ->get();

        $missingRows = [];

        foreach ($entryRows as $entry) {
            $keys = $this->identityKeys(
                $entry->pro_bowler_id ? (int) $entry->pro_bowler_id : null,
                $entry->license_no ?? null,
                $entry->name_kanji ?? null
            );

            $hasScore = false;
            foreach ($keys as $key) {
                if (isset($scoreKeys[$key])) {
                    $hasScore = true;
                    break;
                }
            }

            if ($hasScore) {
                continue;
            }

            $licenseNo = trim((string) ($entry->license_no ?? ''));

            $missingRows[] = [
                'entry_id' => (int) $entry->id,
                'pro_bowler_id' => $entry->pro_bowler_id ? (int) $entry->pro_bowler_id : null,
                'license_no' => $licenseNo,
                'license_tail' => $this->licenseTail($licenseNo),
                'name' => trim((string) ($entry->name_kanji ?? $entry->name_kana ?? '')),
                'shift' => trim((string) ($entry->shift ?? '')),
                'lane' => $entry->lane,
            ];
        }

        return [
            'count' => count($missingRows),
            'rows' => array_slice($missingRows, 0, 50),
        ];
    }

    private function snapshotSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_result_snapshots')) {
            return [
                'current_snapshot_count' => 0,
                'current_final_snapshot_count' => 0,
                'full_final_snapshot' => null,
                'full_final_row_count' => 0,
            ];
        }

        $currentBase = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournamentId)
            ->where('is_current', true);

        $fullFinalSnapshot = (clone $currentBase)
            ->where('is_final', true)
            ->whereNull('gender')
            ->whereNull('shift')
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->first();

        $fullFinalRowCount = $fullFinalSnapshot && Schema::hasTable('tournament_result_snapshot_rows')
            ? (int) DB::table('tournament_result_snapshot_rows')
                ->where('snapshot_id', (int) $fullFinalSnapshot->id)
                ->count()
            : 0;

        return [
            'current_snapshot_count' => (int) (clone $currentBase)->count(),
            'current_final_snapshot_count' => (int) (clone $currentBase)->where('is_final', true)->count(),
            'full_final_snapshot' => $fullFinalSnapshot,
            'full_final_row_count' => $fullFinalRowCount,
        ];
    }

    private function resultSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('tournament_results')) {
            return [
                'final_results_count' => 0,
                'winner_count' => 0,
            ];
        }

        $base = DB::table('tournament_results')->where('tournament_id', $tournamentId);
        $rankColumn = $this->firstExistingColumn('tournament_results', [
            'ranking',
            'rank',
            'position',
            'placing',
            'result_rank',
            'order_no',
        ]);

        return [
            'final_results_count' => (int) (clone $base)->count(),
            'winner_count' => $rankColumn !== null
                ? (int) (clone $base)->where($rankColumn, 1)->count()
                : 0,
        ];
    }

    private function awardSummary(int $tournamentId): array
    {
        $resultBase = Schema::hasTable('tournament_results')
            ? DB::table('tournament_results')->where('tournament_id', $tournamentId)
            : null;

        $pointColumns = $this->existingColumns('tournament_results', ['points', 'award_points', 'step_points']);
        $prizeColumns = $this->existingColumns('tournament_results', ['prize_money']);
        $rankColumn = $this->firstExistingColumn('tournament_results', [
            'ranking',
            'rank',
            'position',
            'placing',
            'result_rank',
            'order_no',
        ]);
        $pointTargetRows = $resultBase !== null && $rankColumn !== null && Schema::hasTable('point_distributions')
            ? $this->countResultRowsForDistributionRanks($resultBase, $rankColumn, 'point_distributions', $tournamentId)
            : 0;
        $prizeTargetRows = $resultBase !== null && $rankColumn !== null && Schema::hasTable('prize_distributions')
            ? $this->countResultRowsForDistributionRanks($resultBase, $rankColumn, 'prize_distributions', $tournamentId)
            : 0;

        return [
            'point_distribution_count' => Schema::hasTable('point_distributions')
                ? (int) DB::table('point_distributions')->where('tournament_id', $tournamentId)->count()
                : 0,
            'prize_distribution_count' => Schema::hasTable('prize_distributions')
                ? (int) DB::table('prize_distributions')->where('tournament_id', $tournamentId)->count()
                : 0,
            'point_applied_rows' => $resultBase !== null && $pointColumns !== []
                ? $this->countRowsWithAnyPositiveColumn($resultBase, $pointColumns)
                : 0,
            'prize_applied_rows' => $resultBase !== null && $prizeColumns !== []
                ? $this->countRowsWithAnyPositiveColumn($resultBase, $prizeColumns)
                : 0,
            'point_target_rows' => $pointTargetRows,
            'prize_target_rows' => $prizeTargetRows,
        ];
    }

    private function titleSummary(int $tournamentId): array
    {
        if (! Schema::hasTable('pro_bowler_titles')) {
            return [
                'title_count' => 0,
            ];
        }

        return [
            'title_count' => (int) DB::table('pro_bowler_titles')
                ->where('tournament_id', $tournamentId)
                ->count(),
        ];
    }

    private function seedSummary(int $tournamentId, ?int $tournamentYear, ?string $gender): array
    {
        $tournamentSeedCount = Schema::hasTable('tournament_seed_players')
            ? (int) DB::table('tournament_seed_players')
                ->where('tournament_id', $tournamentId)
                ->where('is_active', true)
                ->count()
            : 0;

        $annualSeedCount = 0;

        if (
            Schema::hasTable('pro_bowler_seed_lists')
            && Schema::hasTable('pro_bowler_seed_list_players')
        ) {
            $annualQuery = DB::table('pro_bowler_seed_list_players as players')
                ->join('pro_bowler_seed_lists as lists', 'lists.id', '=', 'players.seed_list_id')
                ->where('lists.is_active', true)
                ->where('players.is_active', true);

            if ($tournamentYear !== null) {
                $annualQuery->where('lists.seed_year', $tournamentYear);
            }

            if ($gender !== null) {
                $annualQuery->where('lists.gender', $gender);
            }

            $annualSeedCount = (int) $annualQuery->count();
        }

        return [
            'annual_seed_player_count' => $annualSeedCount,
            'tournament_seed_player_count' => $tournamentSeedCount,
            'active_seed_source_count' => $annualSeedCount + $tournamentSeedCount,
        ];
    }

    private function diagnostics(
        array $entries,
        array $scores,
        array $snapshots,
        array $results,
        array $awards,
        array $titles,
        array $seeds
    ): array {
        $scoreEntryGap = (int) ($scores['missing_player_count'] ?? 0);
        $incompleteStageRows = $scores['incomplete_stage_rows'] ?? [];

        $fullFinalRowCount = (int) ($snapshots['full_final_row_count'] ?? 0);
        $finalResultsCount = (int) ($results['final_results_count'] ?? 0);
        $finalSyncGap = $fullFinalRowCount > 0
            ? $fullFinalRowCount - $finalResultsCount
            : 0;

        $awardPending = $finalResultsCount > 0 && (
            (
                (int) ($awards['point_target_rows'] ?? 0) > 0
                && (int) ($awards['point_applied_rows'] ?? 0) < (int) ($awards['point_target_rows'] ?? 0)
            )
            || (
                (int) ($awards['prize_target_rows'] ?? 0) > 0
                && (int) ($awards['prize_applied_rows'] ?? 0) < (int) ($awards['prize_target_rows'] ?? 0)
            )
        );

        $titlePending = (int) ($results['winner_count'] ?? 0) > 0
            && (int) ($titles['title_count'] ?? 0) < (int) ($results['winner_count'] ?? 0);

        $seedMissing = (int) ($seeds['active_seed_source_count'] ?? 0) === 0;

        $issues = [];

        if ((int) $entries['entry_count'] === 0) {
            $issues[] = [
                'severity' => 'warning',
                'label' => 'エントリー未登録',
                'message' => '参加者がまだ登録されていません。',
            ];
        } elseif ($scoreEntryGap > 0 && (int) $scores['score_count'] > 0) {
            $issues[] = [
                'severity' => 'warning',
                'label' => 'スコア未入力候補',
                'message' => 'エントリー人数に対して、スコア入力済み選手が ' . $scoreEntryGap . ' 名少ない可能性があります。',
            ];
        }

        foreach ($incompleteStageRows as $row) {
            $issues[] = [
                'severity' => 'warning',
                'label' => 'ステージ未完了',
                'message' => $row['stage'] . ' は設定 ' . $row['expected_games'] . 'G に対して、入力は ' . $row['actual_max_game'] . 'G までです。',
            ];
        }

        if ($finalSyncGap !== 0) {
            $label = $finalSyncGap > 0 ? '最終成績不足' : '最終成績過多';
            $issues[] = [
                'severity' => 'warning',
                'label' => $label,
                'message' => '全体final snapshot行数 ' . $fullFinalRowCount . ' 件に対して、最終成績は ' . $finalResultsCount . ' 件です。',
            ];
        }

        if ($awardPending) {
            $issues[] = [
                'severity' => 'info',
                'label' => '賞金・ポイント未反映',
                'message' => '配分設定に対して、最終成績へ未反映の行が残っている可能性があります。',
            ];
        }

        if ($titlePending) {
            $issues[] = [
                'severity' => 'info',
                'label' => 'タイトル同期待ち',
                'message' => '優勝者行に対して、タイトル履歴の登録件数が不足しています。',
            ];
        }

        if ($seedMissing) {
            $issues[] = [
                'severity' => 'info',
                'label' => 'シード未設定',
                'message' => '年度別シードまたは大会別追加シードが見つかりません。',
            ];
        }

        return [
            'score_entry_gap' => $scoreEntryGap,
            'missing_score_player_rows' => $scores['missing_player_rows'] ?? [],
            'incomplete_stage_rows' => $incompleteStageRows,
            'final_sync_gap' => $finalSyncGap,
            'award_pending' => $awardPending,
            'title_pending' => $titlePending,
            'seed_missing' => $seedMissing,
            'issues' => $issues,
            'issue_count' => count($issues),
        ];
    }

    private function stageSettings(int $tournamentId): array
    {
        if (! Schema::hasTable('stage_settings')) {
            return [];
        }

        return DB::table('stage_settings')
            ->where('tournament_id', $tournamentId)
            ->orderBy('stage')
            ->get(['stage', 'total_games', 'enabled'])
            ->map(fn ($row): array => [
                'stage' => trim((string) ($row->stage ?? '')) ?: '未設定',
                'total_games' => max(0, (int) ($row->total_games ?? 0)),
                'enabled' => (bool) ($row->enabled ?? false),
            ])
            ->all();
    }

    private function identityKeys(?int $proBowlerId, ?string $licenseNo, ?string $name): array
    {
        $keys = [];

        if ($proBowlerId !== null && $proBowlerId > 0) {
            $keys[] = 'pro:' . $proBowlerId;
        }

        $licenseKey = $this->normalizeIdentityText($licenseNo);
        if ($licenseKey !== '') {
            $keys[] = 'license:' . $licenseKey;
        }

        $licenseTail = $this->licenseTail($licenseNo);
        if ($licenseTail !== '') {
            $keys[] = 'tail:' . $licenseTail;
        }

        $nameKey = $this->normalizeIdentityText($name);
        if ($nameKey !== '') {
            $keys[] = 'name:' . $nameKey;
        }

        return array_values(array_unique($keys));
    }

    private function normalizeIdentityText(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim((string) $value)) ?? trim((string) $value));
    }

    private function licenseTail(?string $licenseNo): string
    {
        $licenseNo = $this->normalizeIdentityText($licenseNo);
        if ($licenseNo === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $licenseNo) ?? '';
        if ($digits !== '') {
            return substr(str_pad($digits, 4, '0', STR_PAD_LEFT), -4);
        }

        return substr($licenseNo, -4);
    }

    private function countRowsWithAnyPositiveColumn($baseQuery, array $columns): int
    {
        $query = clone $baseQuery;

        $query->where(function ($inner) use ($columns): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, '>', 0);
            }
        });

        return (int) $query->count();
    }

    private function countResultRowsForDistributionRanks($resultBase, string $rankColumn, string $distributionTable, int $tournamentId): int
    {
        $ranks = DB::table($distributionTable)
            ->where('tournament_id', $tournamentId)
            ->pluck('rank')
            ->map(fn ($rank): int => (int) $rank)
            ->filter(fn (int $rank): bool => $rank > 0)
            ->unique()
            ->values()
            ->all();

        if ($ranks === []) {
            return 0;
        }

        return (int) (clone $resultBase)
            ->whereIn($rankColumn, $ranks)
            ->count();
    }

    private function existingColumns(string $table, array $columns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($this->existingColumns($table, $columns) as $column) {
            return $column;
        }

        return null;
    }

    private function resolveTournamentYear(Tournament $tournament): ?int
    {
        if (! empty($tournament->year)) {
            return (int) $tournament->year;
        }

        $startDate = $tournament->start_date ?? null;
        if ($startDate instanceof DateTimeInterface) {
            return (int) $startDate->format('Y');
        }

        $text = trim((string) $startDate);
        if (preg_match('/^\d{4}/', $text, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function resultFlowLabel(string $flowType): string
    {
        return match ($flowType) {
            'legacy_standard' => '既存（予選→準々決勝→準決勝→決勝）',
            'prelim_to_rr_to_final' => '予選→ラウンドロビン→決勝ステップラダー',
            'prelim_to_quarterfinal_to_rr_to_final' => '予選→準々決勝→ラウンドロビン→決勝ステップラダー',
            'prelim_to_single_elimination_to_final' => '予選→トーナメント→最終成績',
            'prelim_to_quarterfinal_to_single_elimination_to_final' => '予選→準々決勝→トーナメント→最終成績',
            'prelim_to_semifinal_to_single_elimination_to_final' => '予選→準決勝通算→トーナメント→最終成績',
            'prelim_to_shootout_to_final' => '予選→シュートアウト→最終成績',
            'prelim_to_quarterfinal_to_shootout_to_final' => '予選→準々決勝→シュートアウト→最終成績',
            'prelim_to_semifinal_to_shootout_to_final' => '予選→準決勝通算→シュートアウト→最終成績',
            default => $flowType,
        };
    }

    private function resultCodeLabel(string $resultCode): string
    {
        return match ($resultCode) {
            'prelim_total' => '予選通算',
            'quarterfinal_total' => '準々決勝通算',
            'semifinal_total' => '準決勝通算',
            'round_robin_total' => 'ラウンドロビン通算',
            'final_total' => '決勝/最終',
            default => $resultCode,
        };
    }

    private function genderLabel(mixed $gender): string
    {
        return match (strtoupper(trim((string) $gender))) {
            'M' => '男子',
            'F' => '女子',
            default => '全体',
        };
    }

    private function blankLabel(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '全体';
    }

    private function normalizeGender(mixed $gender): ?string
    {
        $value = strtoupper(trim((string) $gender));

        return match ($value) {
            'M', 'MALE', 'MAN', 'MEN', '男子' => 'M',
            'F', 'FEMALE', 'WOMAN', 'WOMEN', '女子' => 'F',
            default => null,
        };
    }
}
