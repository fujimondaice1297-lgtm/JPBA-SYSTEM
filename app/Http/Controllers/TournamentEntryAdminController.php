<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Services\ProBowlerSeedService;
use App\Services\TournamentLaneMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TournamentEntryAdminController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $status = (string) $request->input('status', 'active');
        $keyword = trim((string) $request->input('q', ''));

        $priorityLookup = $this->buildTournamentPriorityLookup($tournament);

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id);

        if ($status === 'active') {
            $query->whereIn('status', ['entry', 'waiting']);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $this->applyBowlerKeyword($query, $keyword);

        $entriesCollection = $this->decorateEntriesWithParticipantDisplay(
            $this->decorateEntriesWithPriority(
                $query->get(),
                $priorityLookup
            ),
            $tournament
        );

        $entries = $this->paginateCollection(
            $this->sortAdminIndexEntries($entriesCollection),
            $request
        );

        $summary = $this->buildSummary($tournament, $priorityLookup);
        $amateurBowlers = $this->loadAmateurBowlersForSelect();
        $amateurParticipants = $this->loadTournamentAmateurParticipants($tournament);
        $entryOperationLogs = $this->recentEntryOperationLogs($tournament);

        return view('tournament_entries.admin_index', compact(
            'tournament',
            'entries',
            'summary',
            'status',
            'keyword',
            'amateurBowlers',
            'amateurParticipants',
            'entryOperationLogs'
        ));
    }

    public function draws(Request $request, Tournament $tournament)
    {
        $keyword = trim((string) $request->input('q', ''));
        $pendingDraw = $request->boolean('pending_draw');

        $priorityLookup = $this->buildTournamentPriorityLookup($tournament);

        $query = TournamentEntry::query()
            ->with('bowler')
            ->withCount('balls')
            ->where('tournament_id', $tournament->id)
            ->where('status', 'entry');

        if ($pendingDraw) {
            $query->where(function (Builder $q) use ($tournament) {
                if ((bool) $tournament->use_shift_draw) {
                    $q->whereNull('shift');
                }

                if ((bool) $tournament->use_lane_draw) {
                    if ((bool) $tournament->use_shift_draw) {
                        $q->orWhereNull('lane');
                    } else {
                        $q->whereNull('lane');
                    }
                }
            });
        }

        $this->applyBowlerKeyword($query, $keyword);

        $entriesCollection = $this->decorateEntriesWithParticipantDisplay(
            $this->decorateEntriesWithPriority(
                $query->get(),
                $priorityLookup
            ),
            $tournament
        );

        $entries = $this->paginateCollection(
            $this->sortAdminDrawEntries($entriesCollection),
            $request
        );

        $summary = $this->buildSummary($tournament, $priorityLookup);
        $entryOperationLogs = $this->recentEntryOperationLogs($tournament);

        return view('tournament_entries.admin_draws', compact(
            'tournament',
            'entries',
            'summary',
            'keyword',
            'pendingDraw',
            'entryOperationLogs'
        ));
    }


    public function laneMovementTable(Request $request, Tournament $tournament, TournamentLaneMovementService $laneMovementService)
    {
        $participants = DB::table('tournament_participants as tp')
            ->leftJoin('pro_bowlers as pb', 'pb.id', '=', 'tp.pro_bowler_id')
            ->where('tp.tournament_id', $tournament->id)
            ->whereNotNull('tp.lane')
            ->select([
                'tp.id',
                'tp.tournament_id',
                'tp.pro_bowler_id',
                'tp.pro_bowler_license_no',
                'tp.participant_type',
                'tp.display_name',
                'tp.display_license_no',
                'tp.gender',
                'tp.shift',
                'tp.lane',
                'tp.lane_slot',
                'tp.lane_label',
                'tp.box_no',
                'tp.sort_order',
                'tp.source_note',
                'tp.is_temporary',
                'pb.license_no as bowler_license_no',
                'pb.name_kanji as bowler_name_kanji',
                'pb.kibetsu as bowler_kibetsu',
            ])
            ->orderByRaw('COALESCE(tp.sort_order, (tp.lane * 10 + COALESCE(tp.lane_slot, 0)))')
            ->orderBy('tp.lane')
            ->orderBy('tp.lane_slot')
            ->orderBy('tp.id')
            ->get()
            ->map(function ($row) {
                $row->status = 'entry';

                $row->bowler = $row->pro_bowler_id ? (object) [
                    'id' => $row->pro_bowler_id,
                    'license_no' => $row->bowler_license_no ?: $row->pro_bowler_license_no,
                    'name_kanji' => $row->bowler_name_kanji ?: $row->display_name,
                    'kibetsu' => $row->bowler_kibetsu,
                ] : null;

                return $row;
            });

        if ($participants->isNotEmpty()) {
            $entries = $participants;
        } else {
            $entries = TournamentEntry::query()
                ->with('bowler')
                ->where('tournament_id', $tournament->id)
                ->where('status', 'entry')
                ->whereNotNull('lane')
                ->orderBy('lane')
                ->orderBy('id')
                ->get();
        }

        $laneMovement = $laneMovementService->buildRows($tournament, $entries);

        return view('tournament_entries.lane_movement_table', compact(
            'tournament',
            'entries',
            'laneMovement'
        ));
    }

    public function storeWaitlist(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'license_no' => ['required', 'string', 'max:255'],
            'waitlist_priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'waitlist_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $licenseNo = trim((string) $data['license_no']);

        $resolved = $this->resolveBowlerByLicenseInput($licenseNo, $tournament);
        if (!$resolved['bowler']) {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->withErrors(['license_no' => $resolved['message']])
                ->withInput();
        }

        /** @var ProBowler $bowler */
        $bowler = $resolved['bowler'];

        $existing = TournamentEntry::query()
            ->where('tournament_id', $tournament->id)
            ->where('pro_bowler_id', $bowler->id)
            ->first();

        if ($existing && $existing->status === 'entry') {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->withErrors(['license_no' => 'この選手はすでに参加登録済みです。'])
                ->withInput();
        }

        $priorityLookup = $this->buildTournamentPriorityLookup($tournament);
        $priorityInfo = $this->findPriorityForBowler($bowler, $priorityLookup);
        $waitlistPriority = $data['waitlist_priority'] ?? null;

        if (is_null($waitlistPriority) && $priorityInfo && (int) $priorityInfo['priority_sort'] < 999999) {
            $waitlistPriority = (int) $priorityInfo['priority_sort'];
        }

        $entry = TournamentEntry::query()->updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'pro_bowler_id' => $bowler->id,
            ],
            [
                'status' => 'waiting',
                'waitlist_priority' => $waitlistPriority,
                'waitlisted_at' => $existing?->waitlisted_at ?? now(),
                'waitlist_note' => $data['waitlist_note'] ?: null,
                'promoted_from_waitlist_at' => null,
                'shift' => null,
                'lane' => null,
                'checked_in_at' => null,
                'shift_drawn' => false,
                'lane_drawn' => false,
            ]
        );
        $entry->loadMissing('bowler');

        $this->recordEntryOperation(
            $entry,
            'waitlist_registered',
            $data['waitlist_note'] ?: null,
            [
                'waitlist_priority' => $waitlistPriority,
                'priority_auto_filled' => $priorityInfo && is_null($data['waitlist_priority'] ?? null),
            ],
            $existing?->status,
            'waiting'
        );

        $message = 'ウェイティング登録を保存しました。 [' . $entry->bowler?->license_no . ' ' . ($entry->bowler?->name_kanji ?? '') . ']';

        if ($priorityInfo && is_null($data['waitlist_priority'] ?? null)) {
            $message .= ' 優先出場設定に基づき、優先順を自動補完しました。';
        }

        return redirect()
            ->route('tournaments.entries.index', $tournament->id)
            ->with('success', $message);
    }

    public function storeAmateurParticipant(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'amateur_bowler_id' => ['nullable', 'integer', 'min:1'],
            'amateur_no' => ['nullable', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:M,F,X'],
            'dominant_arm' => ['nullable', 'string', 'max:20'],
            'affiliation_name' => ['nullable', 'string', 'max:255'],
            'equipment_contract' => ['nullable', 'string', 'max:255'],
            'lane' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lane_slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'box_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'master_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $amateurBowlerId = !empty($data['amateur_bowler_id']) ? (int) $data['amateur_bowler_id'] : null;
        $amateurNo = $this->normalizeAmateurNo((string) ($data['amateur_no'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $gender = $this->normalizeParticipantGender((string) ($data['gender'] ?? ($tournament->gender ?? '')));
        $dominantArm = $this->normalizeDominantArm((string) ($data['dominant_arm'] ?? ''));
        $affiliationName = trim((string) ($data['affiliation_name'] ?? ''));
        $equipmentContract = trim((string) ($data['equipment_contract'] ?? ''));

        $amateurBowler = null;

        if ($amateurBowlerId) {
            $amateurBowler = DB::table('amateur_bowlers')->where('id', $amateurBowlerId)->first();
            if (!$amateurBowler) {
                return redirect()
                    ->route('tournaments.entries.index', $tournament->id)
                    ->withErrors(['amateur_bowler_id' => '選択されたアマチュア選手が見つかりません。'])
                    ->withInput();
            }
        }

        if (!$amateurBowler && $name === '') {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->withErrors(['name' => '新規登録する場合はアマチュア選手名を入力してください。'])
                ->withInput();
        }

        $now = now();

        $hasAmateurNoColumn = Schema::hasColumn('amateur_bowlers', 'amateur_no');

        if ($amateurBowler) {
            $name = $name !== '' ? $name : trim((string) $amateurBowler->name);
            $gender = $gender !== '' ? $gender : (trim((string) ($amateurBowler->gender ?? '')) ?: $this->normalizeParticipantGender((string) ($tournament->gender ?? '')));
            $dominantArm = $dominantArm !== '' ? $dominantArm : trim((string) ($amateurBowler->dominant_arm ?? ''));
            $affiliationName = $affiliationName !== '' ? $affiliationName : trim((string) ($amateurBowler->affiliation_name ?? ''));
            $equipmentContract = $equipmentContract !== '' ? $equipmentContract : trim((string) ($amateurBowler->equipment_contract ?? ''));

            if ($hasAmateurNoColumn) {
                $amateurNo = $amateurNo !== ''
                    ? $amateurNo
                    : $this->normalizeAmateurNo((string) ($amateurBowler->amateur_no ?? ''));

                if ($amateurNo === '') {
                    $amateurNo = $this->nextAmateurNo();
                }

                if ($this->amateurNoExists($amateurNo, (int) $amateurBowler->id)) {
                    return redirect()
                        ->route('tournaments.entries.index', $tournament->id)
                        ->withErrors(['amateur_no' => 'このアマチュア識別番号はすでに使用されています。'])
                        ->withInput();
                }
            }

            $masterUpdate = [
                'name' => $name,
                'name_kana' => trim((string) ($data['name_kana'] ?? '')) ?: ($amateurBowler->name_kana ?? null),
                'gender' => $gender ?: ($amateurBowler->gender ?? null),
                'dominant_arm' => $dominantArm ?: null,
                'affiliation_name' => $affiliationName ?: null,
                'equipment_contract' => $equipmentContract ?: null,
                'note' => trim((string) ($data['master_note'] ?? '')) ?: ($amateurBowler->note ?? null),
                'updated_at' => $now,
            ];

            if ($hasAmateurNoColumn) {
                $masterUpdate['amateur_no'] = $amateurNo;
            }

            DB::table('amateur_bowlers')
                ->where('id', $amateurBowler->id)
                ->update(array_filter($masterUpdate, fn ($value) => !is_null($value)));

            $amateurBowlerId = (int) $amateurBowler->id;
        } else {
            $insertPayload = [
                'name' => $name,
                'name_kana' => trim((string) ($data['name_kana'] ?? '')) ?: null,
                'gender' => $gender ?: $this->normalizeParticipantGender((string) ($tournament->gender ?? '')) ?: null,
                'dominant_arm' => $dominantArm ?: null,
                'affiliation_name' => $affiliationName ?: null,
                'equipment_contract' => $equipmentContract ?: null,
                'note' => trim((string) ($data['master_note'] ?? '')) ?: null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasAmateurNoColumn) {
                $amateurNo = $amateurNo !== '' ? $amateurNo : $this->nextAmateurNo();

                if ($this->amateurNoExists($amateurNo)) {
                    return redirect()
                        ->route('tournaments.entries.index', $tournament->id)
                        ->withErrors(['amateur_no' => 'このアマチュア識別番号はすでに使用されています。'])
                        ->withInput();
                }

                $insertPayload['amateur_no'] = $amateurNo;
            }

            $amateurBowlerId = (int) DB::table('amateur_bowlers')->insertGetId($insertPayload);
        }

        $lane = isset($data['lane']) && $data['lane'] !== null && $data['lane'] !== '' ? (int) $data['lane'] : null;
        $laneSlot = isset($data['lane_slot']) && $data['lane_slot'] !== null && $data['lane_slot'] !== '' ? (int) $data['lane_slot'] : null;
        $boxNo = isset($data['box_no']) && $data['box_no'] !== null && $data['box_no'] !== '' ? (int) $data['box_no'] : null;
        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;

        if (is_null($boxNo) && !is_null($lane)) {
            $boxNo = max(1, intdiv(max(1, $lane) - 1, 2));
        }

        if (is_null($sortOrder) && !is_null($lane)) {
            $sortOrder = ($lane * 10) + ($laneSlot ?? 0);
        }

        $participant = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->where('participant_type', 'amateur')
            ->where('amateur_bowler_id', $amateurBowlerId)
            ->first();

        $amateurCode = $participant?->pro_bowler_license_no ?: $this->nextTournamentAmateurCode($tournament);

        $payload = [
            'tournament_id' => $tournament->id,
            'pro_bowler_license_no' => $amateurCode,
            'pro_bowler_id' => null,
            'amateur_bowler_id' => $amateurBowlerId,
            'participant_type' => 'amateur',
            'display_name' => $name,
            'display_license_no' => 'アマ',
            'display_dominant_arm' => $dominantArm ?: null,
            'display_affiliation_name' => $affiliationName ?: null,
            'display_equipment_contract' => $equipmentContract ?: null,
            'gender' => $gender ?: $this->normalizeParticipantGender((string) ($tournament->gender ?? '')) ?: null,
            'shift' => '予選',
            'lane' => $lane,
            'lane_slot' => $laneSlot,
            'lane_label' => !is_null($lane) && !is_null($laneSlot) ? sprintf('%dL-%d', $lane, $laneSlot) : null,
            'box_no' => $boxNo,
            'sort_order' => $sortOrder,
            'source_note' => trim((string) ($data['source_note'] ?? '')) ?: null,
            'is_temporary' => true,
            'updated_at' => $now,
        ];

        if ($participant) {
            DB::table('tournament_participants')->where('id', $participant->id)->update($payload);
            $message = 'アマチュア参加者を更新しました。';
        } else {
            $payload['created_at'] = $now;
            DB::table('tournament_participants')->insert($payload);
            $message = 'アマチュア参加者を登録しました。';
        }

        return redirect()
            ->route('tournaments.entries.index', $tournament->id)
            ->with('success', $message . ' [' . $amateurCode . ' ' . $name . ']');
    }


    public function updateAmateurParticipant(Request $request, $participant)
    {
        $participantId = (int) $participant;
        $row = DB::table('tournament_participants')
            ->where('id', $participantId)
            ->where('participant_type', 'amateur')
            ->first();

        if (!$row) {
            return redirect()->back()->with('error', '更新対象のアマチュア参加者が見つかりません。');
        }

        $data = $request->validate([
            'amateur_bowler_id' => ['nullable', 'integer', 'min:1'],
            'amateur_no' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:M,F,X'],
            'dominant_arm' => ['nullable', 'string', 'max:20'],
            'affiliation_name' => ['nullable', 'string', 'max:255'],
            'equipment_contract' => ['nullable', 'string', 'max:255'],
            'lane' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lane_slot' => ['nullable', 'integer', 'min:1', 'max:9'],
            'box_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'source_note' => ['nullable', 'string', 'max:2000'],
            'master_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $name = trim((string) ($data['name'] ?? ''));
        $amateurNo = $this->normalizeAmateurNo((string) ($data['amateur_no'] ?? ''));
        $nameKana = trim((string) ($data['name_kana'] ?? ''));
        $gender = $this->normalizeParticipantGender((string) ($data['gender'] ?? ($row->gender ?? '')));
        $dominantArm = $this->normalizeDominantArm((string) ($data['dominant_arm'] ?? ''));
        $affiliationName = trim((string) ($data['affiliation_name'] ?? ''));
        $equipmentContract = trim((string) ($data['equipment_contract'] ?? ''));
        $masterNote = trim((string) ($data['master_note'] ?? ''));

        $amateurBowlerId = !empty($data['amateur_bowler_id'])
            ? (int) $data['amateur_bowler_id']
            : (!empty($row->amateur_bowler_id) ? (int) $row->amateur_bowler_id : null);

        $now = now();
        $amateurBowler = null;

        $hasAmateurNoColumn = Schema::hasColumn('amateur_bowlers', 'amateur_no');

        if ($amateurBowlerId) {
            $amateurBowler = DB::table('amateur_bowlers')->where('id', $amateurBowlerId)->first();
            if (!$amateurBowler) {
                return redirect()
                    ->route('tournaments.entries.index', $row->tournament_id)
                    ->withErrors(['amateur_bowler_id' => '選択されたアマチュア選手マスターが見つかりません。'])
                    ->withInput();
            }

            if ($hasAmateurNoColumn) {
                $amateurNo = $amateurNo !== ''
                    ? $amateurNo
                    : $this->normalizeAmateurNo((string) ($amateurBowler->amateur_no ?? ''));

                if ($amateurNo === '') {
                    $amateurNo = $this->nextAmateurNo();
                }

                if ($this->amateurNoExists($amateurNo, (int) $amateurBowler->id)) {
                    return redirect()
                        ->route('tournaments.entries.index', $row->tournament_id)
                        ->withErrors(['amateur_no' => 'このアマチュア識別番号はすでに使用されています。'])
                        ->withInput();
                }
            }

            $masterUpdate = [
                'name' => $name,
                'name_kana' => $nameKana !== '' ? $nameKana : null,
                'gender' => $gender ?: null,
                'dominant_arm' => $dominantArm ?: null,
                'affiliation_name' => $affiliationName ?: null,
                'equipment_contract' => $equipmentContract ?: null,
                'note' => $masterNote !== '' ? $masterNote : null,
                'updated_at' => $now,
            ];

            if ($hasAmateurNoColumn) {
                $masterUpdate['amateur_no'] = $amateurNo;
            }

            DB::table('amateur_bowlers')
                ->where('id', $amateurBowler->id)
                ->update($masterUpdate);

            $amateurBowlerId = (int) $amateurBowler->id;
        } else {
            $insertPayload = [
                'name' => $name,
                'name_kana' => $nameKana !== '' ? $nameKana : null,
                'gender' => $gender ?: null,
                'dominant_arm' => $dominantArm ?: null,
                'affiliation_name' => $affiliationName ?: null,
                'equipment_contract' => $equipmentContract ?: null,
                'note' => $masterNote !== '' ? $masterNote : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasAmateurNoColumn) {
                $amateurNo = $amateurNo !== '' ? $amateurNo : $this->nextAmateurNo();

                if ($this->amateurNoExists($amateurNo)) {
                    return redirect()
                        ->route('tournaments.entries.index', $row->tournament_id)
                        ->withErrors(['amateur_no' => 'このアマチュア識別番号はすでに使用されています。'])
                        ->withInput();
                }

                $insertPayload['amateur_no'] = $amateurNo;
            }

            $amateurBowlerId = (int) DB::table('amateur_bowlers')->insertGetId($insertPayload);
        }

        $lane = isset($data['lane']) && $data['lane'] !== null && $data['lane'] !== '' ? (int) $data['lane'] : null;
        $laneSlot = isset($data['lane_slot']) && $data['lane_slot'] !== null && $data['lane_slot'] !== '' ? (int) $data['lane_slot'] : null;
        $boxNo = isset($data['box_no']) && $data['box_no'] !== null && $data['box_no'] !== '' ? (int) $data['box_no'] : null;
        $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;

        if (is_null($boxNo) && !is_null($lane)) {
            $boxNo = max(1, intdiv(max(1, $lane) - 1, 2));
        }

        if (is_null($sortOrder) && !is_null($lane)) {
            $sortOrder = ($lane * 10) + ($laneSlot ?? 0);
        }

        $amateurCode = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($amateurCode === '') {
            $amateurCode = $this->nextTournamentAmateurCode(Tournament::findOrFail((int) $row->tournament_id));
        }

        DB::table('tournament_participants')
            ->where('id', $participantId)
            ->update([
                'pro_bowler_license_no' => $amateurCode,
                'pro_bowler_id' => null,
                'amateur_bowler_id' => $amateurBowlerId,
                'participant_type' => 'amateur',
                'display_name' => $name,
                'display_license_no' => 'アマ',
                'display_dominant_arm' => $dominantArm ?: null,
                'display_affiliation_name' => $affiliationName ?: null,
                'display_equipment_contract' => $equipmentContract ?: null,
                'gender' => $gender ?: null,
                'lane' => $lane,
                'lane_slot' => $laneSlot,
                'lane_label' => !is_null($lane) && !is_null($laneSlot) ? sprintf('%dL-%d', $lane, $laneSlot) : null,
                'box_no' => $boxNo,
                'sort_order' => $sortOrder,
                'source_note' => trim((string) ($data['source_note'] ?? '')) ?: null,
                'is_temporary' => true,
                'updated_at' => $now,
            ]);

        DB::table('game_scores')
            ->where('tournament_participant_id', $participantId)
            ->update([
                'license_number' => 'アマ',
                'entry_number' => $amateurCode,
                'name' => $name,
                'updated_at' => $now,
            ]);

        return redirect()
            ->route('tournaments.entries.index', $row->tournament_id)
            ->with('success', 'アマチュア参加者を更新しました。 [' . $amateurCode . ' ' . $name . ']');
    }

    public function destroyAmateurParticipant($participant)
    {
        $participantId = (int) $participant;
        $row = DB::table('tournament_participants')
            ->where('id', $participantId)
            ->where('participant_type', 'amateur')
            ->first();

        if (!$row) {
            return redirect()->back()->with('error', '削除対象のアマチュア参加者が見つかりません。');
        }

        $scoreCount = DB::table('game_scores')
            ->where('tournament_participant_id', $participantId)
            ->count();

        if ($scoreCount > 0) {
            return redirect()->back()->with('error', 'スコア入力済みのため削除できません。必要な場合は非表示運用または別途修正してください。');
        }

        DB::table('tournament_participants')->where('id', $participantId)->delete();

        return redirect()
            ->route('tournaments.entries.index', $row->tournament_id)
            ->with('success', 'アマチュア参加者を削除しました。');
    }

    public function promoteWaitlist(TournamentEntry $entry)
    {
        if ((string) $entry->status !== 'waiting') {
            return redirect()
                ->route('tournaments.entries.index', $entry->tournament_id)
                ->with('error', 'ウェイティング行のみ繰り上げできます。');
        }

        $eligibility = $this->resolveEligibility($entry->bowler);
        if (($eligibility['short'] ?? '') !== '参加権利あり') {
            return redirect()
                ->route('tournaments.entries.index', $entry->tournament_id)
                ->with('error', '参加権利がない選手は参加へ繰り上げできません。先に会員区分・出場可否を確認してください。');
        }

        $fromStatus = (string) $entry->status;

        $entry->update([
            'status' => 'entry',
            'promoted_from_waitlist_at' => now(),
            'shift' => null,
            'lane' => null,
            'checked_in_at' => null,
            'shift_drawn' => false,
            'lane_drawn' => false,
        ]);

        $entry->refresh();
        $entry->loadMissing('bowler');

        $this->recordEntryOperation(
            $entry,
            'waitlist_promoted',
            null,
            [
                'bulk' => false,
                'waitlist_priority' => $entry->waitlist_priority,
            ],
            $fromStatus,
            'entry'
        );

        return redirect()
            ->route('tournaments.entries.index', $entry->tournament_id)
            ->with('success', 'ウェイティングから参加へ繰り上げました。');
    }

    public function bulkPromoteWaitlist(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer'],
        ], [
            'entry_ids.required' => '参加へ繰り上げるウェイティング行を選択してください。',
            'entry_ids.min' => '参加へ繰り上げるウェイティング行を選択してください。',
        ]);

        $entryIds = collect($data['entry_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($entryIds->isEmpty()) {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->with('error', '参加へ繰り上げるウェイティング行を選択してください。');
        }

        $entries = TournamentEntry::query()
            ->with('bowler')
            ->where('tournament_id', $tournament->id)
            ->whereIn('id', $entryIds)
            ->where('status', 'waiting')
            ->get();

        if ($entries->isEmpty()) {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->with('error', '対象のウェイティング行が見つかりませんでした。');
        }

        $promotedCount = 0;
        $skippedCount = 0;
        $batchKey = (string) Str::uuid();
        $promotedAt = now();

        foreach ($entries as $entry) {
            $eligibility = $this->resolveEligibility($entry->bowler);
            if (($eligibility['short'] ?? '') !== '参加権利あり') {
                $this->recordEntryOperation(
                    $entry,
                    'waitlist_bulk_skipped',
                    $eligibility['message'] ?? '参加権利なし',
                    [
                        'batch_key' => $batchKey,
                        'eligibility_short' => $eligibility['short'] ?? null,
                    ],
                    (string) $entry->status,
                    (string) $entry->status,
                    $batchKey
                );

                $skippedCount++;
                continue;
            }

            $fromStatus = (string) $entry->status;
            $waitlistPriority = $entry->waitlist_priority;

            $entry->update([
                'status' => 'entry',
                'promoted_from_waitlist_at' => $promotedAt,
                'shift' => null,
                'lane' => null,
                'checked_in_at' => null,
                'shift_drawn' => false,
                'lane_drawn' => false,
            ]);

            $this->recordEntryOperation(
                $entry,
                'waitlist_bulk_promoted',
                null,
                [
                    'batch_key' => $batchKey,
                    'waitlist_priority' => $waitlistPriority,
                ],
                $fromStatus,
                'entry',
                $batchKey
            );

            $promotedCount++;
        }

        if ($promotedCount === 0) {
            return redirect()
                ->route('tournaments.entries.index', $tournament->id)
                ->with('error', '参加権利のあるウェイティング行が選択されていないため、繰り上げは行いませんでした。');
        }

        $message = $promotedCount . '名をウェイティングから参加へ繰り上げました。';
        if ($skippedCount > 0) {
            $message .= ' 参加権利がない ' . $skippedCount . '名はスキップしました。';
        }

        return redirect()
            ->route('tournaments.entries.index', $tournament->id)
            ->with('success', $message);
    }

    public function cancel(Request $request, TournamentEntry $entry)
    {
        if (!in_array((string) $entry->status, ['entry', 'waiting'], true)) {
            return redirect()
                ->route('tournaments.entries.index', $entry->tournament_id)
                ->with('error', '参加またはウェイティングの行のみ取り消しできます。');
        }

        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ], [
            'cancel_reason.required' => '取消理由を入力してください。',
            'cancel_reason.max' => '取消理由は1000文字以内で入力してください。',
        ]);

        $reason = trim((string) $data['cancel_reason']);
        $fromStatus = (string) $entry->status;
        $previousState = [
            'preferred_shift_code' => $entry->preferred_shift_code,
            'shift' => $entry->shift,
            'lane' => $entry->lane,
            'checked_in_at' => $entry->checked_in_at,
            'waitlist_priority' => $entry->waitlist_priority,
            'waitlisted_at' => $entry->waitlisted_at,
            'promoted_from_waitlist_at' => $entry->promoted_from_waitlist_at,
            'shift_drawn' => (bool) $entry->shift_drawn,
            'lane_drawn' => (bool) $entry->lane_drawn,
        ];

        $entry->update([
            'status' => 'no_entry',
            'preferred_shift_code' => null,
            'shift' => null,
            'lane' => null,
            'checked_in_at' => null,
            'waitlist_priority' => null,
            'waitlisted_at' => null,
            'waitlist_note' => null,
            'promoted_from_waitlist_at' => null,
            'shift_drawn' => false,
            'lane_drawn' => false,
        ]);

        $entry->refresh();
        $entry->loadMissing('bowler');

        $this->recordEntryOperation(
            $entry,
            'entry_cancelled',
            $reason,
            ['previous_state' => $previousState],
            $fromStatus,
            'no_entry'
        );

        return redirect()
            ->route('tournaments.entries.index', $entry->tournament_id)
            ->with('success', 'エントリー / ウェイティングを取り消しました。');
    }

    private function applyBowlerKeyword(Builder $query, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $query->whereHas('bowler', function (Builder $q) use ($keyword) {
            $q->where('license_no', 'like', '%' . $keyword . '%')
                ->orWhere('name_kanji', 'like', '%' . $keyword . '%')
                ->orWhere('name_kana', 'like', '%' . $keyword . '%');
        });
    }

    private function buildSummary(Tournament $tournament, array $priorityLookup): array
    {
        $base = TournamentEntry::query()->where('tournament_id', $tournament->id);

        $activeEntries = TournamentEntry::query()
            ->with('bowler')
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['entry', 'waiting'])
            ->get();

        $decorated = $this->decorateEntriesWithPriority($activeEntries, $priorityLookup);
        $priorityCoverage = $this->buildPriorityCoverage($tournament, $priorityLookup);

        return [
            'entry_count' => (clone $base)->where('status', 'entry')->count(),
            'waitlist_count' => (clone $base)->where('status', 'waiting')->count(),
            'checked_in_count' => (clone $base)->whereNotNull('checked_in_at')->count(),
            'pending_shift_count' => (clone $base)->where('status', 'entry')->whereNull('shift')->count(),
            'pending_lane_count' => (clone $base)->where('status', 'entry')->whereNull('lane')->count(),
            'preferred_shift_count' => (clone $base)->where('status', 'entry')->whereNotNull('preferred_shift_code')->count(),
            'priority_entry_count' => $decorated
                ->filter(fn (TournamentEntry $entry) => (bool) ($entry->is_priority_entry ?? false) && $entry->status === 'entry')
                ->count(),
            'priority_waitlist_count' => $decorated
                ->filter(fn (TournamentEntry $entry) => (bool) ($entry->is_priority_entry ?? false) && $entry->status === 'waiting')
                ->count(),
            'priority_total_count' => $priorityCoverage['total_count'],
            'priority_missing_count' => $priorityCoverage['missing_count'],
            'priority_missing_entries' => $priorityCoverage['missing_entries'],
        ];
    }

    private function recentEntryOperationLogs(Tournament $tournament): Collection
    {
        if (!Schema::hasTable('tournament_entry_operation_logs')) {
            return collect();
        }

        return DB::table('tournament_entry_operation_logs as log')
            ->leftJoin('pro_bowlers as pb', 'pb.id', '=', 'log.pro_bowler_id')
            ->leftJoin('users as u', 'u.id', '=', 'log.actor_user_id')
            ->where('log.tournament_id', $tournament->id)
            ->orderByDesc('log.occurred_at')
            ->orderByDesc('log.id')
            ->limit(20)
            ->get([
                'log.*',
                'pb.license_no as bowler_license_no',
                'pb.name_kanji as bowler_name',
                'u.name as actor_name',
            ])
            ->map(function ($row) {
                $payload = json_decode((string) ($row->payload ?? ''), true);
                $payload = is_array($payload) ? $payload : [];

                $row->action_label = $this->entryOperationActionLabel((string) $row->action);
                $row->payload_array = $payload;
                $row->batch_label = $row->batch_key ?: ($payload['batch_key'] ?? null);
                $row->actor_label = $row->actor_name ?: ($row->actor_user_id ? ('user#' . $row->actor_user_id) : '-');

                return $row;
            });
    }

    private function recordEntryOperation(
        TournamentEntry $entry,
        string $action,
        ?string $reason = null,
        array $payload = [],
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $batchKey = null
    ): void {
        if (!Schema::hasTable('tournament_entry_operation_logs')) {
            return;
        }

        $now = now();

        DB::table('tournament_entry_operation_logs')->insert([
            'tournament_id' => $entry->tournament_id,
            'tournament_entry_id' => $entry->id,
            'pro_bowler_id' => $entry->pro_bowler_id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'batch_key' => $batchKey,
            'actor_user_id' => auth()->id(),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function entryOperationActionLabel(string $action): string
    {
        return match ($action) {
            'waitlist_registered' => 'ウェイティング登録',
            'waitlist_promoted' => '参加繰り上げ',
            'waitlist_bulk_promoted' => '一括繰り上げ',
            'waitlist_bulk_skipped' => '一括繰り上げ対象外',
            'entry_cancelled' => '取消',
            'check_in' => 'チェックイン',
            default => $action,
        };
    }

    private function buildPriorityCoverage(Tournament $tournament, array $priorityLookup): array
    {
        $candidates = collect(array_values($priorityLookup))
            ->filter(function (array $candidate) {
                return !empty($candidate['pro_bowler_id']) || trim((string) ($candidate['license_no'] ?? '')) !== '';
            })
            ->unique(function (array $candidate) {
                if (!empty($candidate['pro_bowler_id'])) {
                    return 'id:' . (int) $candidate['pro_bowler_id'];
                }

                return 'license:' . strtoupper(trim((string) ($candidate['license_no'] ?? '')));
            })
            ->sortBy(fn (array $candidate) => sprintf(
                '%06d-%s',
                (int) ($candidate['priority_sort'] ?? 999999),
                strtoupper((string) ($candidate['license_no'] ?? ''))
            ))
            ->values();

        $bowlerIds = $candidates
            ->pluck('pro_bowler_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $bowlersById = $bowlerIds->isNotEmpty()
            ? ProBowler::query()->whereIn('id', $bowlerIds)->get()->keyBy('id')
            : collect();

        $activeEntries = TournamentEntry::query()
            ->with('bowler')
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['entry', 'waiting'])
            ->get();

        $entriesByBowlerId = $activeEntries
            ->filter(fn (TournamentEntry $entry) => !is_null($entry->pro_bowler_id))
            ->keyBy(fn (TournamentEntry $entry) => (int) $entry->pro_bowler_id);

        $entriesByLicenseKey = [];
        foreach ($activeEntries as $entry) {
            $entryLicenseNo = strtoupper(trim((string) ($entry->bowler?->license_no ?? '')));
            if ($entryLicenseNo === '') {
                continue;
            }

            $entriesByLicenseKey['license:' . $entryLicenseNo] = $entry;

            $tail = $this->licenseTail($entryLicenseNo);
            if ($tail !== '') {
                $entriesByLicenseKey['tail:' . $tail] = $entry;
            }
        }

        $missingEntries = [];

        foreach ($candidates as $candidate) {
            $proBowlerId = !empty($candidate['pro_bowler_id']) ? (int) $candidate['pro_bowler_id'] : null;
            $candidateLicenseNo = strtoupper(trim((string) ($candidate['license_no'] ?? '')));
            $entry = null;

            if ($proBowlerId && $entriesByBowlerId->has($proBowlerId)) {
                $entry = $entriesByBowlerId->get($proBowlerId);
            }

            if (!$entry && $candidateLicenseNo !== '') {
                $entry = $entriesByLicenseKey['license:' . $candidateLicenseNo] ?? null;

                if (!$entry) {
                    $tail = $this->licenseTail($candidateLicenseNo);
                    $entry = $tail !== '' ? ($entriesByLicenseKey['tail:' . $tail] ?? null) : null;
                }
            }

            if ($entry) {
                continue;
            }

            $bowler = $proBowlerId ? $bowlersById->get($proBowlerId) : null;

            $eligibility = $this->resolveEligibility($bowler);

            $missingEntries[] = [
                'license_no' => $bowler?->license_no ?: ($candidate['license_no'] ?? null),
                'name_kanji' => $bowler?->name_kanji ?: '-',
                'priority_order_label' => $candidate['priority_order_label'] ?? '-',
                'priority_label' => $candidate['priority_label'] ?? '-',
                'priority_source_label' => $candidate['priority_source_label'] ?? '-',
                'priority_note' => $candidate['priority_note'] ?? null,
                'priority_sort' => (int) ($candidate['priority_sort'] ?? 999999),
                'eligibility_short' => $eligibility['short'],
                'eligibility_message' => $eligibility['message'],
                'eligibility_sort' => ($eligibility['short'] ?? '') === '参加権利あり' ? 0 : 1,
            ];
        }

        $missingEntries = collect($missingEntries)
            ->sortBy(fn (array $entry) => sprintf(
                '%d-%06d-%s',
                (int) ($entry['eligibility_sort'] ?? 1),
                (int) ($entry['priority_sort'] ?? 999999),
                strtoupper((string) ($entry['license_no'] ?? ''))
            ))
            ->values()
            ->all();

        return [
            'total_count' => $candidates->count(),
            'missing_count' => count($missingEntries),
            'missing_entries' => array_slice($missingEntries, 0, 20),
        ];
    }

    private function loadAmateurBowlersForSelect(): Collection
    {
        if (!Schema::hasTable('amateur_bowlers')) {
            return collect();
        }

        return DB::table('amateur_bowlers')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    private function loadTournamentAmateurParticipants(Tournament $tournament): Collection
    {
        if (!Schema::hasTable('amateur_bowlers') || !Schema::hasColumn('tournament_participants', 'amateur_bowler_id')) {
            return collect();
        }

        return DB::table('tournament_participants as tp')
            ->leftJoin('amateur_bowlers as ab', 'ab.id', '=', 'tp.amateur_bowler_id')
            ->where('tp.tournament_id', $tournament->id)
            ->where('tp.participant_type', 'amateur')
            ->select([
                'tp.id',
                'tp.pro_bowler_license_no',
                'tp.display_license_no',
                'tp.display_name',
                'tp.display_dominant_arm',
                'tp.display_affiliation_name',
                'tp.display_equipment_contract',
                'tp.gender',
                'tp.shift',
                'tp.lane',
                'tp.lane_slot',
                'tp.lane_label',
                'tp.box_no',
                'tp.sort_order',
                'tp.source_note',
                'tp.amateur_bowler_id',
                'ab.name as master_name',
                'ab.amateur_no as master_amateur_no',
                'ab.name_kana as master_name_kana',
                'ab.dominant_arm as master_dominant_arm',
                'ab.affiliation_name as master_affiliation_name',
                'ab.equipment_contract as master_equipment_contract',
                'ab.note as master_note',
            ])
            ->orderByRaw('COALESCE(tp.sort_order, 999999)')
            ->orderBy('tp.lane')
            ->orderBy('tp.lane_slot')
            ->orderBy('tp.id')
            ->get();
    }

    private function normalizeAmateurNo(string $value): string
    {
        $value = mb_convert_kana(trim($value), 'as', 'UTF-8');
        $value = strtoupper(str_replace([' ', '　', '-', '_'], '', $value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'A' . str_pad($value, 6, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^A(\d+)$/', $value, $matches) === 1) {
            return 'A' . str_pad($matches[1], 6, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function amateurNoExists(string $amateurNo, ?int $exceptId = null): bool
    {
        if ($amateurNo === '' || !Schema::hasColumn('amateur_bowlers', 'amateur_no')) {
            return false;
        }

        return DB::table('amateur_bowlers')
            ->where('amateur_no', $amateurNo)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '<>', $exceptId))
            ->exists();
    }

    private function nextAmateurNo(): string
    {
        if (!Schema::hasTable('amateur_bowlers') || !Schema::hasColumn('amateur_bowlers', 'amateur_no')) {
            return '';
        }

        $max = 0;
        $codes = DB::table('amateur_bowlers')->pluck('amateur_no');

        foreach ($codes as $code) {
            $normalized = $this->normalizeAmateurNo((string) $code);
            if (preg_match('/^A(\d+)$/', $normalized, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'A' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function nextTournamentAmateurCode(Tournament $tournament): string
    {
        $codes = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->where('participant_type', 'amateur')
            ->pluck('pro_bowler_license_no');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^AM-(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'AM-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function normalizeParticipantGender(string $gender): string
    {
        $gender = strtoupper(trim($gender));

        return in_array($gender, ['M', 'F', 'X'], true) ? $gender : '';
    }

    private function normalizeDominantArm(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $upper = strtoupper($value);

        return match ($upper) {
            'R', 'RIGHT', '右投げ', '右' => '右',
            'L', 'LEFT', '左投げ', '左' => '左',
            'B', 'BOTH', '両', '両手' => '両',
            default => $value,
        };
    }

    private function buildTournamentPriorityLookup(Tournament $tournament): array
    {
        $lookup = [];
        $gender = $this->normalizeTournamentGender($tournament);
        $seedYear = $this->resolveTournamentSeedYear($tournament);

        if ($seedYear) {
            $annualRows = DB::table('pro_bowler_seed_lists as seed_lists')
                ->join('pro_bowler_seed_list_players as seed_players', 'seed_players.seed_list_id', '=', 'seed_lists.id')
                ->leftJoin('pro_bowlers as bowlers', 'bowlers.id', '=', 'seed_players.pro_bowler_id')
                ->where('seed_lists.seed_year', $seedYear)
                ->where('seed_lists.seed_list_type', 'tournament_seed')
                ->where('seed_lists.is_active', true)
                ->where('seed_players.is_active', true)
                ->when($gender !== '', function ($query) use ($gender) {
                    $query->where(function ($q) use ($gender) {
                        $q->where('seed_lists.gender', $gender)
                            ->orWhere('seed_lists.gender', 'X');
                    });
                })
                ->select([
                    'seed_players.pro_bowler_id',
                    'seed_players.license_no',
                    'seed_players.seed_category',
                    'seed_players.priority_order',
                    'seed_players.seed_rank',
                    'seed_players.ranking_rank',
                    'seed_players.note',
                    'bowlers.license_no as bowler_license_no',
                ])
                ->get();

            foreach ($annualRows as $row) {
                $licenseNo = $row->bowler_license_no ?: $row->license_no;
                $sort = $this->normalizePrioritySort(
                    $row->priority_order,
                    $row->seed_rank,
                    $row->ranking_rank,
                    9000
                );

                $this->addPriorityCandidate($lookup, [
                    'pro_bowler_id' => $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                    'license_no' => $licenseNo,
                    'priority_sort' => $sort,
                    'priority_order_label' => $sort < 999999 ? (string) $sort : '-',
                    'priority_label' => $this->seedCategoryLabel((string) $row->seed_category),
                    'priority_source_label' => '年度別シード',
                    'priority_note' => $row->note ?: null,
                    'priority_badge_class' => 'bg-success',
                ]);
            }
        }

        $tournamentRows = DB::table('tournament_seed_players as seed_players')
            ->leftJoin('pro_bowlers as bowlers', 'bowlers.id', '=', 'seed_players.pro_bowler_id')
            ->where('seed_players.tournament_id', $tournament->id)
            ->where('seed_players.is_active', true)
            ->select([
                'seed_players.pro_bowler_id',
                'seed_players.license_no',
                'seed_players.seed_source_type',
                'seed_players.priority_order',
                'seed_players.display_label',
                'seed_players.note',
                'bowlers.license_no as bowler_license_no',
            ])
            ->get();

        foreach ($tournamentRows as $row) {
            $licenseNo = $row->bowler_license_no ?: $row->license_no;
            $sort = $this->normalizePrioritySort(
                $row->priority_order,
                null,
                null,
                9500
            );

            $this->addPriorityCandidate($lookup, [
                'pro_bowler_id' => $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                'license_no' => $licenseNo,
                'priority_sort' => $sort,
                'priority_order_label' => $sort < 999999 ? (string) $sort : '-',
                'priority_label' => $row->display_label ?: $this->seedSourceTypeLabel((string) $row->seed_source_type),
                'priority_source_label' => '大会別追加',
                'priority_note' => $row->note ?: null,
                'priority_badge_class' => 'bg-primary',
            ]);
        }

        return $lookup;
    }

    private function addPriorityCandidate(array &$lookup, array $candidate): void
    {
        foreach ($this->priorityKeys($candidate['pro_bowler_id'] ?? null, $candidate['license_no'] ?? null) as $key) {
            if (!isset($lookup[$key]) || (int) $candidate['priority_sort'] < (int) $lookup[$key]['priority_sort']) {
                $lookup[$key] = $candidate;
            }
        }
    }

    private function decorateEntriesWithParticipantDisplay(Collection $entries, Tournament $tournament): Collection
    {
        $bowlerIds = $entries
            ->pluck('pro_bowler_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $participantsByBowlerId = $bowlerIds->isNotEmpty()
            ? DB::table('tournament_participants')
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('pro_bowler_id')
                ->whereIn('pro_bowler_id', $bowlerIds)
                ->select([
                    'pro_bowler_id',
                    'display_name',
                    'display_license_no',
                    'shift',
                    'lane',
                    'lane_slot',
                    'lane_label',
                    'box_no',
                    'sort_order',
                ])
                ->get()
                ->keyBy(fn ($participant) => (int) $participant->pro_bowler_id)
            : collect();

        $seedService = app(ProBowlerSeedService::class);

        return $entries->map(function (TournamentEntry $entry) use ($participantsByBowlerId, $seedService, $tournament) {
            $participant = $entry->pro_bowler_id
                ? $participantsByBowlerId->get((int) $entry->pro_bowler_id)
                : null;

            $rawLicenseNo = filled($participant?->display_license_no)
                ? (string) $participant->display_license_no
                : ($entry->bowler?->license_no ?? '-');

            $entry->participant_display_license_no = $this->formatEntryLicenseNoForSeedDisplay(
                $seedService,
                $tournament,
                $entry,
                $rawLicenseNo
            );

            $entry->participant_display_name = filled($participant?->display_name)
                ? (string) $participant->display_name
                : ($entry->bowler?->name_kanji ?? '-');

            $entry->participant_shift = filled($participant?->shift)
                ? (string) $participant->shift
                : ($entry->shift ?? null);

            $entry->participant_lane_label = filled($participant?->lane_label)
                ? (string) $participant->lane_label
                : (filled($entry->lane) ? (string) $entry->lane : null);

            $entry->participant_lane = !is_null($participant?->lane)
                ? (int) $participant->lane
                : (!is_null($entry->lane) ? (int) $entry->lane : null);

            $entry->participant_lane_slot = !is_null($participant?->lane_slot)
                ? (int) $participant->lane_slot
                : null;

            $entry->participant_box_no = !is_null($participant?->box_no)
                ? (int) $participant->box_no
                : null;

            $entry->participant_sort_order = !is_null($participant?->sort_order)
                ? (int) $participant->sort_order
                : null;

            return $entry;
        });
    }

    private function formatEntryLicenseNoForSeedDisplay(
        ProBowlerSeedService $seedService,
        Tournament $tournament,
        TournamentEntry $entry,
        ?string $fallbackLicenseNo
    ): string {
        $licenseNo = trim((string) ($entry->bowler?->license_no ?? $fallbackLicenseNo ?? ''));

        if ($licenseNo === '' || $licenseNo === '-') {
            return $fallbackLicenseNo ?: '-';
        }

        $display = $seedService->formatLicenseForTournamentPdf(
            (int) $tournament->id,
            $entry->pro_bowler_id ? (int) $entry->pro_bowler_id : ($entry->bowler?->id ? (int) $entry->bowler->id : null),
            $licenseNo
        );

        return $display !== '' ? $display : ($fallbackLicenseNo ?: '-');
    }

    private function decorateEntriesWithPriority(Collection $entries, array $priorityLookup): Collection
    {
        return $entries->map(function (TournamentEntry $entry) use ($priorityLookup) {
            $priority = $this->findPriorityForEntry($entry, $priorityLookup);
            $eligibility = $this->resolveEligibility($entry->bowler);

            $entry->is_priority_entry = (bool) $priority;
            $entry->priority_info = $priority;
            $entry->priority_sort = $priority['priority_sort'] ?? 999999;
            $entry->priority_order_label = $priority['priority_order_label'] ?? '-';
            $entry->priority_label = $priority['priority_label'] ?? '-';
            $entry->priority_source_label = $priority['priority_source_label'] ?? '-';
            $entry->priority_note = $priority['priority_note'] ?? null;
            $entry->priority_badge_class = $priority['priority_badge_class'] ?? 'bg-secondary';
            $entry->eligibility_short = $eligibility['short'];
            $entry->eligibility_message = $eligibility['message'];
            $entry->eligibility_sort = ($eligibility['short'] ?? '') === '参加権利あり' ? 0 : 1;

            return $entry;
        });
    }

    private function findPriorityForEntry(TournamentEntry $entry, array $priorityLookup): ?array
    {
        return $this->findPriorityForBowler($entry->bowler, $priorityLookup);
    }

    private function findPriorityForBowler(?ProBowler $bowler, array $priorityLookup): ?array
    {
        if (!$bowler) {
            return null;
        }

        foreach ($this->priorityKeys($bowler->id ? (int) $bowler->id : null, $bowler->license_no ?? null) as $key) {
            if (isset($priorityLookup[$key])) {
                return $priorityLookup[$key];
            }
        }

        return null;
    }

    private function priorityKeys(?int $proBowlerId, ?string $licenseNo): array
    {
        $keys = [];

        if ($proBowlerId) {
            $keys[] = 'id:' . $proBowlerId;
        }

        $licenseNo = strtoupper(trim((string) $licenseNo));
        if ($licenseNo !== '') {
            $keys[] = 'license:' . $licenseNo;

            $tail = $this->licenseTail($licenseNo);
            if ($tail !== '') {
                $keys[] = 'tail:' . $tail;
            }
        }

        return array_values(array_unique($keys));
    }

    private function sortAdminIndexEntries(Collection $entries): Collection
    {
        return $entries
            ->sortBy(function (TournamentEntry $entry) {
                $displayShift = $entry->participant_shift ?? $entry->shift;
                $displaySortOrder = $entry->participant_sort_order ?? null;

                return sprintf(
                    '%d-%d-%06d-%06d-%d-%s-%d-%06d-%08d',
                    (int) ($entry->eligibility_sort ?? 1),
                    $this->statusSortValue((string) $entry->status),
                    (int) ($entry->priority_sort ?? 999999),
                    $this->nullableIntSortValue($entry->waitlist_priority),
                    blank($displayShift) ? 1 : 0,
                    (string) ($displayShift ?? ''),
                    is_null($displaySortOrder) ? (blank($entry->lane) ? 1 : 0) : 0,
                    is_null($displaySortOrder) ? $this->nullableIntSortValue($entry->lane) : (int) $displaySortOrder,
                    (int) $entry->id
                );
            })
            ->values();
    }

    private function sortAdminDrawEntries(Collection $entries): Collection
    {
        return $entries
            ->sortBy(fn (TournamentEntry $entry) => sprintf(
                '%d-%06d-%d-%s-%d-%s-%08d',
                (int) ($entry->eligibility_sort ?? 1),
                (int) ($entry->priority_sort ?? 999999),
                blank($entry->shift) ? 0 : 1,
                (string) ($entry->shift ?? ''),
                blank($entry->lane) ? 0 : 1,
                (string) ($entry->lane ?? ''),
                (int) $entry->id
            ))
            ->values();
    }

    private function paginateCollection(Collection $items, Request $request, int $perPage = 100): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function statusSortValue(string $status): int
    {
        return match ($status) {
            'entry' => 0,
            'waiting' => 1,
            'no_entry' => 2,
            default => 9,
        };
    }

    private function nullableIntSortValue($value): int
    {
        return is_null($value) ? 999999 : (int) $value;
    }

    private function normalizePrioritySort($primary, $secondary, $tertiary, int $fallback): int
    {
        foreach ([$primary, $secondary, $tertiary] as $value) {
            if (!is_null($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return $fallback;
    }

    private function resolveBowlerByLicenseInput(string $licenseNo, Tournament $tournament): array
    {
        $licenseNo = strtoupper(trim($licenseNo));
        if ($licenseNo === '') {
            return [
                'bowler' => null,
                'message' => 'ライセンスNoを入力してください。',
            ];
        }

        $gender = $this->normalizeTournamentGender($tournament);
        $inputPrefix = substr($licenseNo, 0, 1);

        if (in_array($inputPrefix, ['M', 'F'], true) && in_array($gender, ['M', 'F'], true) && $inputPrefix !== $gender) {
            return [
                'bowler' => null,
                'message' => '入力されたライセンスNoの性別と大会の対象性別が一致しません。',
            ];
        }

        $exact = ProBowler::query()
            ->whereRaw('upper(license_no) = ?', [$licenseNo])
            ->first();

        if ($exact) {
            if (in_array($gender, ['M', 'F'], true) && !$this->bowlerMatchesTournamentGender($exact, $gender)) {
                return [
                    'bowler' => null,
                    'message' => '該当選手は大会の対象性別と一致しません。',
                ];
            }

            return [
                'bowler' => $exact,
                'message' => '',
            ];
        }

        $tail = preg_replace('/\D/', '', $licenseNo);
        if (strlen($tail) > 4) {
            $tail = substr($tail, -4);
        }

        if ($tail === '') {
            return [
                'bowler' => null,
                'message' => '該当ライセンスNoの選手が見つかりません。',
            ];
        }

        $query = ProBowler::query()
            ->whereRaw('right(license_no, 4) = ?', [str_pad($tail, 4, '0', STR_PAD_LEFT)]);

        if (in_array($gender, ['M', 'F'], true)) {
            $query->where('sex', $gender === 'M' ? 1 : 2);
        }

        $matches = $query
            ->orderBy('license_no')
            ->limit(2)
            ->get();

        if ($matches->count() === 1) {
            return [
                'bowler' => $matches->first(),
                'message' => '',
            ];
        }

        if ($matches->count() > 1) {
            return [
                'bowler' => null,
                'message' => '同じ下4桁の選手が複数見つかりました。フルのライセンスNoで入力してください。',
            ];
        }

        return [
            'bowler' => null,
            'message' => '大会の対象性別に一致する選手が見つかりません。フルのライセンスNoで確認してください。',
        ];
    }

    private function bowlerMatchesTournamentGender(ProBowler $bowler, string $gender): bool
    {
        if ($gender === 'M') {
            return (int) ($bowler->sex ?? 0) === 1;
        }

        if ($gender === 'F') {
            return (int) ($bowler->sex ?? 0) === 2;
        }

        return true;
    }

    private function normalizeTournamentGender(Tournament $tournament): string
    {
        $gender = strtoupper(trim((string) ($tournament->gender ?? '')));

        if (str_contains($gender, '男')) {
            return 'M';
        }

        if (str_contains($gender, '女')) {
            return 'F';
        }

        return match ($gender) {
            'M', 'MALE' => 'M',
            'F', 'FEMALE' => 'F',
            default => $gender,
        };
    }

    private function resolveTournamentSeedYear(Tournament $tournament): ?int
    {
        if (!is_null($tournament->year) && (int) $tournament->year > 0) {
            return (int) $tournament->year;
        }

        if (!empty($tournament->start_date)) {
            return (int) date('Y', strtotime((string) $tournament->start_date));
        }

        return null;
    }

    private function licenseTail(?string $licenseNo): string
    {
        $licenseNo = trim((string) $licenseNo);
        if ($licenseNo === '') {
            return '';
        }

        return substr($licenseNo, -4);
    }

    private function seedCategoryLabel(string $category): string
    {
        return match ($category) {
            'TS' => 'トーナメントシード',
            'V20' => '永久シード',
            'V10' => '準永久シード',
            'JS' => '全日本選手権者シード',
            'CS1' => '当該年度優勝者シード',
            'CS2' => '前年度優勝者シード',
            'PAST_CHAMPION' => '歴代優勝者シード',
            'MANUAL' => '手動追加',
            default => $category !== '' ? $category : '年度別シード',
        };
    }

    private function seedSourceTypeLabel(string $type): string
    {
        return match ($type) {
            'seed_list' => '年度別シードリスト由来',
            'previous_year_ranking_top24' => '前年度ランキング上位24名',
            'current_year_ranking' => '当該年度ランキング',
            'permanent_seed' => '永久シード',
            'semi_permanent_seed' => '準永久シード',
            'all_japan_champion' => '全日本選手権者シード',
            'current_year_winner' => '当該年度優勝者シード',
            'previous_year_winner' => '前年度優勝者シード',
            'past_champion' => '公認T/M歴代優勝者シード',
            'event_sponsor_recommendation' => '本大会スポンサー推薦',
            'organizer_recommendation' => '主催者推薦',
            'pro_test_practical_exempt' => 'プロテスト実技免除合格者',
            'pro_test_top_passer' => 'プロテストトップ合格者',
            'season_trial_participant' => 'シーズントライアル出場者',
            'manual' => '手動追加',
            default => $type !== '' ? $type : '大会別追加',
        };
    }

    private function resolveEligibility(?ProBowler $bowler): array
    {
        if (!$bowler) {
            return [
                'short' => '未結線',
                'message' => '選手情報未結線',
            ];
        }

        $memberClass = (string) ($bowler->member_class ?? '');
        $isActive = (bool) ($bowler->is_active ?? false);
        $canEnter = (bool) ($bowler->can_enter_official_tournament ?? false);

        if (!$isActive) {
            return [
                'short' => '会員無効',
                'message' => '現在の会員状態が無効です。',
            ];
        }

        if ($memberClass !== 'player') {
            return [
                'short' => $this->memberClassLabel($memberClass),
                'message' => '競技参加対象外の会員区分です。',
            ];
        }

        if (!$canEnter) {
            return [
                'short' => '公式戦対象外',
                'message' => '公式戦出場対象外として登録されています。',
            ];
        }

        return [
            'short' => '参加権利あり',
            'message' => '通常参加対象です。',
        ];
    }

    private function memberClassLabel(?string $memberClass): string
    {
        return match ($memberClass) {
            'player' => '競技者',
            'pro_instructor' => 'プロインストラクター',
            'honorary_or_overseas' => '名誉プロ・海外プロ',
            'other' => 'その他',
            default => '-',
        };
    }
}
