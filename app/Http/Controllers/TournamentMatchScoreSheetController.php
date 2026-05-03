<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentMatchScoreSheet;
use App\Models\TournamentMatchScoreSheetPlayer;
use App\Services\BowlingScoreCalculatorService;
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

    public function store(Request $request, Tournament $tournament, BowlingScoreCalculatorService $calculator): RedirectResponse
    {
        $this->saveSheet($request, $tournament, null, $calculator);

        return redirect()
            ->route('tournaments.match_score_sheets.index', $tournament)
            ->with('success', 'スコアシートを保存しました。');
    }

    public function edit(Tournament $tournament, TournamentMatchScoreSheet $scoreSheet): View
    {
        abort_unless((int)$scoreSheet->tournament_id === (int)$tournament->id, 404);

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

    public function update(Request $request, Tournament $tournament, TournamentMatchScoreSheet $scoreSheet, BowlingScoreCalculatorService $calculator): RedirectResponse
    {
        abort_unless((int)$scoreSheet->tournament_id === (int)$tournament->id, 404);

        $this->saveSheet($request, $tournament, $scoreSheet, $calculator);

        return redirect()
            ->route('tournaments.match_score_sheets.index', $tournament)
            ->with('success', 'スコアシートを更新しました。');
    }

    private function saveSheet(Request $request, Tournament $tournament, ?TournamentMatchScoreSheet $scoreSheet, BowlingScoreCalculatorService $calculator): TournamentMatchScoreSheet
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
            'players' => ['required', 'array', 'min:1', 'max:4'],
            'players.*.player_slot' => ['nullable', 'string', 'max:20'],
            'players.*.pro_bowler_id' => ['nullable', 'integer', 'exists:pro_bowlers,id'],
            'players.*.pro_bowler_license_no' => ['nullable', 'string', 'max:255'],
            'players.*.display_name' => ['nullable', 'string', 'max:255'],
            'players.*.name_kana' => ['nullable', 'string', 'max:255'],
            'players.*.dominant_arm' => ['nullable', 'string', 'max:20'],
            'players.*.lane_label' => ['nullable', 'string', 'max:50'],
            'players.*.frames' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($validated, $tournament, $scoreSheet, $calculator) {
            $sheet = $scoreSheet ?: new TournamentMatchScoreSheet();
            $sheet->fill([
                'tournament_id' => $tournament->id,
                'sheet_type' => $validated['sheet_type'],
                'stage_code' => $validated['stage_code'] ?? null,
                'match_code' => $validated['match_code'] ?? null,
                'match_label' => $validated['match_label'] ?? null,
                'match_order' => (int)($validated['match_order'] ?? 0),
                'game_number' => (int)($validated['game_number'] ?? 1),
                'lane_label' => $validated['lane_label'] ?? null,
                'is_published' => (bool)($validated['is_published'] ?? true),
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
                $calculated = $calculator->calculate($frames);

                $bowler = $this->resolveBowler($playerInput);
                $displayName = trim((string)($playerInput['display_name'] ?? ''));

                if ($displayName === '' && $bowler) {
                    $displayName = (string)($bowler->name_kanji ?? '');
                }

                if ($displayName === '') {
                    continue;
                }

                $player = TournamentMatchScoreSheetPlayer::create([
                    'score_sheet_id' => $sheet->id,
                    'sort_order' => $index + 1,
                    'player_slot' => $playerInput['player_slot'] ?? chr(65 + $index),
                    'pro_bowler_id' => $bowler?->id,
                    'pro_bowler_license_no' => $bowler?->license_no ?? ($playerInput['pro_bowler_license_no'] ?? null),
                    'display_name' => $displayName,
                    'name_kana' => $bowler?->name_kana ?? ($playerInput['name_kana'] ?? null),
                    'dominant_arm' => $bowler?->dominant_arm ?? ($playerInput['dominant_arm'] ?? null),
                    'lane_label' => $playerInput['lane_label'] ?? null,
                    'final_score' => (int)$calculated['total'],
                    'is_winner' => false,
                    'score_summary' => [
                        'rolls' => $calculated['rolls'],
                    ],
                ]);

                foreach ($calculated['frames'] as $frame) {
                    $player->frames()->create([
                        'frame_no' => $frame['frame_no'],
                        'throw1' => $frame['throw1'],
                        'throw2' => $frame['throw2'],
                        'throw3' => $frame['throw3'],
                        'frame_score' => $frame['frame_score'],
                        'cumulative_score' => $frame['cumulative_score'],
                        'display_marks' => $frame['display_marks'],
                    ]);
                }

                $createdPlayers[] = $player;
            }

            $maxScore = collect($createdPlayers)->max('final_score');
            $winnerCount = collect($createdPlayers)->where('final_score', $maxScore)->count();

            if ($maxScore !== null && $winnerCount === 1) {
                foreach ($createdPlayers as $player) {
                    $player->forceFill(['is_winner' => (int)$player->final_score === (int)$maxScore])->save();
                }
            }

            return $sheet->fresh(['players.frames']);
        });
    }

    /**
     * @param array<string,mixed> $playerInput
     */
    private function resolveBowler(array $playerInput): ?ProBowler
    {
        if (!empty($playerInput['pro_bowler_id'])) {
            return ProBowler::find((int)$playerInput['pro_bowler_id']);
        }

        $license = trim((string)($playerInput['pro_bowler_license_no'] ?? ''));
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
            $frame = $frames[$frameNo] ?? $frames[(string)$frameNo] ?? [];
            $normalized[$frameNo] = [
                'throw1' => $frame['throw1'] ?? null,
                'throw2' => $frame['throw2'] ?? null,
                'throw3' => $frame['throw3'] ?? null,
            ];
        }

        return $normalized;
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
