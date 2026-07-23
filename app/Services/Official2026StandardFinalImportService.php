<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Official2026StandardFinalImportService
{
    private const DATASET_PATH = 'data/jpba_official_2026_standard_final_scores.json';

    private const IMPORT_MARKER = 'jpba_official_2026_standard_final';

    private const DETAIL_MARKER = 'jpba_official_2026_standard_detail';

    private const EXPECTED_EVENT_COUNT = 11;

    private const EXPECTED_SCORE_SHEET_COUNT = 29;

    private const EXPECTED_FRAME_PLAYER_COUNT = 63;

    private const EXPECTED_FRAME_COUNT = 630;

    private const EXPECTED_ADDITIONAL_STAGE_SCORE_COUNT = 96;

    private const EXPECTED_BRACKET_MATCH_COUNT = 41;

    private const EXPECTED_BRACKET_SCORE_COUNT = 158;

    public function __construct(
        private readonly TournamentResultPublicationService $publicationService,
        private readonly TournamentResultCompletenessService $completenessService,
    ) {}

    /** @return array<string,mixed> */
    public function import(bool $write = false, string $adminEmail = 'yamaguchi@jpba.or.jp'): array
    {
        $dataset = $this->dataset();
        $admin = User::query()->where('email', $adminEmail)->first();
        $errors = [];
        $events = [];

        if ($admin === null) {
            $errors[] = "Administrator was not found: {$adminEmail}";
        }

        foreach ($dataset['events'] as $event) {
            $eventErrors = $this->validateEvent($event);
            $tournament = $this->findTournament((string) $event['key']);
            $participants = ['map' => [], 'errors' => []];

            if ($tournament === null) {
                $eventErrors[] = 'The detailed-score tournament publication was not found.';
            } else {
                $participants = $this->resolveParticipants($tournament, $event);
                $eventErrors = [...$eventErrors, ...$participants['errors']];
            }

            $records = $participants['errors'] === []
                ? $this->scoreRecords($event, $participants['map'])
                : [];
            $comparison = $tournament === null || $records === []
                ? null
                : $this->compareExistingScores($tournament, $records);
            if (($comparison['actual_count'] ?? 0) > 0
                && ($comparison['difference_count'] ?? 0) > 0
                && ! $write) {
                $eventErrors[] = 'Existing final-stage game scores differ from the official dataset.';
            }

            $eventErrors = array_values(array_unique($eventErrors));
            $events[] = [
                'key' => $event['key'],
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournament?->name,
                'score_sheet_count' => count($event['score_sheets']),
                'bracket_match_count' => count($event['bracket_matches'] ?? []),
                'additional_stage_score_count' => $this->additionalStageScoreCount($event),
                'expected_game_score_count' => count($records),
                'existing_score_comparison' => $comparison,
                'errors' => $eventErrors,
            ];
            foreach ($eventErrors as $eventError) {
                $errors[] = $event['key'].': '.$eventError;
            }
        }

        $report = [
            'mode' => $write ? 'write' : 'dry-run',
            'dataset' => $dataset['dataset'],
            'dataset_sha256' => hash_file('sha256', database_path(self::DATASET_PATH)),
            'source_checked_at' => $dataset['source_checked_at'],
            'event_count' => count($dataset['events']),
            'score_sheet_count' => (int) $dataset['score_sheet_count'],
            'frame_player_count' => (int) $dataset['frame_player_count'],
            'frame_count' => (int) $dataset['frame_count'],
            'additional_stage_score_count' => (int) $dataset['additional_stage_score_count'],
            'bracket_match_count' => (int) $dataset['bracket_match_count'],
            'bracket_score_count' => (int) $dataset['bracket_score_count'],
            'admin_id' => $admin?->id,
            'events' => $events,
            'errors' => $errors,
            'repaired' => [],
        ];

        if (! $write || $errors !== []) {
            return $report;
        }

        return DB::transaction(function () use ($dataset, $admin, $report): array {
            foreach ($dataset['events'] as $event) {
                $tournament = $this->findTournament((string) $event['key'], true);
                if ($tournament === null) {
                    throw new RuntimeException("{$event['key']}: tournament disappeared before final repair.");
                }
                $participants = $this->resolveParticipants($tournament, $event);
                if ($participants['errors'] !== []) {
                    throw new RuntimeException($event['key'].': '.implode(' ', $participants['errors']));
                }

                $records = $this->scoreRecords($event, $participants['map']);
                $this->clearGeneratedFinalData($tournament, $records);
                $insertedScores = $this->insertScoreRecords($tournament, $records);
                $stageSettings = $this->synchronizeStageSettings($tournament, $records);
                $scoreSheets = $this->insertFrameScoreSheets(
                    $tournament,
                    $event,
                    $participants['map'],
                );
                $snapshotSummary = $this->synchronizeFinalSnapshot($tournament, $records);

                $finalSnapshot = TournamentResultSnapshot::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('is_current', true)
                    ->where('is_final', true)
                    ->orderByDesc('id')
                    ->firstOrFail();
                $preview = $this->publicationService->preview($tournament->fresh(), $finalSnapshot);
                if (! $preview['can_publish']) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $preview['errors']),
                    );
                }

                $publication = $this->publicationService->publish(
                    $tournament->fresh(),
                    $finalSnapshot->fresh(),
                    (int) $admin->id,
                    (string) $preview['result_checksum'],
                    self::IMPORT_MARKER.':'.$event['key'],
                );
                $audit = $this->completenessService->audit($tournament->fresh());
                if (! $audit['is_complete']) {
                    throw new RuntimeException(
                        $event['key'].': '.implode(' ', $audit['errors']),
                    );
                }

                $report['repaired'][] = [
                    'key' => $event['key'],
                    'tournament_id' => (int) $tournament->id,
                    'inserted_game_scores' => $insertedScores,
                    'stage_settings' => $stageSettings,
                    'score_sheets' => $scoreSheets,
                    'snapshots' => $snapshotSummary,
                    'publication_id' => (int) $publication->id,
                    'publication_row_count' => (int) $publication->row_count,
                    'total_game_score_count' => DB::table('game_scores')
                        ->where('tournament_id', $tournament->id)
                        ->count(),
                    'completeness' => $audit,
                ];
            }

            return $report;
        });
    }

    /** @return array<string,mixed> */
    public function dataset(): array
    {
        $path = database_path(self::DATASET_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Standard-final dataset was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $expected = [
            'event_count' => self::EXPECTED_EVENT_COUNT,
            'score_sheet_count' => self::EXPECTED_SCORE_SHEET_COUNT,
            'frame_player_count' => self::EXPECTED_FRAME_PLAYER_COUNT,
            'frame_count' => self::EXPECTED_FRAME_COUNT,
            'additional_stage_score_count' => self::EXPECTED_ADDITIONAL_STAGE_SCORE_COUNT,
            'bracket_match_count' => self::EXPECTED_BRACKET_MATCH_COUNT,
            'bracket_score_count' => self::EXPECTED_BRACKET_SCORE_COUNT,
        ];
        foreach ($expected as $key => $value) {
            if ((int) ($payload[$key] ?? -1) !== $value) {
                throw new RuntimeException("Standard-final dataset has invalid {$key}.");
            }
        }

        return $payload;
    }

    /** @return array<int,string> */
    private function validateEvent(array $event): array
    {
        $errors = [];
        foreach ($event['sources'] as $source) {
            if (preg_match('/^[a-f0-9]{64}$/', (string) ($source['sha256'] ?? '')) !== 1) {
                $errors[] = "Invalid source hash: {$source['filename']}";
            }
        }

        foreach ($event['score_sheets'] as $sheet) {
            if (($sheet['players'] ?? []) === []) {
                $errors[] = "Score sheet is empty: {$sheet['match_code']}";
                continue;
            }
            foreach ($sheet['players'] as $player) {
                if (count($player['frames'] ?? []) !== 10
                    || (int) ($player['frames'][9]['cumulative_score'] ?? -1) !== (int) $player['score']) {
                    $errors[] = "Frame total is invalid: {$sheet['match_code']} {$player['display_name']}";
                }
            }
        }

        foreach ($event['bracket_matches'] ?? [] as $match) {
            $totals = [];
            foreach ($match['players'] as $player) {
                $scoreTotal = array_sum(array_column($player['scores'], 'score'));
                if ($scoreTotal !== (int) $player['match_total_pin']) {
                    $errors[] = "Bracket total is invalid: {$match['match_code']} {$player['display_name']}";
                }
                $totals[] = $scoreTotal;
            }
            if (count(array_unique($totals)) !== count($totals)
                || collect($match['players'])->where('is_winner', true)->count() !== 1) {
                $errors[] = "Bracket winner is invalid: {$match['match_code']}";
            }
        }

        foreach ($event['additional_stages'] ?? [] as $stage) {
            foreach ($stage['rows'] as $row) {
                if (array_sum(array_column($row['games'], 'score')) !== (int) $row['stage_total_pin']
                    || count($row['games']) !== (int) $row['games_count']) {
                    $errors[] = "Additional stage total is invalid: {$stage['stage']} {$row['display_name']}";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    private function findTournament(string $eventKey, bool $lock = false): ?Tournament
    {
        $publication = TournamentResultPublication::query()
            ->where('status', TournamentResultPublication::STATUS_CURRENT)
            ->whereIn('notes', [
                self::DETAIL_MARKER.':'.$eventKey,
                self::IMPORT_MARKER.':'.$eventKey,
            ])
            ->orderByDesc('revision')
            ->first();
        if ($publication === null) {
            return null;
        }

        $query = Tournament::query()->whereKey($publication->tournament_id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array{map:array<string,object>,errors:array<int,string>} */
    private function resolveParticipants(Tournament $tournament, array $event): array
    {
        $participants = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->select([
                'id',
                'pro_bowler_id',
                'participant_type',
                'pro_bowler_license_no',
                'display_name',
            ])
            ->get();
        $byLicense = $participants->groupBy(
            fn (object $participant): string => $this->normalizeLicense(
                (string) $participant->pro_bowler_license_no,
            ),
        );
        $amateursByName = $participants
            ->filter(fn (object $participant): bool => (string) $participant->participant_type === 'amateur')
            ->groupBy(fn (object $participant): string => $this->normalizeName((string) $participant->display_name));
        $sourceRows = $this->sourcePlayerRows($event);
        $map = [];
        $missing = [];
        $ambiguous = [];

        foreach ($sourceRows as $sourceLicense => $row) {
            $isAmateur = str_starts_with((string) $row['identity'], 'A:');
            $candidates = $isAmateur
                ? $amateursByName->get($this->normalizeName((string) $row['display_name']), collect())
                : $byLicense->get($sourceLicense, collect());
            if ($candidates->count() === 1) {
                $map[$sourceLicense] = $candidates->first();
            } elseif ($candidates->isEmpty()) {
                $missing[] = $sourceLicense.' '.$row['display_name'];
            } else {
                $ambiguous[] = $sourceLicense.' '.$row['display_name'];
            }
        }

        $errors = [];
        if ($missing !== []) {
            $errors[] = 'Tournament participants are missing: '.implode(', ', array_slice($missing, 0, 10));
        }
        if ($ambiguous !== []) {
            $errors[] = 'Tournament participants are ambiguous: '.implode(', ', array_slice($ambiguous, 0, 10));
        }

        return ['map' => $map, 'errors' => $errors];
    }

    /** @return array<string,array<string,mixed>> */
    private function sourcePlayerRows(array $event): array
    {
        $rows = [];
        foreach ($event['score_sheets'] as $sheet) {
            foreach ($sheet['players'] as $player) {
                $rows[$this->normalizeLicense((string) $player['license_no'])] = $player;
            }
        }
        foreach ($event['bracket_matches'] ?? [] as $match) {
            foreach ($match['players'] as $player) {
                $rows[$this->normalizeLicense((string) $player['license_no'])] = $player;
            }
        }
        foreach ($event['additional_stages'] ?? [] as $stage) {
            foreach ($stage['rows'] as $row) {
                $rows[$this->normalizeLicense((string) $row['license_no'])] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function scoreRecords(array $event, array $participantMap): array
    {
        $records = [];
        foreach ($event['additional_stages'] ?? [] as $stage) {
            foreach ($stage['rows'] as $row) {
                foreach ($row['games'] as $game) {
                    $records[] = $this->scoreRecord(
                        $participantMap,
                        $row,
                        (string) $stage['stage'],
                        null,
                        0,
                        (int) $game['game_number'],
                        (int) $game['score'],
                    );
                }
            }
        }
        foreach ($event['bracket_matches'] ?? [] as $match) {
            foreach ($match['players'] as $player) {
                foreach ($player['scores'] as $score) {
                    $records[] = $this->scoreRecord(
                        $participantMap,
                        $player,
                        (string) $match['stage_code'],
                        $match['match_code'].':'.$player['player_slot'],
                        (int) $match['round_order'],
                        (int) $score['match_game_number'],
                        (int) $score['score'],
                    );
                }
            }
        }
        foreach ($event['score_sheets'] as $sheet) {
            foreach ($sheet['players'] as $player) {
                $records[] = $this->scoreRecord(
                    $participantMap,
                    $player,
                    (string) $sheet['stage_code'],
                    $sheet['match_code'].':'.$player['player_slot'],
                    (int) $sheet['round_order'],
                    1,
                    (int) $player['score'],
                );
            }
        }

        $groupedIndexes = [];
        foreach ($records as $index => $record) {
            $key = $record['stage'].'|'.$record['participant_id'];
            $groupedIndexes[$key][] = $index;
        }
        foreach ($groupedIndexes as $indexes) {
            usort($indexes, fn (int $left, int $right): int => [
                $records[$left]['round_order'],
                $records[$left]['entry_number'] ?? '',
                $records[$left]['match_game_number'],
            ] <=> [
                $records[$right]['round_order'],
                $records[$right]['entry_number'] ?? '',
                $records[$right]['match_game_number'],
            ]);
            foreach ($indexes as $gameIndex => $recordIndex) {
                $records[$recordIndex]['game_number'] = $gameIndex + 1;
            }
        }

        return $records;
    }

    /** @return array<string,mixed> */
    private function scoreRecord(
        array $participantMap,
        array $player,
        string $stage,
        ?string $entryNumber,
        int $roundOrder,
        int $matchGameNumber,
        int $score,
    ): array {
        $sourceLicense = $this->normalizeLicense((string) $player['license_no']);
        $participant = $participantMap[$sourceLicense] ?? null;
        if ($participant === null) {
            throw new RuntimeException("Participant mapping is missing: {$player['display_name']}");
        }

        return [
            'participant_id' => (int) $participant->id,
            'pro_bowler_id' => $participant->pro_bowler_id ? (int) $participant->pro_bowler_id : null,
            'license_number' => $this->normalizeLicense((string) $participant->pro_bowler_license_no),
            'name' => (string) $participant->display_name,
            'stage' => $stage,
            'entry_number' => $entryNumber,
            'round_order' => $roundOrder,
            'match_game_number' => $matchGameNumber,
            'score' => $score,
        ];
    }

    /** @return array<string,mixed> */
    private function compareExistingScores(Tournament $tournament, array $records): array
    {
        $stages = array_values(array_unique(array_column($records, 'stage')));
        $actualRows = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage', $stages)
            ->get();
        $expected = [];
        foreach ($records as $record) {
            $expected[$this->scoreKey($record)][] = (int) $record['score'];
        }
        $actual = [];
        foreach ($actualRows as $row) {
            $actual[$this->scoreKey([
                'stage' => $row->stage,
                'license_number' => $row->license_number,
                'entry_number' => $row->entry_number,
                'game_number' => $row->game_number,
            ])][] = (int) $row->score;
        }

        $differences = [];
        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            $expectedValues = $expected[$key] ?? [];
            $actualValues = $actual[$key] ?? [];
            sort($expectedValues);
            sort($actualValues);
            if ($expectedValues !== $actualValues) {
                $differences[] = [
                    'key' => $key,
                    'expected' => $expectedValues,
                    'actual' => $actualValues,
                ];
            }
        }

        return [
            'expected_count' => count($records),
            'actual_count' => $actualRows->count(),
            'difference_count' => count($differences),
            'difference_samples' => array_slice($differences, 0, 10),
        ];
    }

    private function scoreKey(array $record): string
    {
        return implode('|', [
            trim((string) $record['stage']),
            $this->normalizeLicense((string) $record['license_number']),
            trim((string) ($record['entry_number'] ?? '')),
            (int) $record['game_number'],
        ]);
    }

    private function clearGeneratedFinalData(Tournament $tournament, array $records): void
    {
        DB::table('tournament_match_score_sheets')
            ->where('tournament_id', $tournament->id)
            ->where('notes', 'like', '%'.self::IMPORT_MARKER.'%')
            ->delete();
        DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage', array_values(array_unique(array_column($records, 'stage'))))
            ->delete();
    }

    private function insertScoreRecords(Tournament $tournament, array $records): int
    {
        $now = now();
        $rows = array_map(fn (array $record): array => [
            'tournament_id' => $tournament->id,
            'stage' => $record['stage'],
            'shift' => null,
            'gender' => $tournament->gender,
            'license_number' => $record['license_number'],
            'name' => $record['name'],
            'entry_number' => $record['entry_number'],
            'game_number' => $record['game_number'],
            'score' => $record['score'],
            'pro_bowler_id' => $record['pro_bowler_id'],
            'tournament_participant_id' => $record['participant_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_scores')->insert($chunk);
        }

        return count($rows);
    }

    /** @return array<int,array<string,mixed>> */
    private function synchronizeStageSettings(Tournament $tournament, array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            $key = $record['stage'].'|'.$record['participant_id'];
            $counts[$record['stage']][$key] = ($counts[$record['stage']][$key] ?? 0) + 1;
        }
        $summary = [];
        foreach ($counts as $stage => $playerCounts) {
            $totalGames = max($playerCounts);
            DB::table('stage_settings')->updateOrInsert(
                ['tournament_id' => $tournament->id, 'stage' => $stage],
                [
                    'total_games' => $totalGames,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $summary[] = ['stage' => $stage, 'total_games' => $totalGames];
        }

        return $summary;
    }

    /** @return array<string,int> */
    private function insertFrameScoreSheets(
        Tournament $tournament,
        array $event,
        array $participantMap,
    ): array {
        $source = $event['sources'][0];
        $proMap = ProBowler::query()
            ->whereIn(
                'id',
                collect($participantMap)->pluck('pro_bowler_id')->filter()->unique()->all(),
            )
            ->get()
            ->keyBy('id');
        $sheetCount = 0;
        $playerCount = 0;
        $frameCount = 0;
        $now = now();

        foreach ($event['score_sheets'] as $sheet) {
            $sheetId = (int) DB::table('tournament_match_score_sheets')->insertGetId([
                'tournament_id' => $tournament->id,
                'sheet_type' => $sheet['sheet_type'],
                'stage_code' => $sheet['stage_code'],
                'match_code' => $sheet['match_code'],
                'match_label' => $sheet['match_label'],
                'match_order' => $sheet['match_order'],
                'game_number' => 1,
                'lane_label' => null,
                'is_published' => true,
                'confirmed_at' => $now,
                'notes' => self::IMPORT_MARKER.':'.$event['key'].' / '.$source['filename']
                    .' / sha256='.$source['sha256'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sheetCount++;

            foreach ($sheet['players'] as $index => $player) {
                $sourceLicense = $this->normalizeLicense((string) $player['license_no']);
                $participant = $participantMap[$sourceLicense];
                $bowler = $participant->pro_bowler_id
                    ? $proMap->get((int) $participant->pro_bowler_id)
                    : null;
                $playerId = (int) DB::table('tournament_match_score_sheet_players')->insertGetId([
                    'score_sheet_id' => $sheetId,
                    'sort_order' => $index + 1,
                    'player_slot' => $player['player_slot'],
                    'pro_bowler_id' => $participant->pro_bowler_id,
                    'pro_bowler_license_no' => $participant->pro_bowler_license_no,
                    'display_name' => $participant->display_name,
                    'name_kana' => $bowler?->name_kana,
                    'dominant_arm' => $bowler?->dominant_arm,
                    'lane_label' => null,
                    'final_score' => (int) $player['score'],
                    'is_winner' => (bool) $player['is_winner'],
                    'score_summary' => json_encode([
                        'source' => self::IMPORT_MARKER,
                        'source_pdf_sha256' => $source['sha256'],
                        'frame_candidate_count' => $player['frame_candidate_count'],
                        'frame_cumulative_corrections' => $player['frame_cumulative_corrections'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $playerCount++;

                $frames = [];
                foreach ($player['frames'] as $frame) {
                    $frames[] = [
                        'score_sheet_player_id' => $playerId,
                        'frame_no' => (int) $frame['frame_no'],
                        'throw1' => $frame['throw1'] ?: null,
                        'throw2' => $frame['throw2'] ?: null,
                        'throw3' => $frame['throw3'] ?: null,
                        'frame_score' => (int) $frame['frame_score'],
                        'cumulative_score' => (int) $frame['cumulative_score'],
                        'display_marks' => json_encode(
                            $frame['display_marks'],
                            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                        ),
                        'remaining_pins' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('tournament_match_score_frames')->insert($frames);
                $frameCount += count($frames);
            }
        }

        return ['sheets' => $sheetCount, 'players' => $playerCount, 'frames' => $frameCount];
    }

    /** @return array<string,mixed> */
    private function synchronizeFinalSnapshot(Tournament $tournament, array $records): array
    {
        $finalSnapshot = TournamentResultSnapshot::query()
            ->with('rows')
            ->where('tournament_id', $tournament->id)
            ->where('is_current', true)
            ->where('is_final', true)
            ->orderByDesc('id')
            ->firstOrFail();
        $stageNames = array_values(array_unique(array_column($records, 'stage')));
        $allScores = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->get();
        $totals = $this->scoreTotals($allScores);
        $finalTotals = $this->scoreTotals(
            $allScores->whereIn('stage', $stageNames)->values(),
        );
        $participants = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->get();
        $byLicense = $participants->keyBy(
            fn (object $participant): string => $this->normalizeLicense(
                (string) $participant->pro_bowler_license_no,
            ),
        );
        $byName = $participants->groupBy(
            fn (object $participant): string => $this->normalizeName((string) $participant->display_name),
        );

        foreach ($finalSnapshot->rows as $row) {
            $participant = $byLicense->get(
                $this->normalizeLicense((string) $row->pro_bowler_license_no),
            );
            if ($participant === null) {
                $nameMatches = $byName->get($this->normalizeName((string) $row->display_name), collect());
                $participant = $nameMatches->count() === 1 ? $nameMatches->first() : null;
            }
            if ($participant === null || ! isset($totals[(int) $participant->id])) {
                throw new RuntimeException(
                    "Final snapshot score total is missing: {$row->display_name}",
                );
            }

            $actual = $totals[(int) $participant->id];
            $final = $finalTotals[(int) $participant->id] ?? ['games' => 0, 'total_pin' => 0];
            $breakdown = is_array($row->breakdown) ? $row->breakdown : [];
            $breakdown['official_final_detail'] = [
                'source' => self::IMPORT_MARKER,
                'stages' => $stageNames,
                'final_stage_games' => $final['games'],
                'final_stage_total_pin' => $final['total_pin'],
            ];
            $row->forceFill([
                'scratch_pin' => (int) $final['total_pin'],
                'carry_pin' => (int) $actual['total_pin'] - (int) $final['total_pin'],
                'total_pin' => (int) $actual['total_pin'],
                'games' => (int) $actual['games'],
                'average' => round($actual['total_pin'] / max(1, $actual['games']), 3),
                'source_count' => max(1, (int) $row->source_count),
                'is_complete' => true,
                'breakdown' => $breakdown,
            ])->save();
        }

        $definition = is_array($finalSnapshot->calculation_definition)
            ? $finalSnapshot->calculation_definition
            : [];
        $definition['final_detail_source'] = self::IMPORT_MARKER;
        $finalSnapshot->forceFill([
            'stage_name' => '最終成績',
            'games_count' => max(array_column($totals, 'games')),
            'calculation_definition' => $definition,
            'notes' => $this->appendMarker((string) $finalSnapshot->notes),
        ])->save();

        return [
            'final_snapshot_id' => (int) $finalSnapshot->id,
            'row_count' => $finalSnapshot->rows->count(),
            'maximum_game_count' => max(array_column($totals, 'games')),
            'final_stages' => $stageNames,
        ];
    }

    /**
     * @param Collection<int,object> $scores
     * @return array<int,array{games:int,total_pin:int}>
     */
    private function scoreTotals(Collection $scores): array
    {
        $totals = [];
        foreach ($scores as $score) {
            $participantId = (int) ($score->tournament_participant_id ?? 0);
            if ($participantId === 0) {
                continue;
            }
            $totals[$participantId] ??= ['games' => 0, 'total_pin' => 0];
            $totals[$participantId]['games']++;
            $totals[$participantId]['total_pin'] += (int) $score->score;
        }

        return $totals;
    }

    private function additionalStageScoreCount(array $event): int
    {
        $count = 0;
        foreach ($event['additional_stages'] ?? [] as $stage) {
            foreach ($stage['rows'] as $row) {
                $count += count($row['games']);
            }
        }

        return $count;
    }

    private function appendMarker(string $notes): string
    {
        if (str_contains($notes, self::IMPORT_MARKER)) {
            return $notes;
        }

        return trim($notes.' / '.self::IMPORT_MARKER, ' /');
    }

    private function normalizeLicense(string $license): string
    {
        return strtoupper(trim($license));
    }

    private function normalizeName(string $name): string
    {
        $name = function_exists('mb_convert_kana')
            ? mb_convert_kana($name, 'asKV', 'UTF-8')
            : $name;

        return mb_strtolower((string) preg_replace('/[\s　・･.\-ー]+/u', '', trim($name)), 'UTF-8');
    }
}
