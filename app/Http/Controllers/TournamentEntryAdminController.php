<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Services\TournamentLaneMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        return view('tournament_entries.admin_index', compact(
            'tournament',
            'entries',
            'summary',
            'status',
            'keyword'
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

        return view('tournament_entries.admin_draws', compact(
            'tournament',
            'entries',
            'summary',
            'keyword',
            'pendingDraw'
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

        $message = 'ウェイティング登録を保存しました。 [' . $entry->bowler?->license_no . ' ' . ($entry->bowler?->name_kanji ?? '') . ']';

        if ($priorityInfo && is_null($data['waitlist_priority'] ?? null)) {
            $message .= ' 優先出場設定に基づき、優先順を自動補完しました。';
        }

        return redirect()
            ->route('tournaments.entries.index', $tournament->id)
            ->with('success', $message);
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

        $entry->update([
            'status' => 'entry',
            'promoted_from_waitlist_at' => now(),
            'shift' => null,
            'lane' => null,
            'checked_in_at' => null,
            'shift_drawn' => false,
            'lane_drawn' => false,
        ]);

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

        foreach ($entries as $entry) {
            $eligibility = $this->resolveEligibility($entry->bowler);
            if (($eligibility['short'] ?? '') !== '参加権利あり') {
                $skippedCount++;
                continue;
            }

            $entry->update([
                'status' => 'entry',
                'promoted_from_waitlist_at' => now(),
                'shift' => null,
                'lane' => null,
                'checked_in_at' => null,
                'shift_drawn' => false,
                'lane_drawn' => false,
            ]);

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

    public function cancel(TournamentEntry $entry)
    {
        if (!in_array((string) $entry->status, ['entry', 'waiting'], true)) {
            return redirect()
                ->route('tournaments.entries.index', $entry->tournament_id)
                ->with('error', '参加またはウェイティングの行のみ取り消しできます。');
        }

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

        return $entries->map(function (TournamentEntry $entry) use ($participantsByBowlerId) {
            $participant = $entry->pro_bowler_id
                ? $participantsByBowlerId->get((int) $entry->pro_bowler_id)
                : null;

            $entry->participant_display_license_no = filled($participant?->display_license_no)
                ? (string) $participant->display_license_no
                : ($entry->bowler?->license_no ?? '-');

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
