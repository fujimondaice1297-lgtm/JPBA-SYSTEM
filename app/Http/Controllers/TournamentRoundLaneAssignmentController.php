<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentRoundLaneAssignmentController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $stage = trim((string) $request->input('stage', '準決勝'));
        $roundLabel = trim((string) $request->input('round_label', '準決勝4G'));

        $snapshots = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->orderByDesc('id')
            ->get();

        $assignments = $this->loadAssignments($tournament, $stage, $roundLabel);

        $latestPrelimTotal = $snapshots
            ->where('result_code', 'prelim_total')
            ->where('is_current', true)
            ->sortByDesc('id')
            ->first();

        return view('tournament_round_lane_assignments.index', compact(
            'tournament',
            'stage',
            'roundLabel',
            'snapshots',
            'assignments',
            'latestPrelimTotal'
        ));
    }

    public function generateFromSnapshot(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'source_result_snapshot_id' => ['required', 'integer'],
            'stage' => ['required', 'string', 'max:64'],
            'round_label' => ['nullable', 'string', 'max:128'],
            'limit' => ['required', 'integer', 'min:1', 'max:200'],
            'game_from' => ['nullable', 'integer', 'min:1', 'max:999'],
            'game_to' => ['nullable', 'integer', 'min:1', 'max:999'],
            'movement_direction' => ['nullable', 'string', 'max:16'],
            'movement_box_step' => ['nullable', 'integer', 'min:1', 'max:20'],
            'game_start_time' => ['nullable', 'date_format:H:i'],
            'game_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'tv_lane_from' => ['nullable', 'integer', 'min:1', 'max:999'],
            'tv_lane_to' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $snapshot = DB::table('tournament_result_snapshots')
            ->where('id', (int) $data['source_result_snapshot_id'])
            ->where('tournament_id', $tournament->id)
            ->first();

        if (!$snapshot) {
            return back()->withErrors(['source_result_snapshot_id' => '指定された成績スナップショットが見つかりません。'])->withInput();
        }

        $stage = trim((string) $data['stage']);
        $roundLabel = trim((string) ($data['round_label'] ?? ''));
        $roundLabel = $roundLabel !== '' ? $roundLabel : $stage;

        $rows = DB::table('tournament_result_snapshot_rows as sr')
            ->leftJoin('pro_bowlers as pb', 'pb.id', '=', 'sr.pro_bowler_id')
            ->where('sr.snapshot_id', $snapshot->id)
            ->orderBy('sr.ranking')
            ->limit((int) $data['limit'])
            ->select([
                'sr.ranking',
                'sr.pro_bowler_id',
                'sr.pro_bowler_license_no',
                'sr.display_name',
                'sr.total_pin',
                'sr.games',
                'sr.average',
                'pb.id as pb_id',
                'pb.license_no as pb_license_no',
                'pb.kibetsu as pb_kibetsu',
                'pb.dominant_arm as pb_dominant_arm',
                'pb.organization_name as pb_organization_name',
                'pb.equipment_contract as pb_equipment_contract',
            ])
            ->get();

        $now = now();

        DB::transaction(function () use ($rows, $tournament, $snapshot, $stage, $roundLabel, $data, $now) {
            foreach ($rows as $row) {
                $licenseNo = trim((string) ($row->pb_license_no ?: $row->pro_bowler_license_no));
                $participant = $this->resolveParticipant($tournament->id, $row->pro_bowler_id, $licenseNo, (string) $row->display_name);

                $affiliation = $this->combineAffiliation(
                    $row->pb_organization_name ?? null,
                    $row->pb_equipment_contract ?? null
                );

                $payload = [
                    'source_result_snapshot_id' => $snapshot->id,
                    'pro_bowler_id' => $row->pro_bowler_id ?: null,
                    'pro_bowler_license_no' => $licenseNo !== '' ? $licenseNo : null,
                    'display_license_no' => $this->formatLicenseForLanePdf($licenseNo),
                    'display_name' => (string) $row->display_name,
                    'period_label' => $row->pb_kibetsu !== null ? (string) $row->pb_kibetsu : null,
                    'dominant_arm' => $this->normalizeArmLabel($row->pb_dominant_arm ?? null),
                    'affiliation_display' => $affiliation,
                    'source_total_pin' => $row->total_pin !== null ? (int) $row->total_pin : null,
                    'source_games' => $row->games !== null ? (int) $row->games : null,
                    'source_average' => $row->average !== null ? round((float) $row->average, 3) : null,
                    'game_from' => $data['game_from'] !== null ? (int) $data['game_from'] : null,
                    'game_to' => $data['game_to'] !== null ? (int) $data['game_to'] : null,
                    'movement_direction' => (string) ($data['movement_direction'] ?? 'left'),
                    'movement_box_step' => (int) ($data['movement_box_step'] ?? 1),
                    'game_start_time' => $data['game_start_time'] ?? null,
                    'game_interval_minutes' => $data['game_interval_minutes'] !== null ? (int) $data['game_interval_minutes'] : null,
                    'tv_lane_from' => $data['tv_lane_from'] !== null ? (int) $data['tv_lane_from'] : null,
                    'tv_lane_to' => $data['tv_lane_to'] !== null ? (int) $data['tv_lane_to'] : null,
                    'sort_order' => (int) $row->ranking,
                    'updated_at' => $now,
                ];

                if ($participant) {
                    $payload['tournament_participant_id'] = $participant->id;
                }

                $existing = DB::table('tournament_round_lane_assignments')
                    ->where('tournament_id', $tournament->id)
                    ->where('stage', $stage)
                    ->where('round_label', $roundLabel)
                    ->where('tournament_participant_id', $participant?->id)
                    ->first();

                if ($existing) {
                    DB::table('tournament_round_lane_assignments')
                        ->where('id', $existing->id)
                        ->update($payload);
                } else {
                    $payload['tournament_id'] = $tournament->id;
                    $payload['stage'] = $stage;
                    $payload['round_label'] = $roundLabel;
                    $payload['seed_rank'] = (int) $row->ranking;
                    $payload['created_at'] = $now;

                    DB::table('tournament_round_lane_assignments')->insert($payload);
                }
            }
        });

        return redirect()
            ->route('tournaments.round_lane_assignments.index', [
                'tournament' => $tournament->id,
                'stage' => $stage,
                'round_label' => $roundLabel,
            ])
            ->with('success', '成績スナップショットからラウンド別レーン割当の初期行を作成しました。スタートレーンは必要に応じて編集してください。');
    }

    public function bulkUpdate(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'max:64'],
            'round_label' => ['nullable', 'string', 'max:128'],
            'assignments' => ['required', 'array'],
            'assignments.*.id' => ['required', 'integer'],
            'assignments.*.display_license_no' => ['nullable', 'string', 'max:32'],
            'assignments.*.display_name' => ['required', 'string', 'max:255'],
            'assignments.*.period_label' => ['nullable', 'string', 'max:32'],
            'assignments.*.dominant_arm' => ['nullable', 'string', 'max:32'],
            'assignments.*.affiliation_display' => ['nullable', 'string', 'max:2000'],
            'assignments.*.source_total_pin' => ['nullable', 'integer', 'min:0'],
            'assignments.*.source_average' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'assignments.*.start_lane' => ['nullable', 'integer', 'min:1', 'max:999'],
            'assignments.*.lane_slot' => ['nullable', 'integer', 'min:1', 'max:20'],
            'assignments.*.start_lane_label' => ['nullable', 'string', 'max:32'],
            'assignments.*.box_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'assignments.*.movement_boxes_text' => ['nullable', 'string', 'max:1000'],
            'assignments.*.game_start_time' => ['nullable', 'date_format:H:i'],
            'assignments.*.game_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'assignments.*.tv_lane_from' => ['nullable', 'integer', 'min:1', 'max:999'],
            'assignments.*.tv_lane_to' => ['nullable', 'integer', 'min:1', 'max:999'],
            'assignments.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'assignments.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        $stage = trim((string) $data['stage']);
        $roundLabel = trim((string) ($data['round_label'] ?? ''));
        $roundLabel = $roundLabel !== '' ? $roundLabel : $stage;

        DB::transaction(function () use ($data, $tournament, $stage, $roundLabel) {
            foreach ($data['assignments'] as $row) {
                $assignment = DB::table('tournament_round_lane_assignments')
                    ->where('id', (int) $row['id'])
                    ->where('tournament_id', $tournament->id)
                    ->first();

                if (!$assignment) {
                    continue;
                }

                $startLane = $this->nullableInt($row['start_lane'] ?? null);
                $laneSlot = $this->nullableInt($row['lane_slot'] ?? null);
                $startLaneLabel = trim((string) ($row['start_lane_label'] ?? ''));

                if ($startLaneLabel === '' && $startLane && $laneSlot) {
                    $startLaneLabel = $startLane . 'L-' . $laneSlot;
                }

                DB::table('tournament_round_lane_assignments')
                    ->where('id', (int) $row['id'])
                    ->update([
                        'stage' => $stage,
                        'round_label' => $roundLabel,
                        'display_license_no' => $this->nullableString($row['display_license_no'] ?? null),
                        'display_name' => trim((string) $row['display_name']),
                        'period_label' => $this->nullableString($row['period_label'] ?? null),
                        'dominant_arm' => $this->nullableString($row['dominant_arm'] ?? null),
                        'affiliation_display' => $this->nullableString($row['affiliation_display'] ?? null),
                        'source_total_pin' => $this->nullableInt($row['source_total_pin'] ?? null),
                        'source_average' => $this->nullableFloat($row['source_average'] ?? null),
                        'start_lane' => $startLane,
                        'lane_slot' => $laneSlot,
                        'start_lane_label' => $startLaneLabel !== '' ? $startLaneLabel : null,
                        'box_no' => $this->nullableInt($row['box_no'] ?? null),
                        'movement_boxes' => $this->parseMovementBoxes($row['movement_boxes_text'] ?? null),
                        'game_start_time' => $row['game_start_time'] ?? null,
                        'game_interval_minutes' => $this->nullableInt($row['game_interval_minutes'] ?? null),
                        'tv_lane_from' => $this->nullableInt($row['tv_lane_from'] ?? null),
                        'tv_lane_to' => $this->nullableInt($row['tv_lane_to'] ?? null),
                        'sort_order' => $this->nullableInt($row['sort_order'] ?? null),
                        'note' => $this->nullableString($row['note'] ?? null),
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('tournaments.round_lane_assignments.index', [
                'tournament' => $tournament->id,
                'stage' => $stage,
                'round_label' => $roundLabel,
            ])
            ->with('success', 'ラウンド別レーン割当を保存しました。');
    }

    public function destroy(Tournament $tournament, int $assignment)
    {
        DB::table('tournament_round_lane_assignments')
            ->where('id', $assignment)
            ->where('tournament_id', $tournament->id)
            ->delete();

        return back()->with('success', 'ラウンド別レーン割当を削除しました。');
    }

    public function pdf(Request $request, Tournament $tournament)
    {
        $stage = trim((string) $request->input('stage', '準決勝'));
        $roundLabel = trim((string) $request->input('round_label', '準決勝4G'));

        $assignments = $this->loadAssignments($tournament, $stage, $roundLabel);

        if ($assignments->isEmpty()) {
            return redirect()
                ->route('tournaments.round_lane_assignments.index', [
                    'tournament' => $tournament->id,
                    'stage' => $stage,
                    'round_label' => $roundLabel,
                ])
                ->withErrors(['assignments' => 'PDF出力対象のレーン割当がありません。']);
        }

        $first = $assignments->first();

        $gameFrom = (int) ($first->game_from ?: 1);
        $gameTo = (int) ($first->game_to ?: $gameFrom);
        $gameNumbers = range($gameFrom, max($gameFrom, $gameTo));
        $gameHeaders = $this->buildGameHeaders($first, $gameNumbers);

        $tvLaneLabel = null;
        if ($first->tv_lane_from && $first->tv_lane_to) {
            $tvLaneLabel = $first->tv_lane_from . '・' . $first->tv_lane_to . 'L';
        }

        $pdf = Pdf::loadView('tournament_round_lane_assignments.pdf', [
            'tournament' => $tournament,
            'stage' => $stage,
            'roundLabel' => $roundLabel,
            'assignments' => $assignments,
            'gameNumbers' => $gameNumbers,
            'gameHeaders' => $gameHeaders,
            'tvLaneLabel' => $tvLaneLabel,
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('defaultFont', 'ipaexg');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', base_path());

        $fontPath = storage_path('fonts/ipaexg.ttf');
        if (is_file($fontPath)) {
            $dompdf->getFontMetrics()->registerFont(
                [
                    'family' => 'ipaexg',
                    'style' => 'normal',
                    'weight' => 'normal',
                ],
                $fontPath
            );
        }

        $safeTournamentName = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', (string) $tournament->name);
        $safeStage = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', $roundLabel);

        return $pdf->download(sprintf(
            '%s_%s_%s_lane_movement.pdf',
            $tournament->year ?: now()->format('Y'),
            $safeTournamentName !== '' ? $safeTournamentName : 'tournament',
            $safeStage !== '' ? $safeStage : 'round'
        ));
    }

    private function loadAssignments(Tournament $tournament, string $stage, string $roundLabel)
    {
        $query = DB::table('tournament_round_lane_assignments')
            ->where('tournament_id', $tournament->id);

        if ($stage !== '') {
            $query->where('stage', $stage);
        }

        if ($roundLabel !== '') {
            $query->where('round_label', $roundLabel);
        }

        return $query
            ->orderByRaw('COALESCE(sort_order, seed_rank, 999999)')
            ->orderBy('start_lane')
            ->orderBy('lane_slot')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $boxes = $this->decodeMovementBoxes($row->movement_boxes ?? null);
                $row->movement_boxes_array = $boxes;
                $row->movement_boxes_text = implode(', ', $boxes);
                return $row;
            });
    }

    private function resolveParticipant(int $tournamentId, ?int $proBowlerId, string $licenseNo, string $displayName)
    {
        $query = DB::table('tournament_participants')
            ->where('tournament_id', $tournamentId);

        if ($proBowlerId) {
            $participant = (clone $query)->where('pro_bowler_id', $proBowlerId)->first();
            if ($participant) {
                return $participant;
            }
        }

        if ($licenseNo !== '') {
            $participant = (clone $query)->where('pro_bowler_license_no', $licenseNo)->first();
            if ($participant) {
                return $participant;
            }
        }

        if ($displayName !== '') {
            $normalized = $this->normalizeName($displayName);

            return (clone $query)
                ->whereRaw("replace(replace(replace(replace(display_name, '　', ''), ' ', ''), '･', ''), '・', '') = ?", [$normalized])
                ->first();
        }

        return null;
    }

    private function buildGameHeaders(object $first, array $gameNumbers): array
    {
        $labels = [];

        $start = $first->game_start_time ? substr((string) $first->game_start_time, 0, 5) : null;
        $interval = $first->game_interval_minutes ? (int) $first->game_interval_minutes : null;

        $baseTimestamp = null;
        if ($start && preg_match('/^\d{2}:\d{2}$/', $start)) {
            [$h, $m] = array_map('intval', explode(':', $start));
            $baseTimestamp = strtotime(sprintf('2000-01-01 %02d:%02d:00', $h, $m));
        }

        foreach ($gameNumbers as $idx => $gameNo) {
            $timeLabel = '';
            if ($baseTimestamp !== null && $interval !== null) {
                $timeLabel = date('G:i', $baseTimestamp + ($idx * $interval * 60));
                if ($idx > 0) {
                    $timeLabel .= '頃';
                }
            }

            $labels[] = [
                'game_no' => (int) $gameNo,
                'round_game_no' => $idx + 1,
                'time' => $timeLabel,
            ];
        }

        return $labels;
    }

    private function parseMovementBoxes(?string $text): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*|\r\n|\r|\n/u', $text) ?: [];
        $parts = array_values(array_filter(array_map(function ($item) {
            $item = trim((string) $item);
            $item = str_replace(['・', '･'], '･', $item);
            return $item;
        }, $parts), fn ($item) => $item !== ''));

        return json_encode($parts, JSON_UNESCAPED_UNICODE);
    }

    private function decodeMovementBoxes($value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        return [];
    }

    private function formatLicenseForLanePdf(?string $license): ?string
    {
        $license = trim((string) $license);
        if ($license === '') {
            return null;
        }

        if ($license === 'アマ') {
            return 'アマ';
        }

        $digits = preg_replace('/\D+/', '', $license);
        if ($digits === '') {
            return $license;
        }

        $last = substr($digits, -4);
        $last = ltrim($last, '0');

        return $last !== '' ? $last : '0';
    }

    private function combineAffiliation(?string $organization, ?string $equipment): ?string
    {
        $organization = trim((string) $organization);
        $equipment = trim((string) $equipment);

        if ($organization !== '' && $equipment !== '') {
            return $organization . '/' . $equipment;
        }

        return $organization !== '' ? $organization : ($equipment !== '' ? $equipment : null);
    }

    private function normalizeArmLabel($arm): ?string
    {
        $arm = trim((string) $arm);

        if ($arm === '') {
            return null;
        }

        return match ($arm) {
            'R', '右投げ' => '右',
            'L', '左投げ' => '左',
            default => $arm,
        };
    }

    private function normalizeName(string $name): string
    {
        return str_replace(['　', ' ', '･', '・'], '', trim($name));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) $value, 3);
    }
}
