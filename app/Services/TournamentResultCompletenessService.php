<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class TournamentResultCompletenessService
{
    public function __construct(
        private readonly ShootoutService $shootoutService,
        private readonly StepLadderService $stepLadderService,
    ) {}

    /**
     * @param  Collection<int,TournamentResultSnapshot>|null  $sourceSnapshots
     * @return array<string,mixed>
     */
    public function audit(
        Tournament $tournament,
        ?Collection $sourceSnapshots = null,
        bool $includePublishedStatCheck = true,
    ): array {
        $publication = TournamentResultPublication::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', TournamentResultPublication::STATUS_CURRENT)
            ->orderByDesc('revision')
            ->first();

        $snapshots = $sourceSnapshots ?? TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where($publication === null ? 'is_current' : 'is_published', true)
            ->orderBy('id')
            ->get();
        $snapshots->each(function ($snapshot): void {
            if ($snapshot instanceof TournamentResultSnapshot) {
                $snapshot->loadMissing('rows');
            }
        });

        $scoreRows = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->orderBy('id')
            ->get();

        $duplicates = $this->duplicateScoreKeys($scoreRows);
        $invalidScoreCount = $scoreRows->filter(
            fn (object $row): bool => (int) $row->score < 0 || (int) $row->score > 300,
        )->count();

        $facts = [
            'tournament_id' => (int) $tournament->id,
            'tournament_name' => (string) $tournament->name,
            'result_flow_type' => (string) $tournament->result_flow_type,
            'snapshot_count' => $snapshots->count(),
            'stage_setting_count' => DB::table('stage_settings')
                ->where('tournament_id', $tournament->id)
                ->where('enabled', true)
                ->count(),
            'result_output_count' => DB::table('tournament_result_outputs')
                ->where('tournament_id', $tournament->id)
                ->where('is_active', true)
                ->count(),
            'score_count' => $scoreRows->count(),
            'invalid_score_count' => $invalidScoreCount,
            'duplicate_score_count' => count($duplicates),
            'duplicate_score_samples' => array_slice($duplicates, 0, 5),
            'snapshot_gaps' => $this->snapshotGaps($snapshots, $scoreRows),
            'flow_errors' => $this->flowErrors($tournament, $snapshots, $scoreRows),
            'publication_stat_mismatches' => $includePublishedStatCheck && $publication !== null
                ? $this->publicationStatMismatches($publication, $scoreRows)
                : [],
        ];

        return $this->evaluateFacts($facts) + [
            'facts' => $facts,
            'actual_totals' => $this->actualTotalsFromScores($scoreRows),
        ];
    }

    /** @param array<string,mixed> $facts */
    public function evaluateFacts(array $facts): array
    {
        $errors = [];

        if ((int) ($facts['snapshot_count'] ?? 0) === 0) {
            $errors[] = '正式成績スナップショットがありません。';
        }
        if ((int) ($facts['stage_setting_count'] ?? 0) === 0) {
            $errors[] = '競技ステージ設定がありません。';
        }
        if ((int) ($facts['result_output_count'] ?? 0) === 0) {
            $errors[] = '公開結果・PDF出力設定がありません。';
        }
        if ((int) ($facts['score_count'] ?? 0) === 0) {
            $errors[] = '各ゲームスコアが1件もありません。集計値だけでは確定公開できません。';
        }
        if ((int) ($facts['invalid_score_count'] ?? 0) > 0) {
            $errors[] = '0～300の範囲外のゲームスコアがあります。';
        }
        if ((int) ($facts['duplicate_score_count'] ?? 0) > 0) {
            $errors[] = '同一選手・同一ステージ・同一ゲームの重複スコアがあります。';
        }
        if (! empty($facts['snapshot_gaps'])) {
            $errors[] = '正式成績を再現する各ゲームスコアが不足しています。';
        }
        foreach ((array) ($facts['flow_errors'] ?? []) as $flowError) {
            $errors[] = (string) $flowError;
        }
        if (! empty($facts['publication_stat_mismatches'])) {
            $errors[] = '公開成績のゲーム数・トータルピンが実投球スコアと一致していません。';
        }

        return [
            'is_complete' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * 公開順位はスナップショットの競技順位を維持し、年間統計値だけを実投球から作る。
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    public function applyActualTotals(Tournament $tournament, array $rows): array
    {
        $scoreRows = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->get();
        $totals = $this->actualTotalsFromScores($scoreRows);

        foreach ($rows as &$row) {
            $actual = $this->findByAliases($totals, $this->rowAliases($row));
            if ($actual === null || (int) ($actual['games'] ?? 0) === 0) {
                continue;
            }

            $breakdown = is_array($row['breakdown'] ?? null) ? $row['breakdown'] : [];
            $breakdown['competition_display_total'] = [
                'total_pin' => (int) ($row['total_pin'] ?? 0),
                'games' => (int) ($row['games'] ?? 0),
                'average' => isset($row['average']) ? (float) $row['average'] : null,
            ];
            $breakdown['official_stat_source'] = 'game_scores';

            $row['total_pin'] = (int) $actual['total_pin'];
            $row['games'] = (int) $actual['games'];
            $row['average'] = round($row['total_pin'] / max(1, $row['games']), 3);
            $row['breakdown'] = $breakdown;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  Collection<int,TournamentResultSnapshot>  $snapshots
     * @param  Collection<int,object>  $scoreRows
     * @return array<int,array<string,mixed>>
     */
    private function snapshotGaps(Collection $snapshots, Collection $scoreRows): array
    {
        $gaps = [];

        foreach ($snapshots as $snapshot) {
            if ($snapshot->is_final) {
                continue;
            }

            $stageToken = $this->stageToken((string) $snapshot->result_code);
            $expectedStageGames = max(
                0,
                (int) $snapshot->games_count - (int) $snapshot->carry_game_count,
            );
            if ($stageToken === null || $expectedStageGames === 0) {
                continue;
            }

            $stageScores = $scoreRows->filter(
                fn (object $row): bool => str_contains((string) $row->stage, $stageToken),
            );
            $counts = $this->scoreCountsByAlias($stageScores);
            $missing = [];

            foreach ($snapshot->rows as $row) {
                $rowGames = max(0, (int) $row->games);
                $rowStageGames = max(0, $rowGames - (int) $snapshot->carry_game_count);
                $rowExpectedStageGames = min($expectedStageGames, $rowStageGames);
                $actualGames = 0;
                foreach ($this->rowAliases($row->toArray()) as $alias) {
                    $actualGames = max($actualGames, (int) ($counts[$alias] ?? 0));
                }
                if ($actualGames < $rowExpectedStageGames) {
                    $missing[] = [
                        'display_name' => (string) $row->display_name,
                        'expected_games' => $rowExpectedStageGames,
                        'actual_games' => $actualGames,
                    ];
                }
            }

            if ($missing !== []) {
                $gaps[] = [
                    'snapshot_id' => (int) $snapshot->id,
                    'result_code' => (string) $snapshot->result_code,
                    'result_name' => (string) $snapshot->result_name,
                    'stage_token' => $stageToken,
                    'missing_count' => count($missing),
                    'samples' => array_slice($missing, 0, 5),
                ];
            }
        }

        return $gaps;
    }

    /**
     * @param  Collection<int,TournamentResultSnapshot>  $snapshots
     * @param  Collection<int,object>  $scoreRows
     * @return array<int,string>
     */
    private function flowErrors(Tournament $tournament, Collection $snapshots, Collection $scoreRows): array
    {
        $flowType = (string) $tournament->result_flow_type;
        $errors = [];

        if (str_contains($flowType, 'shootout')) {
            $errors = array_merge($errors, $this->shootoutErrors($tournament, $snapshots, $scoreRows));
        }

        if (in_array($flowType, ['prelim_to_rr_to_final', 'prelim_to_quarterfinal_to_rr_to_final'], true)) {
            try {
                $stepLadder = $this->stepLadderService->build([
                    'tournament_id' => (int) $tournament->id,
                    'upto_game' => 2,
                    'shift' => '',
                    'gender' => in_array($tournament->gender, ['M', 'F'], true) ? $tournament->gender : '',
                ]);
                if (($stepLadder['semifinal']['status'] ?? '') !== 'done'
                    || ($stepLadder['final']['status'] ?? '') !== 'done'
                    || count((array) ($stepLadder['standings'] ?? [])) < 3) {
                    $errors[] = 'ステップラダーの全試合・勝者が確定していません。';
                }
            } catch (Throwable $exception) {
                $errors[] = 'ステップラダーを再現できません: '.$exception->getMessage();
            }
        }

        if (str_contains($flowType, 'single_elimination')) {
            $qualifierCount = max(0, (int) $tournament->single_elimination_qualifier_count);
            $matchScoreCount = $scoreRows->filter(
                fn (object $row): bool => (string) $row->stage === 'トーナメント'
                    && str_starts_with((string) ($row->entry_number ?? ''), 'SE:'),
            )->count();
            $expectedScoreCount = $qualifierCount > 1 ? ($qualifierCount - 1) * 2 : 0;
            if ($expectedScoreCount === 0 || $matchScoreCount < $expectedScoreCount) {
                $errors[] = 'トーナメント決勝の全対戦スコアがありません。';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  Collection<int,TournamentResultSnapshot>  $snapshots
     * @param  Collection<int,object>  $scoreRows
     * @return array<int,string>
     */
    private function shootoutErrors(Tournament $tournament, Collection $snapshots, Collection $scoreRows): array
    {
        $seedCode = trim((string) $tournament->shootout_seed_source_result_code) ?: 'semifinal_total';
        $seedSnapshot = $snapshots->firstWhere('result_code', $seedCode);
        if ($seedSnapshot === null || $seedSnapshot->rows->count() < 8) {
            return ['シュートアウト進出者8名の確定成績がありません。'];
        }

        $seedEntries = $seedSnapshot->rows
            ->sortBy('ranking')
            ->take(8)
            ->values()
            ->map(fn ($row, int $index): array => [
                'seed' => $index + 1,
                'display_name' => (string) $row->display_name,
                'pro_bowler_id' => $row->pro_bowler_id,
                'pro_bowler_license_no' => $row->pro_bowler_license_no,
                'amateur_name' => $row->amateur_name,
                'source_row_id' => $row->id,
                'participant_key' => $this->rowAliases($row->toArray())[0] ?? 'seed:'.($index + 1),
                'source_ranking' => (int) $row->ranking,
                'total_pin' => (int) $row->total_pin,
                'games' => (int) $row->games,
                'average' => $row->average,
            ])
            ->all();

        $matchScores = [];
        foreach ($scoreRows as $scoreRow) {
            if ((string) $scoreRow->stage !== 'シュートアウト'
                || preg_match('/^SO:(SO[123]):([ABCD])$/', (string) ($scoreRow->entry_number ?? ''), $matches) !== 1) {
                continue;
            }
            $matchScores[$matches[1]][$matches[2]] = ['score' => (int) $scoreRow->score];
        }

        foreach ($this->scoreSheetWinnerOverrides((int) $tournament->id) as $matchKey => $slots) {
            foreach ($slots as $slotCode => $isWinner) {
                if (isset($matchScores[$matchKey][$slotCode])) {
                    $matchScores[$matchKey][$slotCode]['is_winner'] = $isWinner;
                }
            }
        }

        $shootout = $this->shootoutService->buildStandard8($seedEntries, $matchScores);
        if ((int) ($shootout['summary']['completed_match_count'] ?? 0) !== 3
            || trim((string) ($shootout['summary']['winner_name'] ?? '')) === '') {
            return ['シュートアウト3試合の得点または勝者が確定していません。'];
        }

        try {
            $standings = $this->shootoutService->buildFinalStandings($shootout);
        } catch (Throwable $exception) {
            return ['シュートアウト最終順位を再現できません: '.$exception->getMessage()];
        }

        if (count($standings) !== 8) {
            return ['シュートアウト最終順位が8名分ありません。'];
        }

        $finalSnapshot = $snapshots->first(fn ($snapshot): bool => (bool) $snapshot->is_final);
        $officialWinner = $finalSnapshot?->rows->sortBy('ranking')->first()?->display_name;
        $calculatedWinner = $shootout['summary']['winner_name'] ?? null;
        if ($officialWinner !== null
            && $this->normalizeName((string) $officialWinner) !== $this->normalizeName((string) $calculatedWinner)) {
            return ['シュートアウト計算勝者が公式最終成績1位と一致しません。'];
        }

        return [];
    }

    /** @return array<string,array<string,bool>> */
    private function scoreSheetWinnerOverrides(int $tournamentId): array
    {
        $rows = DB::table('tournament_match_score_sheets as sheets')
            ->join('tournament_match_score_sheet_players as players', 'players.score_sheet_id', '=', 'sheets.id')
            ->where('sheets.tournament_id', $tournamentId)
            ->where('sheets.sheet_type', 'shootout')
            ->where('sheets.is_published', true)
            ->select([
                'sheets.match_code',
                'sheets.match_label',
                'sheets.stage_code',
                'players.player_slot',
                'players.is_winner',
            ])
            ->get();

        $overrides = [];
        foreach ($rows as $row) {
            $matchKey = $this->shootoutMatchKey(implode(' ', array_filter([
                (string) ($row->match_code ?? ''),
                (string) ($row->match_label ?? ''),
                (string) ($row->stage_code ?? ''),
            ])));
            $slotCode = strtoupper(trim((string) ($row->player_slot ?? '')));
            if ($matchKey === null || preg_match('/^[ABCD]$/', $slotCode) !== 1) {
                continue;
            }

            $overrides[$matchKey][$slotCode] = (bool) $row->is_winner;
        }

        return $overrides;
    }

    private function shootoutMatchKey(string $value): ?string
    {
        $normalized = function_exists('mb_convert_kana')
            ? mb_convert_kana($value, 'as', 'UTF-8')
            : $value;
        $upper = strtoupper($normalized);

        if (preg_match('/SO\s*:?\s*SO?\s*([123])/', $upper, $matches)
            || preg_match('/\bSO\s*([123])\b/', $upper, $matches)) {
            return 'SO'.$matches[1];
        }
        if (str_contains($upper, 'FINAL') || str_contains($value, '優勝')) {
            return 'SO3';
        }
        if (str_contains($upper, '2ND') || str_contains($upper, 'SECOND') || str_contains($value, '２')) {
            return 'SO2';
        }
        if (str_contains($upper, '1ST') || str_contains($upper, 'FIRST') || str_contains($value, '１')) {
            return 'SO1';
        }

        return null;
    }

    /**
     * @param  Collection<int,object>  $scoreRows
     * @return array<string,array{total_pin:int,games:int,average:float}>
     */
    private function actualTotalsFromScores(Collection $scoreRows): array
    {
        $groups = [];

        foreach ($scoreRows as $row) {
            $aliases = $this->scoreRowAliases($row);
            $canonical = $aliases[0] ?? null;
            if ($canonical === null) {
                continue;
            }
            if (! isset($groups[$canonical])) {
                $groups[$canonical] = ['total_pin' => 0, 'games' => 0, 'aliases' => []];
            }
            $groups[$canonical]['total_pin'] += (int) $row->score;
            $groups[$canonical]['games']++;
            foreach ($aliases as $alias) {
                $groups[$canonical]['aliases'][$alias] = true;
            }
        }

        $totals = [];
        foreach ($groups as $group) {
            $total = [
                'total_pin' => (int) $group['total_pin'],
                'games' => (int) $group['games'],
                'average' => round($group['total_pin'] / max(1, $group['games']), 3),
            ];
            foreach (array_keys($group['aliases']) as $alias) {
                $totals[$alias] = $total;
            }
        }

        return $totals;
    }

    /**
     * @param  Collection<int,object>  $scoreRows
     * @return array<int,string>
     */
    private function duplicateScoreKeys(Collection $scoreRows): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($scoreRows as $row) {
            $identity = $this->scoreRowAliases($row)[0] ?? 'row:'.$row->id;
            $key = implode('|', [
                $identity,
                trim((string) $row->stage),
                trim((string) ($row->entry_number ?? '')),
                (int) $row->game_number,
            ]);
            if (isset($seen[$key])) {
                $duplicates[$key] = true;
            }
            $seen[$key] = true;
        }

        return array_keys($duplicates);
    }

    /** @param Collection<int,object> $scoreRows */
    private function scoreCountsByAlias(Collection $scoreRows): array
    {
        $counts = [];
        foreach ($scoreRows as $row) {
            foreach ($this->scoreRowAliases($row) as $alias) {
                $counts[$alias] = (int) ($counts[$alias] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int,object>  $scoreRows
     * @return array<int,array<string,mixed>>
     */
    private function publicationStatMismatches(
        TournamentResultPublication $publication,
        Collection $scoreRows,
    ): array {
        $totals = $this->actualTotalsFromScores($scoreRows);
        $mismatches = [];

        foreach ($publication->rows()->orderBy('ranking')->get() as $row) {
            $actual = $this->findByAliases($totals, $this->rowAliases($row->toArray()));
            if ($actual === null
                || (int) $row->games !== (int) $actual['games']
                || (int) $row->total_pin !== (int) $actual['total_pin']) {
                $mismatches[] = [
                    'display_name' => (string) $row->display_name,
                    'published_games' => (int) $row->games,
                    'published_total_pin' => (int) $row->total_pin,
                    'actual_games' => (int) ($actual['games'] ?? 0),
                    'actual_total_pin' => (int) ($actual['total_pin'] ?? 0),
                ];
            }
        }

        return $mismatches;
    }

    private function stageToken(string $resultCode): ?string
    {
        return match (true) {
            str_contains($resultCode, 'prelim') => '予選',
            str_contains($resultCode, 'quarterfinal') => '準々決勝',
            str_contains($resultCode, 'semifinal') => '準決勝',
            str_contains($resultCode, 'round_robin') => 'ラウンドロビン',
            default => null,
        };
    }

    /** @return array<int,string> */
    private function rowAliases(array $row): array
    {
        return $this->aliases(
            (int) ($row['pro_bowler_id'] ?? 0),
            (string) ($row['pro_bowler_license_no'] ?? ''),
            (string) ($row['display_name'] ?? $row['amateur_name'] ?? ''),
        );
    }

    /** @return array<int,string> */
    private function scoreRowAliases(object $row): array
    {
        return $this->aliases(
            (int) ($row->pro_bowler_id ?? 0),
            (string) ($row->license_number ?? ''),
            (string) ($row->name ?? ''),
        );
    }

    /** @return array<int,string> */
    private function aliases(int $proBowlerId, string $license, string $name): array
    {
        $aliases = [];
        if ($proBowlerId > 0) {
            $aliases[] = 'pro:'.$proBowlerId;
        }
        $license = strtoupper(preg_replace('/\s+/u', '', trim($license)) ?? '');
        if ($license !== '' && $license !== 'アマ' && ! str_starts_with($license, 'AMATEUR-')) {
            $aliases[] = 'license:'.$license;
        }
        $name = $this->normalizeName($name);
        if ($name !== '') {
            $aliases[] = 'name:'.$name;
        }

        return array_values(array_unique($aliases));
    }

    /** @param array<string,array<string,mixed>> $totals */
    private function findByAliases(array $totals, array $aliases): ?array
    {
        foreach ($aliases as $alias) {
            if (isset($totals[$alias])) {
                return $totals[$alias];
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/[\s　]+/u', '', trim($name)) ?? '');
    }
}
