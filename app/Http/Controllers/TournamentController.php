<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Services\TournamentResultCarryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', $request->start_date);
        }
        if ($request->filled('venue_name')) {
            $query->where('venue_name', 'like', '%' . $request->venue_name . '%');
        }

        $tournaments = $query->get();

        return view('tournaments.index', compact('tournaments'));
    }

    public function create(Request $request)
    {
        $prefill = $request->session()->pull('tournament_prefill', []);

        if (!empty($prefill) && !$request->session()->hasOldInput()) {
            $request->session()->flashInput($this->buildPrefillOldInput($prefill));
        }

        return view('tournaments.create', ['prefill' => $prefill]);
    }

    public function clone($id)
    {
        $src = Tournament::with(['organizations', 'files'])->findOrFail($id);

        $prefill = $src->only([
            'name',
            'venue_id',
            'venue_name',
            'venue_address',
            'venue_tel',
            'venue_fax',
            'gender',
            'official_type',
            'title_category',
            'result_flow_type',
            'round_robin_qualifier_count',
            'round_robin_win_bonus',
            'round_robin_tie_bonus',
            'round_robin_position_round_enabled',
            'single_elimination_qualifier_count',
            'single_elimination_seed_source_result_code',
            'single_elimination_seed_policy',
            'single_elimination_seed_settings',
            'shootout_qualifier_count',
            'shootout_seed_source_result_code',
            'shootout_format',
            'shootout_settings',
            'spectator_policy',
            'broadcast',
            'streaming',
            'broadcast_url',
            'streaming_url',
            'prize',
            'admission_fee',
            'entry_conditions',
            'materials',
            'previous_event',
            'previous_event_url',
            'inspection_required',
            'use_shift_draw',
            'shift_codes',
            'accept_shift_preference',
            'use_lane_draw',
            'lane_from',
            'lane_to',
            'lane_assignment_mode',
            'box_player_count',
            'odd_lane_player_count',
            'even_lane_player_count',
            'extra_venues',
            'sidebar_schedule',
            'award_highlights',
            'result_cards',
            'title_logo_path',
        ]);

        foreach ([
            'start_date',
            'end_date',
            'entry_start',
            'entry_end',
            'shift_draw_open_at',
            'shift_draw_close_at',
            'lane_draw_open_at',
            'lane_draw_close_at',
            'shift_auto_draw_reminder_send_on',
            'lane_auto_draw_reminder_send_on',
        ] as $dateField) {
            $prefill[$dateField] = null;
        }

        $prefill['org'] = $src->organizations->map(function ($o) {
            return [
                'category'   => $o->category,
                'name'       => $o->name,
                'url'        => $o->url,
                'sort_order' => $o->sort_order,
            ];
        })->values()->all();

        session()->flash('tournament_prefill', $prefill);

        return redirect()->route('tournaments.create')
            ->with('success', '前回大会の内容を下書きにコピーしました。日付は空にしています。アップロード済みファイルは必要に応じて見直してください。');
    }

    public function show($id)
    {
        $tournament = Tournament::with(['organizations', 'files', 'venue'])->findOrFail($id);

        return view('tournaments.show', compact('tournament'));
    }

    public function edit($id)
    {
        $tournament = Tournament::with(['organizations', 'files', 'venue'])->findOrFail($id);

        return view('tournaments.edit', compact('tournament'));
    }

    private function buildPrefillOldInput(array $prefill): array
    {
        $old = $prefill;

        foreach ([
            'inspection_required',
            'use_shift_draw',
            'accept_shift_preference',
            'use_lane_draw',
        ] as $booleanKey) {
            $old[$booleanKey] = !empty($prefill[$booleanKey]) ? 1 : 0;
        }

        foreach ([
            'start_date',
            'end_date',
            'entry_start',
            'entry_end',
            'shift_draw_open_at',
            'shift_draw_close_at',
            'lane_draw_open_at',
            'lane_draw_close_at',
            'shift_auto_draw_reminder_send_on',
            'lane_auto_draw_reminder_send_on',
        ] as $dateField) {
            $old[$dateField] = null;
        }

        $old['org'] = $prefill['org'] ?? [];
        $old['schedule'] = $this->buildPrefillScheduleRows($prefill['sidebar_schedule'] ?? []);
        $old['awards'] = $this->buildPrefillAwardRows($prefill['award_highlights'] ?? []);
        $old['result_cards'] = $this->buildPrefillResultCardRows($prefill['result_cards'] ?? []);

        unset(
            $old['sidebar_schedule'],
            $old['award_highlights'],
            $old['gallery_items'],
            $old['simple_result_pdfs'],
            $old['result_cards']
        );

        return $old;
    }

    private function buildPrefillScheduleRows(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                return [
                    'date'      => (string) ($row['date'] ?? ''),
                    'label'     => (string) ($row['label'] ?? ($row['title'] ?? '')),
                    'url'       => $this->normalizePrefillUrl($row['href'] ?? ($row['url'] ?? '')),
                    'separator' => !empty($row['separator']) ? 1 : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function buildPrefillAwardRows(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                return [
                    'type'   => (string) ($row['type'] ?? ($row['category'] ?? 'perfect')),
                    'player' => (string) ($row['player'] ?? ''),
                    'game'   => (string) ($row['game'] ?? ''),
                    'lane'   => (string) ($row['lane'] ?? ''),
                    'note'   => (string) ($row['note'] ?? ''),
                    'title'  => (string) ($row['title'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPrefillResultCardRows(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                return [
                    'title'  => (string) ($row['title'] ?? ''),
                    'player' => (string) ($row['player'] ?? ''),
                    'balls'  => (string) ($row['balls'] ?? ''),
                    'note'   => (string) ($row['note'] ?? ''),
                    'url'    => $this->normalizePrefillUrl($row['url'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizePrefillUrl($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : '';
    }

    private function normalizeShiftCodes(?string $shiftCodes): ?string
    {
        $normalized = collect(explode(',', (string) $shiftCodes))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(',');

        return $normalized !== '' ? $normalized : null;
    }

    private function defaultShootoutSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_shootout_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_shootout_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    private function normalizeShootoutSettings(Request $request, bool $usesShootout, ?int $shootoutQualifierCount): ?array
    {
        if (!$usesShootout) {
            return null;
        }

        $shootoutSettingsRaw = trim((string) $request->input('shootout_settings', ''));
        $settings = [];

        if ($shootoutSettingsRaw !== '') {
            $decodedShootoutSettings = json_decode($shootoutSettingsRaw, true);

            if (!is_array($decodedShootoutSettings)) {
                throw ValidationException::withMessages([
                    'shootout_settings' => 'シュートアウト詳細設定JSONの形式が正しくありません。',
                ]);
            }

            $settings = $decodedShootoutSettings;
        }

        $stageProgress = $this->normalizeShootoutStageProgress($request, $shootoutQualifierCount);

        if (!empty($stageProgress)) {
            $settings['stage_progress'] = $stageProgress;
        } else {
            unset($settings['stage_progress']);
        }

        return !empty($settings) ? $settings : null;
    }

    private function normalizeShootoutStageProgress(Request $request, ?int $shootoutQualifierCount): array
    {
        $input = $request->input('shootout_stage_progress', []);

        if (!is_array($input)) {
            return [];
        }

        $keys = [
            'prelim_player_count',
            'prelim_game_count',
            'prelim_qualifier_count',
            'semifinal_game_count',
            'semifinal_total_game_count',
        ];

        $stageProgress = [];

        foreach ($keys as $key) {
            $value = $input[$key] ?? null;

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $stageProgress[$key] = (int) $value;
        }

        if ($shootoutQualifierCount !== null && $shootoutQualifierCount > 0) {
            $stageProgress['semifinal_qualifier_count'] = $shootoutQualifierCount;
        }

        if (empty($stageProgress)) {
            return [];
        }

        if (
            isset($stageProgress['prelim_player_count'], $stageProgress['prelim_qualifier_count'])
            && $stageProgress['prelim_qualifier_count'] > $stageProgress['prelim_player_count']
        ) {
            throw ValidationException::withMessages([
                'shootout_stage_progress.prelim_qualifier_count' => '準決勝進出人数は、予選参加人数以下で指定してください。',
            ]);
        }

        if (
            isset($stageProgress['prelim_qualifier_count'], $stageProgress['semifinal_qualifier_count'])
            && $stageProgress['semifinal_qualifier_count'] > $stageProgress['prelim_qualifier_count']
        ) {
            throw ValidationException::withMessages([
                'shootout_qualifier_count' => 'SO進出人数は、準決勝進出人数以下で指定してください。',
            ]);
        }

        if (
            isset($stageProgress['prelim_game_count'], $stageProgress['semifinal_game_count'], $stageProgress['semifinal_total_game_count'])
            && $stageProgress['semifinal_total_game_count'] < ($stageProgress['prelim_game_count'] + $stageProgress['semifinal_game_count'])
        ) {
            throw ValidationException::withMessages([
                'shootout_stage_progress.semifinal_total_game_count' => '準決勝通算ゲーム数は、予選ゲーム数＋準決勝ゲーム数以上で指定してください。',
            ]);
        }

        return $stageProgress;
    }

    private function normalizeSingleEliminationSeedSettings($value): ?array
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return empty($value) ? null : $value;
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ValidationException::withMessages([
                'single_elimination_seed_settings' => 'シード詳細設定はJSON形式で入力してください。',
            ]);
        }

        return empty($decoded) ? null : $decoded;
    }


    private function normalizeSingleEliminationLaneSettings(Request $request): array
    {
        $settings = [];
        $rounds = [];

        $roundInputs = $request->input('single_elimination_lane_rounds', []);
        if (!is_array($roundInputs)) {
            $roundInputs = [];
        }

        foreach ($roundInputs as $roundNo => $row) {
            if (!is_array($row)) {
                continue;
            }

            $roundNo = (int) $roundNo;
            if ($roundNo <= 0) {
                continue;
            }

            $startLane = (int) ($row['start_lane'] ?? 0);
            if ($startLane <= 0) {
                continue;
            }

            $step = (int) ($row['step'] ?? 2);
            $width = (int) ($row['width'] ?? 2);

            $rounds[(string) $roundNo] = [
                'start_lane' => $startLane,
                'step' => max(1, $step),
                'width' => max(1, $width),
            ];
        }

        if (!empty($rounds)) {
            $settings['rounds'] = $rounds;
        }

        $matchInputs = $request->input('single_elimination_match_lanes', []);
        if (!is_array($matchInputs)) {
            $matchInputs = [];
        }

        $matches = [];
        foreach ($matchInputs as $matchKey => $laneLabel) {
            $matchKey = trim((string) $matchKey);
            $laneLabel = trim((string) $laneLabel);

            if ($matchKey === '' || $laneLabel === '') {
                continue;
            }

            $matches[$matchKey] = $laneLabel;
        }

        if (!empty($matches)) {
            $settings['matches'] = $matches;
        }

        return $settings;
    }

    private function normalizeResultCarrySettings(string $preset, $customJson): array
    {
        $service = app(TournamentResultCarryService::class);
        $preset = $service->canonicalPresetKey($preset);

        if ($preset === 'custom') {
            $json = trim((string) $customJson);

            if ($json === '') {
                return [];
            }

            $decoded = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw ValidationException::withMessages([
                    'result_carry_settings' => '成績持ち込み設定はJSON形式で入力してください。',
                ]);
            }

            return $service->normalizeSettings('custom', $decoded);
        }

        return $service->presetSettings($preset);
    }


    private function normalizeLaneMovementSettings(Request $request): ?string
    {
        $input = (array) $request->input('lane_movement', []);
        $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            return null;
        }

        $laneFrom = $request->filled('lane_from') ? (int) $request->input('lane_from') : (int) ($input['lane_from'] ?? 0);
        $laneTo = $request->filled('lane_to') ? (int) $request->input('lane_to') : (int) ($input['lane_to'] ?? 0);
        $boxWidth = (int) ($input['box_width'] ?? 2);
        $games = (int) ($input['games'] ?? 0);
        $startTime = trim((string) ($input['start_time'] ?? ''));
        $regularMoveBoxes = (int) ($input['regular_move_boxes'] ?? 1);
        $halfTurnEnabled = filter_var($input['half_turn_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $halfTurnGame = $halfTurnEnabled ? (int) ($input['half_turn_game'] ?? 0) : null;
        $halfTurnMoveBoxes = $halfTurnEnabled ? (int) ($input['half_turn_move_boxes'] ?? 0) : null;
        $direction = (string) ($input['direction'] ?? 'right');
        $wrap = ! array_key_exists('wrap', $input) || filter_var($input['wrap'], FILTER_VALIDATE_BOOLEAN);

        $day1Label = trim((string) ($input['day1_label'] ?? ''));
        $secondDayEnabled = filter_var($input['second_day_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $day2Label = trim((string) ($input['day2_label'] ?? ''));
        $day2StartGame = $secondDayEnabled ? (int) ($input['day2_start_game'] ?? 0) : null;
        $day2Games = $secondDayEnabled ? (int) ($input['day2_games'] ?? 0) : null;
        $day2StartTime = trim((string) ($input['day2_start_time'] ?? ''));
        $day2StartMoveBoxes = $secondDayEnabled ? (int) ($input['day2_start_move_boxes'] ?? 0) : null;
        $day2RegularMoveBoxes = $secondDayEnabled ? (int) ($input['day2_regular_move_boxes'] ?? $regularMoveBoxes) : null;
        $day2Direction = (string) ($input['day2_direction'] ?? $direction);
        $day2HalfTurnEnabled = $secondDayEnabled && filter_var($input['day2_half_turn_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $day2HalfTurnGame = $day2HalfTurnEnabled ? (int) ($input['day2_half_turn_game'] ?? 0) : null;
        $day2HalfTurnMoveBoxes = $day2HalfTurnEnabled ? (int) ($input['day2_half_turn_move_boxes'] ?? 0) : null;
        $day2Wrap = ! array_key_exists('day2_wrap', $input) || filter_var($input['day2_wrap'], FILTER_VALIDATE_BOOLEAN);

        if ($laneFrom < 1 || $laneTo < $laneFrom) {
            throw ValidationException::withMessages(['lane_movement.lane_from' => 'レーン移動表を作成する場合は、使用レーン開始・終了を正しく入力してください。']);
        }
        if ($boxWidth < 1 || $boxWidth > 20) {
            throw ValidationException::withMessages(['lane_movement.box_width' => '1BOXのレーン数は1〜20で指定してください。']);
        }
        $laneCount = $laneTo - $laneFrom + 1;
        if ($laneCount % $boxWidth !== 0) {
            throw ValidationException::withMessages(['lane_movement.box_width' => '使用レーン数は1BOXのレーン数で割り切れるようにしてください。']);
        }
        if ($games < 1 || $games > 99) {
            throw ValidationException::withMessages(['lane_movement.games' => 'レーン移動表のゲーム数は1〜99で指定してください。']);
        }
        if ($startTime !== '' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            throw ValidationException::withMessages(['lane_movement.start_time' => '1G目開始時刻は HH:MM 形式で指定してください。']);
        }
        if ($regularMoveBoxes < 0 || $regularMoveBoxes > 99) {
            throw ValidationException::withMessages(['lane_movement.regular_move_boxes' => '通常移動BOX数は0〜99で指定してください。']);
        }
        if (! in_array($direction, ['right', 'left'], true)) {
            throw ValidationException::withMessages(['lane_movement.direction' => '移動方向は右へ、または左へを選択してください。']);
        }
        if ($halfTurnEnabled) {
            if ($halfTurnGame < 2 || $halfTurnGame > $games) {
                throw ValidationException::withMessages(['lane_movement.half_turn_game' => '後半開始ゲームは2G目以降、かつ総ゲーム数以内で指定してください。']);
            }
            if ($halfTurnMoveBoxes < 0 || $halfTurnMoveBoxes > 99) {
                throw ValidationException::withMessages(['lane_movement.half_turn_move_boxes' => '後半開始時の移動BOX数は0〜99で指定してください。']);
            }
        }

        $settings = [
            'enabled' => true,
            'lane_from' => $laneFrom,
            'lane_to' => $laneTo,
            'box_width' => $boxWidth,
            'games' => $games,
            'start_time' => $startTime !== '' ? $startTime : null,
            'regular_move_boxes' => $regularMoveBoxes,
            'half_turn_enabled' => $halfTurnEnabled,
            'half_turn_game' => $halfTurnEnabled ? $halfTurnGame : null,
            'half_turn_move_boxes' => $halfTurnEnabled ? $halfTurnMoveBoxes : null,
            'direction' => $direction,
            'wrap' => $wrap,
            'day1_label' => $day1Label !== '' ? $day1Label : null,
            'second_day_enabled' => $secondDayEnabled,
        ];

        if ($secondDayEnabled) {
            if ($day2StartGame < 2 || $day2StartGame > $games) {
                throw ValidationException::withMessages(['lane_movement.day2_start_game' => '2日目開始ゲームは2G目以降、かつ総ゲーム数以内で指定してください。']);
            }
            if ($day2Games < 1 || ($day2StartGame + $day2Games - 1) > $games) {
                throw ValidationException::withMessages(['lane_movement.day2_games' => '2日目対象ゲーム数は、総ゲーム数の範囲内になるように指定してください。']);
            }
            if ($day2StartTime !== '' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $day2StartTime)) {
                throw ValidationException::withMessages(['lane_movement.day2_start_time' => '2日目開始時刻は HH:MM 形式で指定してください。']);
            }
            if ($day2StartMoveBoxes < 0 || $day2StartMoveBoxes > 99) {
                throw ValidationException::withMessages(['lane_movement.day2_start_move_boxes' => '2日目開始BOX補正は0〜99で指定してください。']);
            }
            if ($day2RegularMoveBoxes < 0 || $day2RegularMoveBoxes > 99) {
                throw ValidationException::withMessages(['lane_movement.day2_regular_move_boxes' => '2日目通常移動BOX数は0〜99で指定してください。']);
            }
            if (! in_array($day2Direction, ['right', 'left'], true)) {
                throw ValidationException::withMessages(['lane_movement.day2_direction' => '2日目の移動方向は右へ、または左へを選択してください。']);
            }
            if ($day2HalfTurnEnabled) {
                if ($day2HalfTurnGame < $day2StartGame || $day2HalfTurnGame > ($day2StartGame + $day2Games - 1)) {
                    throw ValidationException::withMessages(['lane_movement.day2_half_turn_game' => '2日目の途中移動ゲームは、2日目の対象ゲーム範囲内で指定してください。']);
                }
                if ($day2HalfTurnMoveBoxes < 0 || $day2HalfTurnMoveBoxes > 99) {
                    throw ValidationException::withMessages(['lane_movement.day2_half_turn_move_boxes' => '2日目途中移動BOX数は0〜99で指定してください。']);
                }
            }

            $day1GameTo = $day2StartGame - 1;
            // 編集画面で再表示しやすいように、2日目入力値もトップレベルへ保持する。
            // 実際のレーン表計算は day_blocks を正本として使う。
            $settings['day2_label'] = $day2Label !== '' ? $day2Label : null;
            $settings['day2_start_game'] = $day2StartGame;
            $settings['day2_games'] = $day2Games;
            $settings['day2_start_time'] = $day2StartTime !== '' ? $day2StartTime : null;
            $settings['day2_start_move_boxes'] = $day2StartMoveBoxes;
            $settings['day2_regular_move_boxes'] = $day2RegularMoveBoxes;
            $settings['day2_direction'] = $day2Direction;
            $settings['day2_half_turn_enabled'] = $day2HalfTurnEnabled;
            $settings['day2_half_turn_game'] = $day2HalfTurnEnabled ? $day2HalfTurnGame : null;
            $settings['day2_half_turn_move_boxes'] = $day2HalfTurnEnabled ? $day2HalfTurnMoveBoxes : null;
            $settings['day2_wrap'] = $day2Wrap;

            $settings['day_blocks'] = [
                [
                    'key' => 'day1',
                    'label' => $day1Label !== '' ? $day1Label : '1日目',
                    'game_from' => 1,
                    'game_to' => $day1GameTo,
                    'games' => max(1, $day1GameTo),
                    'start_time' => $startTime !== '' ? $startTime : null,
                    'start_move_boxes' => 0,
                    'regular_move_boxes' => $regularMoveBoxes,
                    'half_turn_enabled' => $halfTurnEnabled,
                    'half_turn_game' => $halfTurnEnabled ? $halfTurnGame : null,
                    'half_turn_move_boxes' => $halfTurnEnabled ? $halfTurnMoveBoxes : null,
                    'direction' => $direction,
                    'wrap' => $wrap,
                ],
                [
                    'key' => 'day2',
                    'label' => $day2Label !== '' ? $day2Label : '2日目',
                    'game_from' => $day2StartGame,
                    'game_to' => $day2StartGame + $day2Games - 1,
                    'games' => $day2Games,
                    'start_time' => $day2StartTime !== '' ? $day2StartTime : null,
                    'start_move_boxes' => $day2StartMoveBoxes,
                    'regular_move_boxes' => $day2RegularMoveBoxes,
                    'half_turn_enabled' => $day2HalfTurnEnabled,
                    'half_turn_game' => $day2HalfTurnEnabled ? $day2HalfTurnGame : null,
                    'half_turn_move_boxes' => $day2HalfTurnEnabled ? $day2HalfTurnMoveBoxes : null,
                    'direction' => $day2Direction,
                    'wrap' => $day2Wrap,
                ],
            ];
        }

        return json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateAndNormalize(Request $request): array
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'start_date'           => 'nullable|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
            'venue_name'           => 'nullable|string',
            'venue_address'        => 'nullable|string',
            'venue_tel'            => 'nullable|string',
            'venue_fax'            => 'nullable|string',
            'gender'               => 'required|in:M,F,X',
            'official_type'        => 'required|in:official,approved,other',
            'title_category'       => 'nullable|in:normal,season_trial,excluded',
            'result_flow_type'     => 'nullable|in:legacy_standard,prelim_to_rr_to_final,prelim_to_quarterfinal_to_rr_to_final,prelim_to_single_elimination_to_final,prelim_to_quarterfinal_to_single_elimination_to_final,prelim_to_semifinal_to_single_elimination_to_final,prelim_to_shootout_to_final,prelim_to_quarterfinal_to_shootout_to_final,prelim_to_semifinal_to_shootout_to_final',
            'round_robin_qualifier_count' => 'nullable|integer|min:4|max:16',
            'round_robin_win_bonus' => 'nullable|integer|min:0|max:200',
            'round_robin_tie_bonus' => 'nullable|integer|min:0|max:200',
            'round_robin_position_round_enabled' => 'nullable|boolean',
            'single_elimination_qualifier_count' => 'nullable|integer|min:2|max:64',
            'single_elimination_seed_source_result_code' => 'nullable|in:prelim_total,quarterfinal_total,semifinal_total',
            'single_elimination_seed_policy' => 'nullable|in:standard,higher_seed_bye,custom',
            'single_elimination_seed_settings' => 'nullable|string|max:10000',
            'single_elimination_lane_rounds' => 'nullable|array',
            'single_elimination_lane_rounds.*.start_lane' => 'nullable|integer|min:1|max:999',
            'single_elimination_lane_rounds.*.step' => 'nullable|integer|min:1|max:50',
            'single_elimination_lane_rounds.*.width' => 'nullable|integer|min:1|max:20',
            'single_elimination_match_lanes' => 'nullable|array',
            'single_elimination_match_lanes.*' => 'nullable|string|max:50',
            'shootout_qualifier_count' => 'nullable|integer|min:2|max:32',
            'shootout_seed_source_result_code' => 'nullable|in:prelim_total,quarterfinal_total,semifinal_total',
            'shootout_format' => 'nullable|in:standard_8,custom',
            'shootout_settings' => 'nullable|string',
            'shootout_stage_progress' => 'nullable|array',
            'shootout_stage_progress.prelim_player_count' => 'nullable|integer|min:1|max:999',
            'shootout_stage_progress.prelim_game_count' => 'nullable|integer|min:1|max:99',
            'shootout_stage_progress.prelim_qualifier_count' => 'nullable|integer|min:1|max:999',
            'shootout_stage_progress.semifinal_game_count' => 'nullable|integer|min:1|max:99',
            'shootout_stage_progress.semifinal_total_game_count' => 'nullable|integer|min:1|max:199',
            'result_carry_preset' => 'nullable|in:' . implode(',', app(TournamentResultCarryService::class)->allowedPresetKeys()),
            'result_carry_settings' => 'nullable|string|max:20000',
            'entry_start'          => 'nullable|date',
            'entry_end'            => 'nullable|date|after_or_equal:entry_start',
            'inspection_required'  => 'nullable|boolean',

            'spectator_policy'     => 'nullable|in:paid,free,none',
            'prize'                => 'nullable|string',
            'admission_fee'        => 'nullable|string',
            'broadcast'            => 'nullable|string',
            'streaming'            => 'nullable|string',
            'broadcast_url'        => 'nullable|string|max:255',
            'streaming_url'        => 'nullable|string|max:255',
            'previous_event'       => 'nullable|string',
            'previous_event_url'   => 'nullable|string|max:255',
            'entry_conditions'     => 'nullable|string',
            'materials'            => 'nullable|string',

            'venue_id'             => 'nullable|integer|exists:venues,id',

            'extra_venues'                 => 'nullable|array|max:4',
            'extra_venues.*.venue_id'      => 'nullable|integer|exists:venues,id',
            'extra_venues.*.name'          => 'nullable|string|max:255',
            'extra_venues.*.address'       => 'nullable|string|max:255',
            'extra_venues.*.tel'           => 'nullable|string|max:50',
            'extra_venues.*.fax'           => 'nullable|string|max:50',
            'extra_venues.*.website_url'   => 'nullable|string|max:255',
            'extra_venues.*.memo'          => 'nullable|string|max:2000',

            'use_shift_draw'               => 'nullable|boolean',
            'shift_codes'                  => 'nullable|string|max:255',
            'accept_shift_preference'      => 'nullable|boolean',
            'shift_draw_open_at'           => 'nullable|date',
            'shift_draw_close_at'          => 'nullable|date|after_or_equal:shift_draw_open_at',

            'use_lane_draw'                => 'nullable|boolean',
            'lane_assignment_mode'         => 'nullable|in:single_lane,box',
            'lane_from'                    => 'nullable|integer|min:1',
            'lane_to'                      => 'nullable|integer|gte:lane_from|max:999',
            'lane_draw_open_at'            => 'nullable|date',
            'lane_draw_close_at'           => 'nullable|date|after_or_equal:lane_draw_open_at',
            'box_player_count'             => 'nullable|integer|min:1|max:12',
            'odd_lane_player_count'        => 'nullable|integer|min:1|max:12',
            'even_lane_player_count'       => 'nullable|integer|min:1|max:12',
            'lane_movement'                 => 'nullable|array',
            'lane_movement.enabled'         => 'nullable|boolean',
            'lane_movement.box_width'       => 'nullable|integer|min:1|max:20',
            'lane_movement.games'           => 'nullable|integer|min:1|max:99',
            'lane_movement.start_time'      => 'nullable|date_format:H:i',
            'lane_movement.regular_move_boxes' => 'nullable|integer|min:0|max:99',
            'lane_movement.half_turn_enabled' => 'nullable|boolean',
            'lane_movement.half_turn_game'  => 'nullable|integer|min:2|max:99',
            'lane_movement.half_turn_move_boxes' => 'nullable|integer|min:0|max:99',
            'lane_movement.direction'       => 'nullable|in:right,left',
            'lane_movement.wrap'            => 'nullable|boolean',
            'lane_movement.day1_label'      => 'nullable|string|max:255',
            'lane_movement.second_day_enabled' => 'nullable|boolean',
            'lane_movement.day2_label'      => 'nullable|string|max:255',
            'lane_movement.day2_start_game' => 'nullable|integer|min:2|max:99',
            'lane_movement.day2_games'      => 'nullable|integer|min:1|max:99',
            'lane_movement.day2_start_time' => 'nullable|date_format:H:i',
            'lane_movement.day2_start_move_boxes' => 'nullable|integer|min:0|max:99',
            'lane_movement.day2_regular_move_boxes' => 'nullable|integer|min:0|max:99',
            'lane_movement.day2_direction'  => 'nullable|in:right,left',
            'lane_movement.day2_half_turn_enabled' => 'nullable|boolean',
            'lane_movement.day2_half_turn_game' => 'nullable|integer|min:2|max:99',
            'lane_movement.day2_half_turn_move_boxes' => 'nullable|integer|min:0|max:99',
            'lane_movement.day2_wrap'       => 'nullable|boolean',

            'schedule'                     => 'sometimes|array',
            'awards'                       => 'sometimes|array',
            'result_cards'                 => 'sometimes|array',
            'org'                          => 'sometimes|array',
        ]);

        if ($request->filled('audience') && empty($validated['spectator_policy'])) {
            $map = [
                '可(有料)' => 'paid',
                '可（有料）' => 'paid',
                '可(無料)' => 'free',
                '可（無料）' => 'free',
                '不可' => 'none',
            ];
            $aud = trim((string) $request->input('audience'));
            if (isset($map[$aud])) {
                $validated['spectator_policy'] = $map[$aud];
            }
        }

        foreach (['broadcast_url', 'streaming_url', 'previous_event_url'] as $key) {
            if (!empty($validated[$key]) && !preg_match('~^https?://~i', $validated[$key])) {
                $validated[$key] = 'https://' . ltrim($validated[$key]);
            }
        }

        if ($request->filled('venue_id')) {
            $venue = \App\Models\Venue::find($request->input('venue_id'));
            if ($venue) {
                $validated['venue_name'] = $validated['venue_name'] ?? $venue->name;
                $validated['venue_address'] = $validated['venue_address'] ?? $venue->address;
                $validated['venue_tel'] = $validated['venue_tel'] ?? $venue->tel;
                $validated['venue_fax'] = $validated['venue_fax'] ?? $venue->fax;
            }
        }

        $useShiftDraw = $request->boolean('use_shift_draw');
        $useLaneDraw = $request->boolean('use_lane_draw');
        $laneAssignmentMode = (string) ($validated['lane_assignment_mode'] ?? 'single_lane');

        $validated['entry_start'] = $request->filled('entry_start')
            ? Carbon::parse($request->input('entry_start'))
            : null;
        $validated['entry_end'] = $request->filled('entry_end')
            ? Carbon::parse($request->input('entry_end'))
            : null;

        $validated['inspection_required'] = $request->boolean('inspection_required');

        $flowType = trim((string) ($validated['result_flow_type'] ?? 'legacy_standard')) ?: 'legacy_standard';

        $usesRoundRobin = in_array($flowType, [
            'prelim_to_rr_to_final',
            'prelim_to_quarterfinal_to_rr_to_final',
        ], true);

        $usesSingleElimination = in_array($flowType, [
            'prelim_to_single_elimination_to_final',
            'prelim_to_quarterfinal_to_single_elimination_to_final',
            'prelim_to_semifinal_to_single_elimination_to_final',
        ], true);

        $usesShootout = in_array($flowType, [
            'prelim_to_shootout_to_final',
            'prelim_to_quarterfinal_to_shootout_to_final',
            'prelim_to_semifinal_to_shootout_to_final',
        ], true);
        $usesSingleElimination = in_array($flowType, [
            'prelim_to_single_elimination_to_final',
            'prelim_to_quarterfinal_to_single_elimination_to_final',
            'prelim_to_semifinal_to_single_elimination_to_final',
        ], true);

        $validated['result_flow_type'] = $flowType;

        $validated['round_robin_qualifier_count'] = $usesRoundRobin
            ? (int) ($request->input('round_robin_qualifier_count', 8) ?: 8)
            : null;
        $validated['round_robin_win_bonus'] = $usesRoundRobin
            ? (int) ($request->input('round_robin_win_bonus', 30) ?: 30)
            : null;
        $validated['round_robin_tie_bonus'] = $usesRoundRobin
            ? (int) ($request->input('round_robin_tie_bonus', 15) ?: 15)
            : null;
        $validated['round_robin_position_round_enabled'] = $usesRoundRobin
            ? $request->boolean('round_robin_position_round_enabled', true)
            : false;

        if ($usesRoundRobin && (($validated['round_robin_qualifier_count'] ?? 0) % 2 !== 0)) {
            throw ValidationException::withMessages([
                'round_robin_qualifier_count' => 'ラウンドロビン進出人数は偶数で指定してください。',
            ]);
        }

        $fixedSeedSource = match ($flowType) {
            'prelim_to_quarterfinal_to_single_elimination_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_single_elimination_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };

        $singleEliminationSeedSettings = $this->normalizeSingleEliminationSeedSettings(
            $request->input('single_elimination_seed_settings')
        ) ?? [];
        $singleEliminationLaneSettings = $this->normalizeSingleEliminationLaneSettings($request);
        $hasSingleEliminationSettingInput = $usesSingleElimination
            || !empty($singleEliminationSeedSettings)
            || !empty($singleEliminationLaneSettings)
            || $request->filled('single_elimination_qualifier_count')
            || $request->filled('single_elimination_seed_policy');

        $validated['single_elimination_qualifier_count'] = $hasSingleEliminationSettingInput
            ? (int) ($request->input('single_elimination_qualifier_count', 8) ?: 8)
            : null;

        // 進出元成績は result_flow_type と矛盾しないよう、Controller側で固定する。
        // ただし、既存のテスト大会など result_flow_type が未設定でもトーナメント速報を使うケースがあるため、
        // レーン設定やseed設定が入力されている場合は single_elimination_* 系設定を消さずに保持する。
        $validated['single_elimination_seed_source_result_code'] = $usesSingleElimination
            ? $fixedSeedSource
            : ($hasSingleEliminationSettingInput
                ? (trim((string) $request->input('single_elimination_seed_source_result_code', '')) ?: 'prelim_total')
                : null);

        $validated['single_elimination_seed_policy'] = $hasSingleEliminationSettingInput
            ? ($request->input('single_elimination_seed_policy') ?: 'standard')
            : null;

        if ($hasSingleEliminationSettingInput) {
            if (!empty($singleEliminationLaneSettings)) {
                $singleEliminationSeedSettings['lane_settings'] = $singleEliminationLaneSettings;
            } elseif ($request->has('single_elimination_lane_rounds') || $request->has('single_elimination_match_lanes')) {
                unset($singleEliminationSeedSettings['lane_settings']);
            }

            $validated['single_elimination_seed_settings'] = empty($singleEliminationSeedSettings)
                ? null
                : $singleEliminationSeedSettings;
        } else {
            $validated['single_elimination_seed_settings'] = null;
        }

        $validated['shootout_qualifier_count'] = $usesShootout
            ? (int) ($request->input('shootout_qualifier_count', 8) ?: 8)
            : null;

        $validated['shootout_seed_source_result_code'] = $usesShootout
            ? (trim((string) $request->input('shootout_seed_source_result_code', '')) ?: $this->defaultShootoutSeedSourceResultCode($flowType))
            : null;

        $validated['shootout_format'] = $usesShootout
            ? (trim((string) $request->input('shootout_format', 'standard_8')) ?: 'standard_8')
            : null;

        $validated['shootout_settings'] = $this->normalizeShootoutSettings(
            request: $request,
            usesShootout: $usesShootout,
            shootoutQualifierCount: $validated['shootout_qualifier_count']
        );

        if ($usesShootout && $validated['shootout_qualifier_count'] < 2) {
            throw ValidationException::withMessages([
                'shootout_qualifier_count' => 'シュートアウト進出人数は2名以上で指定してください。',
            ]);
        }

        if ($usesShootout && $validated['shootout_format'] === 'standard_8' && $validated['shootout_qualifier_count'] !== 8) {
            throw ValidationException::withMessages([
                'shootout_qualifier_count' => '標準シュートアウトは8名で指定してください。',
            ]);
        }

        $carryPreset = app(TournamentResultCarryService::class)->canonicalPresetKey(
            trim((string) ($request->input('result_carry_preset', 'default') ?: 'default'))
        );

        $validated['result_carry_preset'] = $carryPreset;
        $validated['result_carry_settings'] = $this->normalizeResultCarrySettings(
            preset: $carryPreset,
            customJson: $request->input('result_carry_settings')
        );

        // レーン移動表ルールは画面入力用配列 lane_movement を
        // tournaments.lane_movement_settings JSON へ正規化して保存する。
        $validated['lane_movement_settings'] = $this->normalizeLaneMovementSettings($request);

        $validated['use_shift_draw'] = $useShiftDraw;
        $validated['accept_shift_preference'] = $useShiftDraw && $request->boolean('accept_shift_preference');
        $validated['shift_codes'] = $useShiftDraw
            ? $this->normalizeShiftCodes($request->input('shift_codes'))
            : null;

        unset(
            $validated['schedule'],
            $validated['awards'],
            $validated['org'],
            $validated['shootout_stage_progress'],
            $validated['lane_movement']
        );
        
        $validated['shift_draw_open_at'] = $useShiftDraw && $request->filled('shift_draw_open_at')
            ? Carbon::parse($request->input('shift_draw_open_at'))
            : null;
        $validated['shift_draw_close_at'] = $useShiftDraw && $request->filled('shift_draw_close_at')
            ? Carbon::parse($request->input('shift_draw_close_at'))
            : null;

        $validated['use_lane_draw'] = $useLaneDraw;
        $validated['lane_assignment_mode'] = $useLaneDraw ? $laneAssignmentMode : 'single_lane';
        $validated['lane_from'] = $useLaneDraw && $request->filled('lane_from')
            ? (int) $request->input('lane_from')
            : null;
        $validated['lane_to'] = $useLaneDraw && $request->filled('lane_to')
            ? (int) $request->input('lane_to')
            : null;
        $validated['lane_draw_open_at'] = $useLaneDraw && $request->filled('lane_draw_open_at')
            ? Carbon::parse($request->input('lane_draw_open_at'))
            : null;
        $validated['lane_draw_close_at'] = $useLaneDraw && $request->filled('lane_draw_close_at')
            ? Carbon::parse($request->input('lane_draw_close_at'))
            : null;
        $validated['box_player_count'] = $useLaneDraw && $request->filled('box_player_count')
            ? (int) $request->input('box_player_count')
            : null;
        $validated['odd_lane_player_count'] = $useLaneDraw && $request->filled('odd_lane_player_count')
            ? (int) $request->input('odd_lane_player_count')
            : null;
        $validated['even_lane_player_count'] = $useLaneDraw && $request->filled('even_lane_player_count')
            ? (int) $request->input('even_lane_player_count')
            : null;

        if ($useShiftDraw && blank($validated['shift_codes'])) {
            throw ValidationException::withMessages([
                'shift_codes' => 'シフト抽選を使う場合は、シフト候補を1つ以上入力してください。',
            ]);
        }

        if ($useLaneDraw) {
            if (is_null($validated['lane_from']) || is_null($validated['lane_to'])) {
                throw ValidationException::withMessages([
                    'lane_from' => 'レーン抽選を使う場合は、使用レーン開始と終了を入力してください。',
                ]);
            }

            if ($laneAssignmentMode === 'box') {
                if (
                    is_null($validated['box_player_count']) ||
                    is_null($validated['odd_lane_player_count']) ||
                    is_null($validated['even_lane_player_count'])
                ) {
                    throw ValidationException::withMessages([
                        'box_player_count' => 'BOX運用を使う場合は、BOX人数と奇数/偶数レーン人数を入力してください。',
                    ]);
                }
            }

            if (
                !is_null($validated['box_player_count']) &&
                !is_null($validated['odd_lane_player_count']) &&
                !is_null($validated['even_lane_player_count']) &&
                ((int) $validated['odd_lane_player_count'] + (int) $validated['even_lane_player_count']) !== (int) $validated['box_player_count']
            ) {
                throw ValidationException::withMessages([
                    'box_player_count' => 'BOX人数は「奇数レーン人数 + 偶数レーン人数」と一致させてください。',
                ]);
            }
        }

        // 画面入力専用の配列キーは tournaments テーブルの実カラムではないため、
        // forceFill()/save() 前に必ず除外する。
        // レーン設定は single_elimination_seed_settings.lane_settings に統合済み。
        unset(
            $validated['single_elimination_lane_rounds'],
            $validated['single_elimination_match_lanes']
        );

        return $validated;
    }

    private function buildOrgRowsAndTexts(Request $request): array
    {
        $rows = [];
        $texts = [
            'host' => [],
            'special_sponsor' => [],
            'sponsor' => [],
            'support' => [],
            'cooperation' => [],
        ];

        $seen = [];

        $add = function (string $cat, ?string $name, ?string $url, int $order = 0) use (&$rows, &$texts, &$seen) {
            $name = trim((string) $name);
            if ($name === '' || !isset($texts[$cat])) {
                return;
            }

            if ($url && !preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url);
            }

            $key = strtolower($cat . '|' . $name . '|' . ($url ?? ''));
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $rows[] = new \App\Models\TournamentOrganization([
                'category' => $cat,
                'name' => $name,
                'url' => $url ?: null,
                'sort_order' => $order,
            ]);
            $texts[$cat][] = $name;
        };

        $flat = $request->input('org');

        if (is_array($flat) && array_key_exists(0, $flat)) {
            foreach ($flat as $i => $r) {
                $add(
                    (string) ($r['category'] ?? ''),
                    $r['name'] ?? null,
                    $r['url'] ?? null,
                    (int) ($r['sort_order'] ?? $i)
                );
            }
        }

        if (is_array($flat) && !array_key_exists(0, $flat)) {
            foreach (array_keys($texts) as $cat) {
                $list = $flat[$cat] ?? null;
                if (!is_array($list)) {
                    continue;
                }
                $j = 0;
                foreach ($list as $item) {
                    if (is_array($item)) {
                        $add($cat, $item['name'] ?? null, $item['url'] ?? null, $j++);
                    } elseif (is_string($item) && $item !== '') {
                        $add($cat, $item, null, $j++);
                    }
                }
            }
        }

        foreach (array_keys($texts) as $cat) {
            $key = 'org_' . $cat;
            if (!$request->has($key)) {
                continue;
            }
            $list = $request->input($key);
            if (is_array($list) && array_key_exists(0, $list) && is_array($list[0])) {
                $k = 0;
                foreach ($list as $item) {
                    $add($cat, $item['name'] ?? null, $item['url'] ?? null, $k++);
                }
            } elseif (is_array($list) && isset($list['name']) && is_array($list['name'])) {
                $names = $list['name'];
                $urls = is_array($list['url'] ?? null) ? $list['url'] : [];
                foreach ($names as $idx => $nm) {
                    $add($cat, $nm, $urls[$idx] ?? null, $idx);
                }
            } elseif (is_array($list)) {
                foreach ($list as $idx => $nm) {
                    $add($cat, is_string($nm) ? $nm : null, null, $idx);
                }
            }
        }

        foreach ($texts as $k => $arr) {
            $texts[$k] = $arr ? implode(' / ', array_values(array_unique($arr))) : null;
        }

        return ['rows' => $rows, 'text' => $texts];
    }

    private function buildSidebarSchedule(Request $request): array
    {
        $out = [];
        $rows = (array) $request->input('schedule', []);
        $files = $request->file('schedule_files', []);
        $keeps = (array) $request->input('schedule_keep', []);

        foreach ($rows as $i => $r) {
            $date = trim((string) ($r['date'] ?? ''));
            $label = trim((string) ($r['label'] ?? ''));
            $url = trim((string) ($r['url'] ?? ''));
            $sep = !empty($r['separator']);

            $href = null;
            if (!$sep) {
                if ($url !== '') {
                    $href = preg_match('~^https?://~i', $url) ? $url : ('https://' . ltrim($url));
                } elseif (!empty($files[$i])) {
                    $href = $files[$i]->store('tournament_pdfs', 'public');
                } elseif (!empty($keeps[$i]['keep']) && !empty($keeps[$i]['href'])) {
                    $href = $keeps[$i]['href'];
                }
            }

            if (!$sep && $label === '' && $href === null) {
                continue;
            }

            $out[] = [
                'date' => $date,
                'label' => $label,
                'href' => $href,
                'separator' => $sep,
            ];
        }

        $uniq = [];
        $dedup = [];
        foreach ($out as $r) {
            $k = ($r['date'] ?? '') . '|' . ($r['label'] ?? '') . '|' . ($r['href'] ?? '') . '|' . (!empty($r['separator']) ? '1' : '0');
            if (isset($uniq[$k])) {
                continue;
            }
            $uniq[$k] = true;
            $dedup[] = $r;
        }

        return $dedup;
    }

    private function buildAwardHighlights(Request $request): array
    {
        $out = [];
        $rows = (array) $request->input('awards', []);
        $files = $request->file('award_files', []);
        $keeps = (array) $request->input('awards_keep', []);

        foreach ($rows as $i => $r) {
            $type = in_array(($r['type'] ?? ''), ['perfect', 'series800', 'split710'], true) ? $r['type'] : 'perfect';
            $player = trim((string) ($r['player'] ?? ''));
            $game = trim((string) ($r['game'] ?? ''));
            $lane = trim((string) ($r['lane'] ?? ''));
            $note = trim((string) ($r['note'] ?? ''));
            $title = trim((string) ($r['title'] ?? ''));

            $photo = null;
            if (!empty($files[$i])) {
                $photo = $files[$i]->store('tournament_awards', 'public');
            } elseif (!empty($keeps[$i]['photo'])) {
                $photo = $keeps[$i]['photo'];
            }

            if ($player === '' && $photo === null && $title === '') {
                continue;
            }

            $out[] = [
                'type' => $type,
                'player' => $player,
                'game' => $game,
                'lane' => $lane,
                'note' => $note,
                'title' => $title,
                'photo' => $photo,
            ];
        }

        return $out;
    }

    private function buildGalleryAndResults(Request $request, ?Tournament $t = null): array
    {
        $gallery = [];
        $results = [];

        if (is_array($request->input('__keep_gallery'))) {
            foreach ($request->input('__keep_gallery') as $g) {
                if (!empty($g['photo'])) {
                    $gallery[] = [
                        'photo' => $g['photo'],
                        'title' => $g['title'] ?? null,
                    ];
                }
            }
        } elseif ($t && is_array($t->gallery_items)) {
            $gallery = $t->gallery_items;
        }

        if (is_array($request->input('__keep_results'))) {
            foreach ($request->input('__keep_results') as $r) {
                if (!empty($r['file'])) {
                    $results[] = [
                        'file' => $r['file'],
                        'title' => $r['title'] ?? null,
                    ];
                }
            }
        } elseif ($t && is_array($t->simple_result_pdfs)) {
            $results = $t->simple_result_pdfs;
        }

        if ($request->hasFile('gallery_files')) {
            $titles = (array) $request->input('gallery_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('gallery_files') as $i => $f) {
                if (!$f) {
                    continue;
                }
                $path = $f->store('tournament_gallery', 'public');
                $gallery[] = [
                    'photo' => $path,
                    'title' => $titles[$i] ?? null,
                ];
            }
        }

        if ($request->hasFile('result_pdfs')) {
            $titles = (array) $request->input('result_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('result_pdfs') as $i => $f) {
                if (!$f) {
                    continue;
                }
                $path = $f->store('tournament_pdfs', 'public');
                $results[] = [
                    'file' => $path,
                    'title' => $titles[$i] ?? null,
                ];
            }
        }

        return [$gallery, $results];
    }

    private function buildResultCards(Request $request, ?Tournament $t = null): array
    {
        $rows = (array) $request->input('result_cards', []);
        $photoFiles = $request->file('result_card_photos', []);
        $pdfFiles = $request->file('result_card_files', []);
        $keeps = (array) $request->input('result_card_keep', []);

        $out = [];

        foreach ($rows as $i => $r) {
            $title = trim((string) ($r['title'] ?? ''));
            $player = trim((string) ($r['player'] ?? ''));
            $balls = trim((string) ($r['balls'] ?? ''));
            $note = trim((string) ($r['note'] ?? ''));
            $url = trim((string) ($r['url'] ?? ''));

            if ($url !== '' && !preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url);
            }

            $photos = [];
            if (!empty($keeps[$i]['photos']) && is_array($keeps[$i]['photos'])) {
                foreach ($keeps[$i]['photos'] as $p) {
                    if ($p !== null && $p !== '') {
                        $photos[] = $p;
                    }
                }
            }
            if (empty($keeps[$i]['photos']) && !empty($keeps[$i]['photo'])) {
                $photos[] = $keeps[$i]['photo'];
            }

            if (isset($photoFiles[$i])) {
                $slot = $photoFiles[$i];
                if (is_array($slot)) {
                    foreach ($slot as $pf) {
                        if (!$pf) {
                            continue;
                        }
                        $photos[] = $pf->store('tournament_results', 'public');
                    }
                } else {
                    if ($slot) {
                        $photos[] = $slot->store('tournament_results', 'public');
                    }
                }
            }

            $filePath = null;
            if (!empty($pdfFiles[$i])) {
                $filePath = $pdfFiles[$i]->store('tournament_pdfs', 'public');
            } elseif (!empty($keeps[$i]['file'])) {
                $filePath = $keeps[$i]['file'];
            }

            if ($title === '' && $player === '' && $balls === '' && $note === '' && $url === '' && !$photos && !$filePath) {
                continue;
            }

            $out[] = [
                'title' => $title,
                'player' => $player,
                'balls' => $balls,
                'note' => $note,
                'url' => $url,
                'photos' => $photos,
                'photo' => $photos[0] ?? null,
                'file' => $filePath,
            ];
        }

        return $out;
    }

    public function store(Request $request)
    {
        $validated = $this->validateAndNormalize($request);

        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }

        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        }

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        $posterPaths = [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) {
                    $posterPaths[] = $pf->store('posters', 'public');
                }
            }
        }
        if ($posterPaths) {
            $validated['poster_images'] = $posterPaths;
        }

        $validated['sidebar_schedule'] = $this->buildSidebarSchedule($request);
        $validated['award_highlights'] = $this->buildAwardHighlights($request);

        [$gallery, $results] = $this->buildGalleryAndResults($request, null);
        if ($gallery) {
            $validated['gallery_items'] = $gallery;
        }
        if ($results) {
            $validated['simple_result_pdfs'] = $results;
        }

        $cards = $this->buildResultCards($request, null);
        if ($cards) {
            $validated['result_cards'] = $cards;
        }

        $t = new Tournament();
        $t->forceFill($validated);
        $t->save();

        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) {
            $t->organizations()->saveMany($org['rows']);
        }
        $t->fill([
            'host' => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor' => $org['text']['sponsor'],
            'support' => $org['text']['support'],
        ])->save();

        $filesToStore = [
            'outline_public' => ['type' => 'outline_public', 'visibility' => 'public', 'title' => '大会要項（一般）'],
            'outline_player' => ['type' => 'outline_player', 'visibility' => 'members', 'title' => '大会要項（選手）'],
            'oil_pattern' => ['type' => 'oil_pattern', 'visibility' => 'public', 'title' => 'オイルパターン表'],
        ];

        foreach ($filesToStore as $inputName => $meta) {
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type' => $meta['type'],
                    'title' => $meta['title'],
                    'file_path' => $path,
                    'visibility' => $meta['visibility'],
                    'sort_order' => 0,
                ]);
            }
        }

        if ($request->hasFile('custom_files')) {
            $titles = (array) $request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) {
                    continue;
                }
                $title = $titles[$i] ?? '資料' . ($i + 1);
                $path = $file->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type' => 'custom',
                    'title' => $title,
                    'file_path' => $path,
                    'visibility' => 'public',
                    'sort_order' => $i,
                ]);
                $i++;
            }
        }

        return redirect()->route('tournaments.show', $t->id)
            ->with('success', '大会が登録されました');
    }

    public function update(Request $request, $id)
    {
        $t = Tournament::with(['organizations', 'files'])->findOrFail($id);
        $validated = $this->validateAndNormalize($request);

        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }

        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        } else {
            $validated['title_logo_path'] = $t->title_logo_path;
        }

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        $posterPaths = is_array($t->poster_images) ? $t->poster_images : [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) {
                    $posterPaths[] = $pf->store('posters', 'public');
                }
            }
        }
        $validated['poster_images'] = $posterPaths;

        $validated['sidebar_schedule'] = $request->has('schedule')
            ? $this->buildSidebarSchedule($request)
            : $t->sidebar_schedule;

        $validated['award_highlights'] = $request->has('awards')
            ? $this->buildAwardHighlights($request)
            : $t->award_highlights;

        [$gallery, $results] = $this->buildGalleryAndResults($request, $t);
        $validated['gallery_items'] = $gallery;
        $validated['simple_result_pdfs'] = $results;

        $validated['result_cards'] = $request->has('result_cards')
            ? $this->buildResultCards($request, $t)
            : $t->result_cards;

        $t->forceFill($validated)->save();

        $t->organizations()->delete();
        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) {
            $t->organizations()->saveMany($org['rows']);
        }
        $t->fill([
            'host' => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor' => $org['text']['sponsor'],
            'support' => $org['text']['support'],
        ])->save();

        $filesToStore = [
            'outline_public' => ['type' => 'outline_public', 'visibility' => 'public', 'title' => '大会要項（一般）'],
            'outline_player' => ['type' => 'outline_player', 'visibility' => 'members', 'title' => '大会要項（選手）'],
            'oil_pattern' => ['type' => 'oil_pattern', 'visibility' => 'public', 'title' => 'オイルパターン表'],
        ];

        foreach ($filesToStore as $inputName => $meta) {
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('tournament_pdfs', 'public');
                $t->files()->updateOrCreate(
                    ['type' => $meta['type']],
                    [
                        'title' => $meta['title'],
                        'file_path' => $path,
                        'visibility' => $meta['visibility'],
                        'sort_order' => 0,
                    ]
                );
            }
        }

        if ($request->hasFile('custom_files')) {
            $titles = (array) $request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) {
                    continue;
                }
                $title = $titles[$i] ?? '資料' . ($i + 1);
                $path = $file->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type' => 'custom',
                    'title' => $title,
                    'file_path' => $path,
                    'visibility' => 'public',
                    'sort_order' => $i,
                ]);
                $i++;
            }
        }

        return redirect()->route('tournaments.show', $t->id)
            ->with('success', '大会情報を更新しました。');
    }

    public function destroy($id)
    {
        $t = Tournament::with(['organizations', 'files', 'prizeDistributions', 'pointDistributions', 'entries'])
            ->findOrFail($id);

        DB::transaction(function () use ($t) {
            $paths = [];

            if ($t->image_path) {
                $paths[] = $t->image_path;
            }
            if ($t->hero_image_path) {
                $paths[] = $t->hero_image_path;
            }
            if ($t->title_logo_path) {
                $paths[] = $t->title_logo_path;
            }
            if (is_array($t->poster_images)) {
                foreach ($t->poster_images as $p) {
                    $paths[] = $p;
                }
            }
            if (is_array($t->gallery_items)) {
                foreach ($t->gallery_items as $gi) {
                    if (!empty($gi['photo'])) {
                        $paths[] = $gi['photo'];
                    }
                }
            }
            if (is_array($t->simple_result_pdfs)) {
                foreach ($t->simple_result_pdfs as $ri) {
                    if (!empty($ri['file'])) {
                        $paths[] = $ri['file'];
                    }
                }
            }
            foreach ($t->files as $f) {
                if ($f->file_path) {
                    $paths[] = $f->file_path;
                }
            }

            $paths = array_values(array_unique($paths));
            foreach ($paths as $p) {
                try {
                    Storage::disk('public')->delete($p);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $t->organizations()->delete();
            $t->files()->delete();
            if (method_exists($t, 'prizeDistributions')) {
                $t->prizeDistributions()->delete();
            }
            if (method_exists($t, 'pointDistributions')) {
                $t->pointDistributions()->delete();
            }
            if (method_exists($t, 'entries')) {
                $t->entries()->delete();
            }

            $t->delete();
        });

        return redirect()->route('tournaments.index')->with('success', '大会を削除しました。');
    }
}