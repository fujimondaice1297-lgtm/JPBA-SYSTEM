<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentMatchScoreSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TournamentMatchScoreSheetController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $sheets = TournamentMatchScoreSheet::query()
            ->with(['players.frames'])
            ->where('tournament_id', $tournament->id)
            ->orderBy('match_order')
            ->orderBy('id')
            ->get();

        $playerOptions = $this->buildPlayerOptions($tournament);

        return view('tournament_match_score_sheets.index', compact('tournament', 'sheets', 'playerOptions'));
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->saveSheet($request, $tournament, null);

        return redirect()
            ->route('tournaments.match_score_sheets.index', $tournament)
            ->with('success', 'スコアシートを保存しました。');
    }

    public function edit(Tournament $tournament, TournamentMatchScoreSheet $scoreSheet): View
    {
        abort_unless((int) $scoreSheet->tournament_id === (int) $tournament->id, 404);

        $scoreSheet->load(['players.frames']);
        $playerOptions = $this->buildPlayerOptions($tournament);

        return view('tournament_match_score_sheets.index', [
            'tournament' => $tournament,
            'sheets' => TournamentMatchScoreSheet::query()
                ->with(['players.frames'])
                ->where('tournament_id', $tournament->id)
                ->orderBy('match_order')
                ->orderBy('id')
                ->get(),
            'playerOptions' => $playerOptions,
            'editingSheet' => $scoreSheet,
        ]);
    }

    public function update(Request $request, Tournament $tournament, TournamentMatchScoreSheet $scoreSheet): RedirectResponse
    {
        abort_unless((int) $scoreSheet->tournament_id === (int) $tournament->id, 404);

        $this->saveSheet($request, $tournament, $scoreSheet);

        return redirect()
            ->route('tournaments.match_score_sheets.index', $tournament)
            ->with('success', 'スコアシートを更新しました。');
    }

    private function saveSheet(Request $request, Tournament $tournament, ?TournamentMatchScoreSheet $scoreSheet): TournamentMatchScoreSheet
    {
        $validated = $request->validate([
            'sheet_type' => ['required', 'string', 'max:50'],
            'stage_code' => ['nullable', 'string', 'max:80'],
            'match_code' => ['nullable', 'string', 'max:80'],
            'match_label' => ['nullable', 'string', 'max:255'],
            'match_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'lane_label' => ['nullable', 'string', 'max:50'],
            'is_published' => ['nullable', 'boolean'],
            'confirmed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'winner_note' => ['nullable', 'string', 'max:500'],
            'players' => ['required', 'array', 'min:1', 'max:4'],
            'players.*.player_slot' => ['nullable', 'string', 'max:20'],
            'players.*.pro_bowler_id' => ['nullable', 'integer', 'exists:pro_bowlers,id'],
            'players.*.pro_bowler_license_no' => ['nullable', 'string', 'max:255'],
            'players.*.display_name' => ['nullable', 'string', 'max:255'],
            'players.*.name_kana' => ['nullable', 'string', 'max:255'],
            'players.*.dominant_arm' => ['nullable', 'string', 'max:20'],
            'players.*.lane_label' => ['nullable', 'string', 'max:50'],
            'players.*.frames' => ['nullable', 'array'],
            'players.*.frames.*.throw1' => ['nullable', 'string', 'max:2'],
            'players.*.frames.*.throw2' => ['nullable', 'string', 'max:2'],
            'players.*.frames.*.throw3' => ['nullable', 'string', 'max:2'],
            'players.*.frames.*.remaining_pins' => ['nullable', 'string', 'max:80'],
        ]);

        return DB::transaction(function () use ($validated, $tournament, $scoreSheet) {
            $this->saveShootoutWinnerNote($tournament, $validated['winner_note'] ?? null);

            $sheet = $scoreSheet ?: new TournamentMatchScoreSheet();

            $sheet->fill([
                'tournament_id' => $tournament->id,
                'sheet_type' => $validated['sheet_type'],
                'stage_code' => $validated['stage_code'] ?? null,
                'match_code' => $validated['match_code'] ?? null,
                'match_label' => $validated['match_label'] ?? null,
                'match_order' => (int) ($validated['match_order'] ?? 0),
                'game_number' => (int) ($validated['game_number'] ?? 1),
                'lane_label' => $validated['lane_label'] ?? null,
                'is_published' => (bool) ($validated['is_published'] ?? true),
                'confirmed_at' => !empty($validated['confirmed']) ? now() : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $sheet->save();

            if ($scoreSheet) {
                $sheet->players()->delete();
            }

            $createdPlayers = [];

            foreach (array_values($validated['players']) as $index => $playerInput) {
                $frames = $this->normalizeFramesForCalculator($playerInput['frames'] ?? []);
                $calculated = $this->calculateBowlingScore($frames);

                $bowler = $this->resolveBowler($playerInput);
                $displayName = trim((string) ($playerInput['display_name'] ?? ''));

                if ($displayName === '' && $bowler) {
                    $displayName = (string) ($bowler->name_kanji ?? '');
                }

                if ($displayName === '') {
                    continue;
                }

                $player = $sheet->players()->create([
                    'sort_order' => $index + 1,
                    'player_slot' => $playerInput['player_slot'] ?? chr(65 + $index),
                    'pro_bowler_id' => $bowler?->id,
                    'pro_bowler_license_no' => $bowler?->license_no ?? ($playerInput['pro_bowler_license_no'] ?? null),
                    'display_name' => $displayName,
                    'name_kana' => $bowler?->name_kana ?? ($playerInput['name_kana'] ?? null),
                    'dominant_arm' => $bowler?->dominant_arm ?? ($playerInput['dominant_arm'] ?? null),
                    'lane_label' => $playerInput['lane_label'] ?? null,
                    'final_score' => (int) $calculated['total'],
                    'is_winner' => false,
                    'score_summary' => [
                        'rolls' => $calculated['rolls'],
                    ],
                ]);

                foreach ($calculated['frames'] as $frame) {
                    $frameNo = (int) $frame['frame_no'];
                    $sourceFrame = $playerInput['frames'][$frameNo] ?? $playerInput['frames'][(string) $frameNo] ?? [];

                    $player->frames()->create([
                        'frame_no' => $frameNo,
                        'throw1' => $frame['throw1'],
                        'throw2' => $frame['throw2'],
                        'throw3' => $frame['throw3'],
                        'frame_score' => $frame['frame_score'],
                        'cumulative_score' => $frame['cumulative_score'],
                        'display_marks' => $frame['display_marks'],
                        'remaining_pins' => $this->normalizeRemainingPins($sourceFrame['remaining_pins'] ?? null),
                    ]);
                }

                $createdPlayers[] = $player;
            }

            $maxScore = collect($createdPlayers)->max('final_score');
            $winnerCount = collect($createdPlayers)->where('final_score', $maxScore)->count();

            if ($maxScore !== null && $winnerCount === 1) {
                foreach ($createdPlayers as $player) {
                    $player->forceFill([
                        'is_winner' => (int) $player->final_score === (int) $maxScore,
                    ])->save();
                }
            }

            return $sheet->fresh(['players.frames']);
        });
    }

    private function saveShootoutWinnerNote(Tournament $tournament, mixed $value): void
    {
        $settings = $tournament->shootout_settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        $winnerNote = $this->normalizeWinnerNote($value);

        if ($winnerNote === '') {
            unset($settings['winner_note']);
        } else {
            $settings['winner_note'] = $winnerNote;
        }

        $tournament->forceFill([
            'shootout_settings' => $settings,
        ])->save();
    }

    private function normalizeWinnerNote(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = array_map(
            fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line),
            explode("\n", $text)
        );

        $lines = array_values(array_filter($lines, fn (string $line): bool => $line !== ''));

        return mb_substr(implode("\n", $lines), 0, 500);
    }

    /**
     * @param array<int,array{throw1:mixed,throw2:mixed,throw3:mixed}> $frames
     * @return array{total:int,rolls:array<int,int>,frames:array<int,array<string,mixed>>}
     */
    private function calculateBowlingScore(array $frames): array
    {
        $rolls = [];
        $frameStartIndexes = [];
        $normalizedFrames = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $frames[$frameNo] ?? [
                'throw1' => null,
                'throw2' => null,
                'throw3' => null,
            ];

            $throw1 = $this->normalizeThrowMark($frame['throw1'] ?? null);
            $throw2 = $this->normalizeThrowMark($frame['throw2'] ?? null);
            $throw3 = $this->normalizeThrowMark($frame['throw3'] ?? null);

            if ($frameNo < 10 && ($throw1 === 'X' || $throw2 === 'X')) {
                $throw1 = 'X';
                $throw2 = '';
            }

            $normalizedFrames[$frameNo] = [
                'throw1' => $throw1,
                'throw2' => $throw2,
                'throw3' => $throw3,
            ];

            $frameStartIndexes[$frameNo] = count($rolls);

            if ($frameNo < 10) {
                if ($throw1 === '') {
                    continue;
                }

                if ($throw1 === 'X') {
                    $rolls[] = 10;
                    continue;
                }

                $firstPins = $this->pinsForFirstThrow($throw1);
                $rolls[] = $firstPins;

                if ($throw2 === '') {
                    continue;
                }

                $rolls[] = $throw2 === '/'
                    ? max(0, 10 - $firstPins)
                    : $this->pinsForNormalThrow($throw2);

                continue;
            }

            if ($throw1 === '') {
                continue;
            }

            $firstPins = $this->pinsForFirstThrow($throw1);
            $rolls[] = $firstPins;

            if ($throw2 === '') {
                continue;
            }

            $secondPins = $throw2 === '/'
                ? max(0, 10 - $firstPins)
                : $this->pinsForNormalThrow($throw2);

            $rolls[] = $secondPins;

            if ($throw3 === '') {
                continue;
            }

            $rolls[] = $throw3 === '/'
                ? max(0, 10 - $secondPins)
                : $this->pinsForNormalThrow($throw3);
        }

        $total = 0;
        $calculatedFrames = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $normalizedFrames[$frameNo];
            $start = $frameStartIndexes[$frameNo] ?? count($rolls);
            $frameScore = null;

            if ($frameNo < 10) {
                if ($frame['throw1'] === '') {
                    $frameScore = null;
                } elseif ($frame['throw1'] === 'X') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 1] + $rolls[$start + 2];
                    }
                } elseif ($frame['throw2'] === '/') {
                    if (isset($rolls[$start], $rolls[$start + 1], $rolls[$start + 2])) {
                        $frameScore = 10 + $rolls[$start + 2];
                    }
                } elseif ($frame['throw2'] !== '') {
                    if (isset($rolls[$start], $rolls[$start + 1])) {
                        $frameScore = $rolls[$start] + $rolls[$start + 1];
                    }
                }
            } else {
                $requiredRollCount = $this->requiredTenthFrameRollCount($frame);
                $tenthRolls = array_slice($rolls, $start);

                if ($requiredRollCount > 0 && count($tenthRolls) >= $requiredRollCount) {
                    $frameScore = array_sum($tenthRolls);
                }
            }

            if ($frameScore !== null) {
                $total += $frameScore;
            }

            $calculatedFrames[] = [
                'frame_no' => $frameNo,
                'throw1' => $frame['throw1'] !== '' ? $frame['throw1'] : null,
                'throw2' => $frame['throw2'] !== '' ? $frame['throw2'] : null,
                'throw3' => $frame['throw3'] !== '' ? $frame['throw3'] : null,
                'frame_score' => $frameScore,
                'cumulative_score' => $frameScore !== null ? $total : null,
                'display_marks' => [
                    'throw1' => $frame['throw1'] !== '' ? $frame['throw1'] : null,
                    'throw2' => $frame['throw2'] !== '' ? $frame['throw2'] : null,
                    'throw3' => $frame['throw3'] !== '' ? $frame['throw3'] : null,
                ],
            ];
        }

        return [
            'total' => $total,
            'rolls' => $rolls,
            'frames' => $calculatedFrames,
        ];
    }

    private function requiredTenthFrameRollCount(array $frame): int
    {
        if (($frame['throw1'] ?? '') === '') {
            return 0;
        }

        if (($frame['throw2'] ?? '') === '') {
            return 1;
        }

        if (($frame['throw1'] ?? '') === 'X' || ($frame['throw2'] ?? '') === '/') {
            return 3;
        }

        return 2;
    }

    private function normalizeThrowMark(mixed $value): string
    {
        $mark = strtoupper(trim((string) ($value ?? '')));
        $mark = str_replace(['×', 'Ｘ', 'ｘ'], 'X', $mark);
        $mark = str_replace(['ー', '－', '―'], '-', $mark);

        if ($mark === '' || $mark === '.') {
            return '';
        }

        if (in_array($mark, ['X', '/', '-', 'F'], true)) {
            return $mark;
        }

        if (preg_match('/^[0-9]$/', $mark)) {
            return $mark;
        }

        return '';
    }

    private function pinsForFirstThrow(string $mark): int
    {
        if ($mark === 'X') {
            return 10;
        }

        return $this->pinsForNormalThrow($mark);
    }

    private function pinsForNormalThrow(string $mark): int
    {
        if ($mark === 'X') {
            return 10;
        }

        if ($mark === '-' || $mark === 'F' || $mark === '') {
            return 0;
        }

        if (preg_match('/^[0-9]$/', $mark)) {
            return max(0, min(9, (int) $mark));
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $playerInput
     */
    private function resolveBowler(array $playerInput): ?ProBowler
    {
        if (!empty($playerInput['pro_bowler_id'])) {
            return ProBowler::find((int) $playerInput['pro_bowler_id']);
        }

        $license = trim((string) ($playerInput['pro_bowler_license_no'] ?? ''));

        if ($license !== '') {
            $normalized = strtoupper($license);

            return ProBowler::query()
                ->whereRaw('upper(license_no) = ?', [$normalized])
                ->orWhereRaw('right(upper(license_no), 4) = ?', [substr($normalized, -4)])
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $frames
     * @return array<int, array{throw1:mixed, throw2:mixed, throw3:mixed}>
     */
    private function normalizeFramesForCalculator(array $frames): array
    {
        $normalized = [];

        for ($frameNo = 1; $frameNo <= 10; $frameNo++) {
            $frame = $frames[$frameNo] ?? $frames[(string) $frameNo] ?? [];

            $normalized[$frameNo] = [
                'throw1' => $frame['throw1'] ?? null,
                'throw2' => $frame['throw2'] ?? null,
                'throw3' => $frame['throw3'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<int,int>
     */
    private function normalizeRemainingPins(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : preg_split('/[^0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            return [];
        }

        $pins = [];

        foreach ($value as $pin) {
            $pinNumber = filter_var($pin, FILTER_VALIDATE_INT);

            if ($pinNumber === false || $pinNumber < 1 || $pinNumber > 10) {
                continue;
            }

            $pins[] = $pinNumber;
        }

        $pins = array_values(array_unique($pins));
        sort($pins, SORT_NUMERIC);

        return $pins;
    }

    private function buildPlayerOptions(Tournament $tournament)
    {
        $ids = DB::table('tournament_results')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('pro_bowler_id')
            ->pluck('pro_bowler_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $ids = DB::table('tournament_result_snapshot_rows')
                ->join('tournament_result_snapshots', 'tournament_result_snapshot_rows.snapshot_id', '=', 'tournament_result_snapshots.id')
                ->where('tournament_result_snapshots.tournament_id', $tournament->id)
                ->whereNotNull('tournament_result_snapshot_rows.pro_bowler_id')
                ->pluck('tournament_result_snapshot_rows.pro_bowler_id')
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return ProBowler::query()
            ->whereIn('id', $ids->all())
            ->orderBy('license_no')
            ->get(['id', 'license_no', 'name_kanji', 'name_kana', 'dominant_arm']);
    }
}