<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatchScoreSheet;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JapanOpenDoubleEliminationService
{
    public const STAGE_CODE = 'japan_open_double_elimination';

    public const SHEET_TYPE = 'double_elimination';

    public const FINALIST_COUNT = 8;

    /** @return array<string,array<string,mixed>> */
    public function matchDefinitions(): array
    {
        return [
            'W1' => $this->match('W1', '勝者ゾーン1回戦 第1試合', 1, 2, [['seed', 1], ['seed', 8]]),
            'W2' => $this->match('W2', '勝者ゾーン1回戦 第2試合', 2, 2, [['seed', 4], ['seed', 5]]),
            'W3' => $this->match('W3', '勝者ゾーン1回戦 第3試合', 3, 2, [['seed', 2], ['seed', 7]]),
            'W4' => $this->match('W4', '勝者ゾーン1回戦 第4試合', 4, 2, [['seed', 3], ['seed', 6]]),
            'W5' => $this->match('W5', '勝者ゾーン2回戦 第1試合', 5, 2, [['winner', 'W1'], ['winner', 'W2']]),
            'W6' => $this->match('W6', '勝者ゾーン2回戦 第2試合', 6, 2, [['winner', 'W3'], ['winner', 'W4']]),
            'L1' => $this->match('L1', '敗者復活1回戦 第1試合', 7, 2, [['loser', 'W1'], ['loser', 'W2']]),
            'L2' => $this->match('L2', '敗者復活1回戦 第2試合', 8, 2, [['loser', 'W3'], ['loser', 'W4']]),
            'L3' => $this->match('L3', '敗者復活2回戦 第1試合', 9, 2, [['winner', 'L1'], ['loser', 'W6']]),
            'L4' => $this->match('L4', '敗者復活2回戦 第2試合', 10, 2, [['winner', 'L2'], ['loser', 'W5']]),
            'W7' => $this->match('W7', '勝者ゾーン3回戦', 11, 2, [['winner', 'W5'], ['winner', 'W6']]),
            'L5' => $this->match('L5', '敗者復活3回戦', 12, 2, [['winner', 'L3'], ['winner', 'L4']]),
            'TP' => $this->match('TP', '第3位決定戦', 13, 1, [['winner', 'L5'], ['loser', 'W7']]),
            'GF1' => $this->match('GF1', '優勝決定戦', 14, 1, [['winner', 'W7'], ['winner', 'TP']]),
            'GF2' => $this->match('GF2', '再優勝決定戦', 15, 1, [['winner', 'W7'], ['winner', 'TP']], true),
        ];
    }

    public function supports(Tournament $tournament): bool
    {
        return in_array(
            (string) data_get($tournament->template_snapshot, 'japan_open.component_code'),
            ['masters', 'queens'],
            true,
        );
    }

    /** @return array<string,mixed> */
    public function status(Tournament $tournament): array
    {
        if (! $this->supports($tournament)) {
            return ['supported' => false];
        }

        $snapshot = $this->currentSemifinalSnapshot($tournament);
        $settings = $this->settings($tournament);
        $seeds = collect($settings['seeds'] ?? [])->keyBy('seed');
        $state = $this->buildState($tournament, $seeds, false);

        return $state + [
            'supported' => true,
            'source_snapshot' => $snapshot,
            'source_row_count' => $snapshot?->rows()->count() ?? 0,
            'seed_count' => $seeds->count(),
            'last_seed_sync' => (array) ($settings['seed_sync'] ?? []),
        ];
    }

    /** @return array<string,mixed> */
    public function syncFinalists(
        Tournament $tournament,
        ?int $syncedBy = null,
        ?TournamentResultSnapshot $sourceSnapshot = null,
    ): array {
        $this->assertSupported($tournament);
        $sourceSnapshot ??= $this->currentSemifinalSnapshot($tournament);

        if (! $sourceSnapshot
            || (int) $sourceSnapshot->tournament_id !== (int) $tournament->id
            || (string) $sourceSnapshot->result_code !== 'semifinal_total'
            || ! $sourceSnapshot->is_current) {
            throw new InvalidArgumentException('最新の予選＋準決勝14G通算成績を先に反映してください。');
        }
        if ((int) $sourceSnapshot->games_count !== 14) {
            throw new InvalidArgumentException('決勝進出者の選出元は予選8G＋準決勝6Gの14G通算成績にしてください。');
        }

        $rows = $sourceSnapshot->rows()
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->get();
        $selected = $this->selectFinalists($rows);
        $seeds = $selected->map(function (TournamentResultSnapshotRow $row, int $index) use ($tournament): array {
            $participant = $this->resolveParticipant($tournament, $row);
            if (! $participant) {
                throw new InvalidArgumentException(sprintf(
                    '%sさんを大会参加者へ紐づけられません。参加者情報を確認してください。',
                    $row->display_name,
                ));
            }

            return [
                'seed' => $index + 1,
                'identity' => $this->participantIdentity($participant),
                'participant_id' => (int) $participant->id,
                'pro_bowler_id' => $participant->pro_bowler_id ? (int) $participant->pro_bowler_id : null,
                'license_no' => (string) ($participant->display_license_no ?: $participant->pro_bowler_license_no),
                'display_name' => (string) ($participant->display_name ?: $row->display_name),
                'dominant_arm' => $participant->display_dominant_arm,
                'source_ranking' => (int) $row->ranking,
                'source_total_pin' => (int) $row->total_pin,
                'source_games' => (int) $row->games,
                'source_average' => (float) $row->average,
            ];
        })->values()->all();

        DB::transaction(function () use ($tournament, $sourceSnapshot, $seeds, $syncedBy): void {
            $tournament->refresh();
            $current = collect($this->settings($tournament)['seeds'] ?? [])->pluck('identity')->values()->all();
            $incoming = collect($seeds)->pluck('identity')->values()->all();

            if ($current !== [] && $current !== $incoming && $this->hasEnteredBracketScores($tournament)) {
                throw new InvalidArgumentException('決勝対戦表に入力済みの得点があります。14G成績を修正する前に対戦表の確認が必要です。');
            }

            $allSettings = (array) ($tournament->template_snapshot ?? []);
            data_set($allSettings, 'japan_open.double_elimination', [
                'format' => 'jpba_japan_open_8_player',
                'finalist_count' => self::FINALIST_COUNT,
                'seeds' => $seeds,
                'tie_overrides' => $current === $incoming
                    ? (array) data_get($allSettings, 'japan_open.double_elimination.tie_overrides', [])
                    : [],
                'seed_sync' => [
                    'source_snapshot_id' => (int) $sourceSnapshot->id,
                    'source_result_code' => 'semifinal_total',
                    'source_games' => 14,
                    'finalist_count' => self::FINALIST_COUNT,
                    'synced_at' => now()->toIso8601String(),
                    'synced_by' => $syncedBy,
                ],
            ]);
            $tournament->template_snapshot = $allSettings;
            $tournament->save();
        });

        $state = $this->syncAvailableMatches($tournament->fresh());

        return [
            'source_snapshot_id' => (int) $sourceSnapshot->id,
            'finalist_count' => count($seeds),
            'created_sheet_count' => $state['created_sheet_count'],
        ];
    }

    /** @return array<string,mixed> */
    public function syncAvailableMatches(Tournament $tournament): array
    {
        $this->assertSupported($tournament);
        $seeds = collect($this->settings($tournament)['seeds'] ?? [])->keyBy('seed');
        if ($seeds->count() !== self::FINALIST_COUNT) {
            throw new InvalidArgumentException('準決勝14G上位8名を先に決勝へ同期してください。');
        }

        return DB::transaction(function () use ($tournament, $seeds): array {
            $state = $this->buildState($tournament, $seeds, true);
            $tournament->refresh();
            $settings = (array) ($tournament->template_snapshot ?? []);
            data_set($settings, 'japan_open.double_elimination.last_bracket_sync', [
                'ready_match_count' => $state['ready_match_count'],
                'completed_match_count' => $state['completed_match_count'],
                'reset_required' => $state['reset_required'],
                'champion_identity' => $state['champion']['identity'] ?? null,
                'synced_at' => now()->toIso8601String(),
            ]);
            $tournament->template_snapshot = $settings;
            $tournament->save();

            return $state;
        });
    }

    /** @return array<string,mixed> */
    public function setTieWinner(Tournament $tournament, string $matchCode, string $winnerIdentity): array
    {
        $this->assertSupported($tournament);
        $matchCode = strtoupper(trim($matchCode));
        $definitions = $this->matchDefinitions();
        if (! isset($definitions[$matchCode])) {
            throw new InvalidArgumentException('指定された対戦を確認できません。');
        }

        $state = $this->buildState(
            $tournament,
            collect($this->settings($tournament)['seeds'] ?? [])->keyBy('seed'),
            false,
        );
        $match = $state['matches'][$matchCode] ?? null;
        if (! $match || ! $match['is_tied']) {
            throw new InvalidArgumentException('合計得点が同点の対戦だけ勝者を指定できます。');
        }
        if (! collect($match['participants'])->contains(
            fn (array $player): bool => $player['identity'] === $winnerIdentity,
        )) {
            throw new InvalidArgumentException('対戦者ではない選手を勝者には指定できません。');
        }

        $settings = (array) ($tournament->template_snapshot ?? []);
        data_set($settings, 'japan_open.double_elimination.tie_overrides.'.$matchCode, $winnerIdentity);
        $tournament->template_snapshot = $settings;
        $tournament->save();

        return $this->syncAvailableMatches($tournament->fresh());
    }

    public function createFinalSnapshot(Tournament $tournament, ?int $reflectedBy = null): TournamentResultSnapshot
    {
        $this->assertSupported($tournament);
        $sourceSnapshot = $this->currentSemifinalSnapshot($tournament);
        if (! $sourceSnapshot) {
            throw new InvalidArgumentException('予選＋準決勝14G通算成績を先に反映してください。');
        }

        $seeds = collect($this->settings($tournament)['seeds'] ?? [])->keyBy('seed');
        $state = $this->buildState($tournament, $seeds, false);
        if (! $state['is_complete'] || count($state['final_rankings']) !== self::FINALIST_COUNT) {
            throw new InvalidArgumentException('決勝ダブルエリミネーションの全順位がまだ確定していません。');
        }

        $sourceRows = $sourceSnapshot->rows()->orderBy('ranking')->get();
        $matchTotals = $this->confirmedMatchTotals($tournament);

        return DB::transaction(function () use (
            $tournament,
            $sourceSnapshot,
            $sourceRows,
            $matchTotals,
            $state,
            $reflectedBy,
        ): TournamentResultSnapshot {
            TournamentResultSnapshot::query()
                ->where('tournament_id', $tournament->id)
                ->where('is_final', true)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $snapshot = TournamentResultSnapshot::query()->create([
                'tournament_id' => $tournament->id,
                'result_code' => 'final_total',
                'result_name' => '決勝ダブルエリミネーション 最終成績',
                'result_type' => self::SHEET_TYPE,
                'stage_name' => '決勝',
                'gender' => $sourceSnapshot->gender,
                'shift' => null,
                'games_count' => (int) collect($state['final_rankings'])
                    ->max(fn (array $ranked): int => (int) ($ranked['player']['source_games'] ?? 0)
                        + (int) data_get($matchTotals, $ranked['player']['identity'].'.games', 0)),
                'carry_game_count' => 14,
                'carry_stage_names' => ['予選', '準決勝'],
                'calculation_definition' => [
                    'format' => 'jpba_japan_open_8_player',
                    'source_snapshot_id' => (int) $sourceSnapshot->id,
                    'source_result_code' => (string) $sourceSnapshot->result_code,
                    'ranking_policy' => [
                        1 => '優勝者',
                        2 => '優勝決定戦敗者',
                        3 => '第3位決定戦敗者',
                        4 => '敗者復活3回戦敗者',
                        5 => '敗者復活2回戦第1試合敗者',
                        6 => '敗者復活2回戦第2試合敗者',
                        7 => '敗者復活1回戦第2試合敗者',
                        8 => '敗者復活1回戦第1試合敗者',
                    ],
                    'reset_required' => (bool) $state['reset_required'],
                    'completed_match_count' => (int) $state['completed_match_count'],
                ],
                'reflected_at' => now(),
                'reflected_by' => $reflectedBy,
                'is_final' => true,
                'is_published' => false,
                'is_current' => true,
                'notes' => '決勝対戦表の確定結果から自動生成。公式公開前にポイント・賞金配分を確認してください。',
            ]);

            foreach ($state['final_rankings'] as $ranked) {
                $rank = (int) $ranked['ranking'];
                $player = (array) $ranked['player'];
                $sourceRow = $this->findSourceRow($sourceRows, $player);
                if (! $sourceRow) {
                    throw new InvalidArgumentException($player['display_name'].'さんの14G成績を特定できません。');
                }

                $match = (array) ($matchTotals[$player['identity']] ?? []);
                $matchPin = (int) ($match['total_pin'] ?? 0);
                $matchGames = (int) ($match['games'] ?? 0);
                $carryPin = (int) $sourceRow->total_pin;
                $carryGames = (int) $sourceRow->games;
                $totalPin = $carryPin + $matchPin;
                $games = $carryGames + $matchGames;

                TournamentResultSnapshotRow::query()->create([
                    'snapshot_id' => $snapshot->id,
                    'ranking' => $rank,
                    'subject_type' => 'individual',
                    'pro_bowler_id' => $sourceRow->pro_bowler_id,
                    'amateur_bowler_id' => $sourceRow->amateur_bowler_id,
                    'pro_bowler_license_no' => $sourceRow->pro_bowler_license_no,
                    'amateur_name' => $sourceRow->amateur_name,
                    'display_name' => $sourceRow->display_name,
                    'gender' => $sourceRow->gender ?: $tournament->gender,
                    'shift' => null,
                    'entry_number' => $sourceRow->entry_number,
                    'identity_key' => $sourceRow->identity_key,
                    'scratch_pin' => $matchPin,
                    'carry_pin' => $carryPin,
                    'total_pin' => $totalPin,
                    'games' => $games,
                    'source_count' => 1 + $matchGames,
                    'is_complete' => true,
                    'breakdown' => [
                        'official_points_eligible' => true,
                        'source_snapshot_id' => (int) $sourceSnapshot->id,
                        'source_total_pin' => $carryPin,
                        'source_games' => $carryGames,
                        'double_elimination' => [
                            'total_pin' => $matchPin,
                            'games' => $matchGames,
                            'matches' => array_values((array) ($match['matches'] ?? [])),
                        ],
                    ],
                    'average' => $games > 0 ? round($totalPin / $games, 3) : null,
                    'tie_break_value' => 100000 - $rank,
                    'points' => null,
                    'prize_money' => null,
                ]);
            }

            $settings = (array) ($tournament->fresh()->template_snapshot ?? []);
            data_set($settings, 'japan_open.double_elimination.final_snapshot', [
                'snapshot_id' => (int) $snapshot->id,
                'source_snapshot_id' => (int) $sourceSnapshot->id,
                'champion_identity' => (string) $state['champion']['identity'],
                'created_at' => now()->toIso8601String(),
                'created_by' => $reflectedBy,
            ]);
            $tournament->forceFill(['template_snapshot' => $settings])->save();

            return $snapshot->load('rows');
        });
    }

    public function isBracketSheet(TournamentMatchScoreSheet $sheet): bool
    {
        return $sheet->sheet_type === self::SHEET_TYPE && $sheet->stage_code === self::STAGE_CODE;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $seeds
     * @return array<string,mixed>
     */
    private function buildState(Tournament $tournament, Collection $seeds, bool $createSheets): array
    {
        $definitions = $this->matchDefinitions();
        $results = [];
        $matches = [];
        $createdSheetCount = 0;
        $resetRequired = false;

        foreach ($definitions as $code => $definition) {
            if ($code === 'GF2') {
                $winnerSide = $results['W7']['winner']['identity'] ?? null;
                $firstFinalWinner = $results['GF1']['winner']['identity'] ?? null;
                $resetRequired = $winnerSide !== null
                    && $firstFinalWinner !== null
                    && $winnerSide !== $firstFinalWinner;
                if (! $resetRequired) {
                    $matches[$code] = $this->waitingMatch($definition, '再決定戦は不要です。');

                    continue;
                }
            }

            $participants = collect($definition['sources'])
                ->map(fn (array $source): ?array => $this->resolveSource($source, $seeds, $results))
                ->filter()
                ->values();

            if ($participants->count() !== 2) {
                $matches[$code] = $this->waitingMatch($definition, '前の対戦結果を待っています。');

                continue;
            }
            if ($participants->pluck('identity')->unique()->count() !== 2) {
                throw new InvalidArgumentException($definition['label'].'の対戦者が重複しています。');
            }

            if ($createSheets) {
                $createdSheetCount += $this->ensureSheets($tournament, $definition, $participants);
            }

            $outcome = $this->evaluateMatch($tournament, $definition, $participants);
            $matches[$code] = $outcome;
            if ($outcome['winner'] !== null) {
                $results[$code] = [
                    'winner' => $outcome['winner'],
                    'loser' => $outcome['loser'],
                ];
            }
        }

        $champion = null;
        $runnerUp = null;
        if ($resetRequired && isset($results['GF2'])) {
            $champion = $results['GF2']['winner'];
            $runnerUp = $results['GF2']['loser'];
        } elseif (! $resetRequired && isset($results['GF1'])) {
            $champion = $results['GF1']['winner'];
            $runnerUp = $results['GF1']['loser'];
        }

        return [
            'matches' => $matches,
            'created_sheet_count' => $createdSheetCount,
            'ready_match_count' => collect($matches)->whereIn('status', ['ready', 'in_progress', 'tied', 'complete'])->count(),
            'completed_match_count' => collect($matches)->where('status', 'complete')->count(),
            'reset_required' => $resetRequired,
            'champion' => $champion,
            'runner_up' => $runnerUp,
            'third_place' => $results['TP']['loser'] ?? null,
            'is_complete' => $champion !== null,
            'final_rankings' => $champion !== null
                ? $this->finalRankings($results, $champion, $runnerUp)
                : [],
        ];
    }

    /** @return array<int,array{ranking:int,player:array<string,mixed>}> */
    private function finalRankings(array $results, array $champion, array $runnerUp): array
    {
        $rankedPlayers = [
            1 => $champion,
            2 => $runnerUp,
            3 => $results['TP']['loser'] ?? null,
            4 => $results['L5']['loser'] ?? null,
            5 => $results['L3']['loser'] ?? null,
            6 => $results['L4']['loser'] ?? null,
            7 => $results['L2']['loser'] ?? null,
            8 => $results['L1']['loser'] ?? null,
        ];

        if (collect($rankedPlayers)->filter()->count() !== self::FINALIST_COUNT
            || collect($rankedPlayers)->filter()->pluck('identity')->unique()->count() !== self::FINALIST_COUNT) {
            return [];
        }

        return collect($rankedPlayers)
            ->map(fn (array $player, int $ranking): array => compact('ranking', 'player'))
            ->values()
            ->all();
    }

    /** @return array<string,array{total_pin:int,games:int,matches:array<int,array<string,mixed>>}> */
    private function confirmedMatchTotals(Tournament $tournament): array
    {
        $totals = [];
        $sheets = TournamentMatchScoreSheet::query()
            ->with('players')
            ->where('tournament_id', $tournament->id)
            ->where('sheet_type', self::SHEET_TYPE)
            ->where('stage_code', self::STAGE_CODE)
            ->whereNotNull('confirmed_at')
            ->orderBy('match_order')
            ->orderBy('game_number')
            ->get();

        foreach ($sheets as $sheet) {
            foreach ($sheet->players as $player) {
                $identity = $this->scoreSheetPlayerIdentity($player);
                $totals[$identity] ??= ['total_pin' => 0, 'games' => 0, 'matches' => []];
                $totals[$identity]['total_pin'] += (int) $player->final_score;
                $totals[$identity]['games']++;
                $totals[$identity]['matches'][] = [
                    'match_code' => (string) $sheet->match_code,
                    'match_label' => (string) $sheet->match_label,
                    'game_number' => (int) $sheet->game_number,
                    'score' => (int) $player->final_score,
                ];
            }
        }

        return $totals;
    }

    private function findSourceRow(Collection $rows, array $player): ?TournamentResultSnapshotRow
    {
        $proBowlerId = (int) ($player['pro_bowler_id'] ?? 0);
        if ($proBowlerId > 0) {
            $row = $rows->first(fn (TournamentResultSnapshotRow $candidate): bool => (int) $candidate->pro_bowler_id === $proBowlerId);
            if ($row) {
                return $row;
            }
        }

        $license = strtoupper(trim((string) ($player['license_no'] ?? '')));
        if ($license !== '') {
            $row = $rows->first(fn (TournamentResultSnapshotRow $candidate): bool => strtoupper(trim((string) $candidate->pro_bowler_license_no)) === $license);
            if ($row) {
                return $row;
            }
        }

        $name = $this->normalizeName((string) ($player['display_name'] ?? ''));

        return $rows->first(fn (TournamentResultSnapshotRow $candidate): bool => $this->normalizeName((string) $candidate->display_name) === $name);
    }

    /** @param array<string,mixed> $definition @param Collection<int,array<string,mixed>> $participants */
    private function ensureSheets(Tournament $tournament, array $definition, Collection $participants): int
    {
        $created = 0;
        foreach (range(1, $definition['games']) as $gameNumber) {
            $sheet = TournamentMatchScoreSheet::query()->firstOrNew([
                'tournament_id' => $tournament->id,
                'sheet_type' => self::SHEET_TYPE,
                'stage_code' => self::STAGE_CODE,
                'match_code' => $definition['code'],
                'game_number' => $gameNumber,
            ]);
            $wasNew = ! $sheet->exists;
            $sheet->fill([
                'match_label' => $definition['label'],
                'match_order' => ($definition['order'] * 10) + $gameNumber,
                'is_published' => true,
                'notes' => 'ジャパンオープン公式対戦表から自動生成',
            ]);
            $sheet->save();

            $existing = $sheet->players()->withCount('frames')->get();
            $expectedIdentities = $participants->pluck('identity')->all();
            $currentIdentities = $existing->map(fn ($player): string => $this->scoreSheetPlayerIdentity($player))->all();
            if ($currentIdentities !== $expectedIdentities) {
                if ($sheet->confirmed_at || $existing->sum('frames_count') > 0) {
                    throw new InvalidArgumentException($definition['label'].'の入力済みスコアシートと進出者が一致しません。');
                }
                $sheet->players()->delete();
                foreach ($participants as $index => $participant) {
                    $sheet->players()->create([
                        'sort_order' => $index + 1,
                        'player_slot' => chr(65 + $index),
                        'pro_bowler_id' => $participant['pro_bowler_id'],
                        'pro_bowler_license_no' => $participant['license_no'] ?: null,
                        'display_name' => $participant['display_name'],
                        'dominant_arm' => $participant['dominant_arm'] ?? null,
                        'final_score' => 0,
                        'is_winner' => false,
                    ]);
                }
            }
            if ($wasNew) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  array<string,mixed>  $definition
     * @param  Collection<int,array<string,mixed>>  $participants
     * @return array<string,mixed>
     */
    private function evaluateMatch(Tournament $tournament, array $definition, Collection $participants): array
    {
        $sheets = TournamentMatchScoreSheet::query()
            ->with('players')
            ->where('tournament_id', $tournament->id)
            ->where('sheet_type', self::SHEET_TYPE)
            ->where('stage_code', self::STAGE_CODE)
            ->where('match_code', $definition['code'])
            ->orderBy('game_number')
            ->get();
        $base = $definition + [
            'participants' => $participants->all(),
            'sheets' => $sheets,
            'totals' => [],
            'winner' => null,
            'loser' => null,
            'is_tied' => false,
            'message' => null,
        ];

        if ($sheets->count() < $definition['games']) {
            return array_merge($base, ['status' => 'ready']);
        }
        if ($sheets->contains(fn (TournamentMatchScoreSheet $sheet): bool => $sheet->confirmed_at === null)) {
            return array_merge($base, [
                'status' => $sheets->contains(fn ($sheet) => $sheet->players->contains(fn ($player) => $player->frames()->exists()))
                    ? 'in_progress'
                    : 'ready',
            ]);
        }

        $expected = $participants->pluck('identity')->sort()->values()->all();
        $totals = array_fill_keys($expected, 0);
        foreach ($sheets->take($definition['games']) as $sheet) {
            $actual = $sheet->players->map(fn ($player): string => $this->scoreSheetPlayerIdentity($player))->sort()->values()->all();
            if ($actual !== $expected) {
                return array_merge($base, ['status' => 'invalid', 'message' => '対戦者が公式組合せと一致しません。']);
            }
            foreach ($sheet->players as $player) {
                $totals[$this->scoreSheetPlayerIdentity($player)] += (int) $player->final_score;
            }
        }

        $max = max($totals);
        $winnerIdentities = array_keys(array_filter($totals, fn (int $total): bool => $total === $max));
        $isTied = count($winnerIdentities) > 1;
        $winnerIdentity = $winnerIdentities[0] ?? null;
        if ($isTied) {
            $winnerIdentity = (string) data_get(
                $tournament->template_snapshot,
                'japan_open.double_elimination.tie_overrides.'.$definition['code'],
                '',
            );
            if ($definition['games'] === 1 && $winnerIdentity === '') {
                $declared = $sheets->first()->players->firstWhere('is_winner', true);
                $winnerIdentity = $declared ? $this->scoreSheetPlayerIdentity($declared) : '';
            }
            if (! in_array($winnerIdentity, $expected, true)) {
                return array_merge($base, [
                    'status' => 'tied',
                    'totals' => $totals,
                    'is_tied' => true,
                    'message' => '2G合計が同点です。タイブレーク後の勝者を指定してください。',
                ]);
            }
        }

        $winner = $participants->firstWhere('identity', $winnerIdentity);
        $loser = $participants->first(fn (array $player): bool => $player['identity'] !== $winnerIdentity);

        return array_merge($base, [
            'status' => 'complete',
            'totals' => $totals,
            'winner' => $winner,
            'loser' => $loser,
            'is_tied' => $isTied,
        ]);
    }

    /** @param array{0:string,1:int|string} $source @param Collection<int,array<string,mixed>> $seeds @param array<string,array<string,array<string,mixed>>> $results */
    private function resolveSource(array $source, Collection $seeds, array $results): ?array
    {
        [$type, $value] = $source;
        if ($type === 'seed') {
            return $seeds->get((int) $value);
        }

        return $results[(string) $value][$type] ?? null;
    }

    /** @param Collection<int,TournamentResultSnapshotRow> $rows @return Collection<int,TournamentResultSnapshotRow> */
    private function selectFinalists(Collection $rows): Collection
    {
        if ($rows->count() < self::FINALIST_COUNT) {
            throw new InvalidArgumentException('14G通算成績が8名未満のため、決勝進出者を確定できません。');
        }
        $selected = $rows->take(self::FINALIST_COUNT)->values();
        if ($selected->contains(fn (TournamentResultSnapshotRow $row): bool => ! $row->is_complete || (int) $row->games !== 14)) {
            throw new InvalidArgumentException('進出圏内に14G未完了の選手がいるため、決勝進出者を確定できません。');
        }
        $last = $selected->last();
        $next = $rows->get(self::FINALIST_COUNT);
        if ($next && (int) $last->total_pin === (int) $next->total_pin) {
            throw new InvalidArgumentException('決勝進出境界が同ピンです。タイブレーク後の順位を成績へ反映してください。');
        }

        return $selected;
    }

    private function currentSemifinalSnapshot(Tournament $tournament): ?TournamentResultSnapshot
    {
        return TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('result_code', 'semifinal_total')
            ->where('is_current', true)
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveParticipant(Tournament $tournament, TournamentResultSnapshotRow $row): ?object
    {
        $query = DB::table('tournament_participants')->where('tournament_id', $tournament->id);
        if ($row->pro_bowler_id) {
            return (clone $query)->where('pro_bowler_id', $row->pro_bowler_id)->first();
        }
        if ($row->amateur_bowler_id) {
            return (clone $query)->where('amateur_bowler_id', $row->amateur_bowler_id)->first();
        }
        if (trim((string) $row->pro_bowler_license_no) !== '') {
            return (clone $query)->where(function ($builder) use ($row): void {
                $builder->where('display_license_no', $row->pro_bowler_license_no)
                    ->orWhere('pro_bowler_license_no', $row->pro_bowler_license_no);
            })->first();
        }

        return (clone $query)->where('display_name', $row->display_name)->first();
    }

    private function participantIdentity(object $participant): string
    {
        if ($participant->pro_bowler_id) {
            return 'pro:'.(int) $participant->pro_bowler_id;
        }
        $license = strtoupper(trim((string) ($participant->display_license_no ?: $participant->pro_bowler_license_no)));
        if ($license !== '') {
            return 'license:'.$license;
        }

        return 'name:'.$this->normalizeName((string) $participant->display_name);
    }

    private function scoreSheetPlayerIdentity(object $player): string
    {
        if ($player->pro_bowler_id) {
            return 'pro:'.(int) $player->pro_bowler_id;
        }
        $license = strtoupper(trim((string) $player->pro_bowler_license_no));
        if ($license !== '') {
            return 'license:'.$license;
        }

        return 'name:'.$this->normalizeName((string) $player->display_name);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/[\s　]+/u', '', trim($name)) ?? trim($name));
    }

    private function hasEnteredBracketScores(Tournament $tournament): bool
    {
        return TournamentMatchScoreSheet::query()
            ->where('tournament_id', $tournament->id)
            ->where('sheet_type', self::SHEET_TYPE)
            ->where('stage_code', self::STAGE_CODE)
            ->where(function ($query): void {
                $query->whereNotNull('confirmed_at')
                    ->orWhereHas('players.frames');
            })
            ->exists();
    }

    /** @return array<string,mixed> */
    private function settings(Tournament $tournament): array
    {
        return (array) data_get($tournament->template_snapshot, 'japan_open.double_elimination', []);
    }

    private function assertSupported(Tournament $tournament): void
    {
        if (! $this->supports($tournament)) {
            throw new InvalidArgumentException('ジャパンオープンのマスターズ／クイーンズで実行してください。');
        }
    }

    /** @param array<int,array{0:string,1:int|string}> $sources @return array<string,mixed> */
    private function match(string $code, string $label, int $order, int $games, array $sources, bool $conditional = false): array
    {
        return compact('code', 'label', 'order', 'games', 'sources', 'conditional');
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function waitingMatch(array $definition, string $message): array
    {
        return $definition + [
            'status' => 'waiting',
            'participants' => [],
            'sheets' => collect(),
            'totals' => [],
            'winner' => null,
            'loser' => null,
            'is_tied' => false,
            'message' => $message,
        ];
    }
}
