<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JapanOpenAdvancementService
{
    public const QUALIFIER_SOURCE_NOTE = 'ジャパンオープン オールエベンツ通過';

    public const DIRECT_SEED_SOURCE_NOTE = 'ジャパンオープン 大会シード';

    public const SEMIFINAL_SOURCE_NOTE = 'ジャパンオープン 予選8G通過';

    public const SEMIFINAL_STAGE = '準決勝';

    public const SEMIFINAL_ROUND_LABEL = '準決勝6G進出者';

    public function __construct(
        private readonly ProBowlerSeedService $seedService,
    ) {}

    /** @return array<string,mixed>|null */
    public function status(Tournament $allEventsTournament): ?array
    {
        $component = $this->componentCode($allEventsTournament);
        if (! in_array($component, ['men_all_events', 'women_all_events'], true)) {
            return null;
        }

        $target = $this->targetTournament($allEventsTournament);
        $snapshot = $this->currentSnapshot($allEventsTournament);
        $fieldSize = $this->configuredFieldSize($allEventsTournament);
        $reserved = $target ? $this->reservedEntries($target) : ['count' => 0];
        $remaining = max(0, $fieldSize - (int) $reserved['count']);
        $sync = (array) data_get($allEventsTournament->template_snapshot, 'japan_open.advancement_sync', []);

        return [
            'mode' => $component === 'men_all_events' ? 'per_shift' : 'overall',
            'target' => $target,
            'field_size' => $fieldSize,
            'source_snapshot' => $snapshot,
            'source_row_count' => $snapshot?->rows()->count() ?? 0,
            'reserved_entry_count' => (int) $reserved['count'],
            'required_qualifier_count' => $remaining,
            'synced_qualifier_count' => $target
                ? DB::table('tournament_participants')
                    ->where('tournament_id', $target->id)
                    ->where('source_note', 'like', self::QUALIFIER_SOURCE_NOTE.'%')
                    ->count()
                : 0,
            'suggested_shift_a_count' => $remaining % 2 === 0 ? intdiv($remaining, 2) : null,
            'suggested_shift_b_count' => $remaining % 2 === 0 ? intdiv($remaining, 2) : null,
            'last_sync' => $sync,
        ];
    }

    /** @return array<string,mixed>|null */
    public function championshipStatus(Tournament $tournament): ?array
    {
        if (! in_array($this->componentCode($tournament), ['masters', 'queens'], true)) {
            return null;
        }

        $seedError = null;
        try {
            $seedCandidates = $this->directSeedCandidates($tournament);
        } catch (InvalidArgumentException $exception) {
            $seedCandidates = collect();
            $seedError = $exception->getMessage();
        }

        $participants = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->get();
        $participantLookup = $participants
            ->mapWithKeys(fn (object $row): array => [$this->participantIdentity($row) => true])
            ->all();
        $linkedSeeds = $seedCandidates
            ->filter(fn (array $candidate): bool => isset($participantLookup[$candidate['identity']]))
            ->count();
        $prelimSnapshot = $this->currentChampionshipPrelimSnapshot($tournament);
        $qualifierCount = $this->configuredSemifinalQualifierCount($tournament);
        $assignments = DB::table('tournament_round_lane_assignments')
            ->where('tournament_id', $tournament->id)
            ->where('stage', self::SEMIFINAL_STAGE)
            ->where('round_label', self::SEMIFINAL_ROUND_LABEL)
            ->count();

        return [
            'component' => $this->componentCode($tournament),
            'seed_candidate_count' => $seedCandidates->count(),
            'linked_seed_count' => $linkedSeeds,
            'owned_seed_participant_count' => $participants
                ->filter(fn (object $row): bool => str_starts_with((string) $row->source_note, self::DIRECT_SEED_SOURCE_NOTE))
                ->count(),
            'seed_error' => $seedError,
            'prelim_snapshot' => $prelimSnapshot,
            'prelim_row_count' => $prelimSnapshot?->rows()->count() ?? 0,
            'semifinal_qualifier_count' => $qualifierCount,
            'semifinal_assignment_count' => $assignments,
            'last_seed_sync' => (array) data_get($tournament->template_snapshot, 'japan_open.direct_seed_sync', []),
            'last_semifinal_sync' => (array) data_get($tournament->template_snapshot, 'japan_open.semifinal_sync', []),
        ];
    }

    /** @return array<string,mixed> */
    public function syncDirectSeeds(Tournament $tournament, ?int $syncedBy = null): array
    {
        if (! in_array($this->componentCode($tournament), ['masters', 'queens'], true)) {
            throw new InvalidArgumentException('ジャパンオープンのマスターズ／クイーンズで実行してください。');
        }

        $candidates = $this->directSeedCandidates($tournament);

        return DB::transaction(function () use ($tournament, $candidates, $syncedBy): array {
            $participants = DB::table('tournament_participants')
                ->where('tournament_id', $tournament->id)
                ->lockForUpdate()
                ->get();
            $owned = $participants
                ->filter(fn (object $row): bool => str_starts_with((string) $row->source_note, self::DIRECT_SEED_SOURCE_NOTE))
                ->values();
            $ownedByIdentity = $owned->groupBy(fn (object $row): string => $this->participantIdentity($row));
            $allByIdentity = $participants->groupBy(fn (object $row): string => $this->participantIdentity($row));
            $retainedIds = [];
            $created = 0;
            $updated = 0;
            $alreadyPresent = 0;

            foreach ($candidates as $position => $candidate) {
                $identity = $candidate['identity'];
                $matched = $ownedByIdentity->get($identity)?->shift();

                if ($matched) {
                    DB::table('tournament_participants')
                        ->where('id', $matched->id)
                        ->update($this->directSeedParticipantPayload($tournament, $candidate, $position + 1));
                    $retainedIds[] = (int) $matched->id;
                    $updated++;

                    continue;
                }

                $existing = $allByIdentity->get($identity)?->first();
                if ($existing) {
                    $alreadyPresent++;

                    continue;
                }

                $retainedIds[] = (int) DB::table('tournament_participants')->insertGetId(
                    $this->directSeedParticipantPayload($tournament, $candidate, $position + 1) + [
                        'tournament_id' => $tournament->id,
                        'created_at' => now(),
                    ],
                );
                $created++;
            }

            $stale = $owned->whereNotIn('id', $retainedIds)->values();
            $this->guardStaleParticipants($stale);
            if ($stale->isNotEmpty()) {
                DB::table('tournament_participants')->whereIn('id', $stale->pluck('id')->all())->delete();
            }

            $metadata = [
                'candidate_count' => $candidates->count(),
                'created_count' => $created,
                'updated_count' => $updated,
                'already_present_count' => $alreadyPresent,
                'removed_count' => $stale->count(),
                'synced_at' => now()->toIso8601String(),
                'synced_by' => $syncedBy,
            ];
            $settings = (array) ($tournament->template_snapshot ?? []);
            data_set($settings, 'japan_open.direct_seed_sync', $metadata);
            $tournament->template_snapshot = $settings;
            $tournament->save();

            return $metadata;
        });
    }

    /** @return array<string,mixed> */
    public function syncSemifinalists(
        Tournament $tournament,
        ?int $qualifierCount = null,
        ?int $syncedBy = null,
        ?TournamentResultSnapshot $sourceSnapshot = null,
    ): array {
        if (! in_array($this->componentCode($tournament), ['masters', 'queens'], true)) {
            throw new InvalidArgumentException('ジャパンオープンのマスターズ／クイーンズで実行してください。');
        }

        $qualifierCount ??= $this->configuredSemifinalQualifierCount($tournament);
        if ($qualifierCount < 1 || $qualifierCount > 200) {
            throw new InvalidArgumentException('準決勝進出人数は1～200名で指定してください。');
        }

        $sourceSnapshot ??= $this->currentChampionshipPrelimSnapshot($tournament);
        if (! $sourceSnapshot
            || (int) $sourceSnapshot->tournament_id !== (int) $tournament->id
            || (string) $sourceSnapshot->result_code !== 'prelim_total'
            || ! $sourceSnapshot->is_current) {
            throw new InvalidArgumentException('最新の予選8G通算成績を先に反映してください。');
        }
        if ((int) $sourceSnapshot->games_count !== 8) {
            throw new InvalidArgumentException('準決勝進出者の選出元は予選8G通算成績にしてください。');
        }

        $rows = $sourceSnapshot->rows()
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->get();
        $selected = $this->selectWithBoundaryGuard($rows, $qualifierCount, '準決勝');
        if ($selected->contains(fn (TournamentResultSnapshotRow $row): bool => ! $row->is_complete || (int) $row->games !== 8)) {
            throw new InvalidArgumentException('進出圏内に予選8G未完了の選手がいるため、準決勝進出者を確定できません。');
        }

        $resolved = $selected->map(function (TournamentResultSnapshotRow $row) use ($tournament): array {
            $participant = $this->resolveParticipantForSnapshotRow($tournament, $row);
            if (! $participant) {
                throw new InvalidArgumentException(sprintf(
                    '%sさんを大会参加者へ紐づけられません。参加者情報を確認してください。',
                    $row->display_name,
                ));
            }

            return ['row' => $row, 'participant' => $participant];
        });

        return DB::transaction(function () use ($tournament, $sourceSnapshot, $resolved, $qualifierCount, $syncedBy): array {
            $existing = DB::table('tournament_round_lane_assignments')
                ->where('tournament_id', $tournament->id)
                ->where('stage', self::SEMIFINAL_STAGE)
                ->where('round_label', self::SEMIFINAL_ROUND_LABEL)
                ->lockForUpdate()
                ->get();
            $existingByIdentity = $existing->groupBy(fn (object $row): string => $this->assignmentIdentity($row));
            $retainedIds = [];
            $created = 0;
            $updated = 0;

            foreach ($resolved as $position => $item) {
                $row = $item['row'];
                $participant = $item['participant'];
                $identity = $this->assignmentIdentity($participant, true);
                $matched = $existingByIdentity->get($identity)?->shift();
                $payload = $this->semifinalAssignmentPayload(
                    $tournament,
                    $sourceSnapshot,
                    $row,
                    $participant,
                    $position + 1,
                );

                if ($matched) {
                    DB::table('tournament_round_lane_assignments')->where('id', $matched->id)->update($payload);
                    $retainedIds[] = (int) $matched->id;
                    $updated++;
                } else {
                    $retainedIds[] = (int) DB::table('tournament_round_lane_assignments')->insertGetId($payload + [
                        'tournament_id' => $tournament->id,
                        'stage' => self::SEMIFINAL_STAGE,
                        'round_label' => self::SEMIFINAL_ROUND_LABEL,
                        'movement_direction' => 'left',
                        'movement_box_step' => 1,
                        'note' => self::SEMIFINAL_SOURCE_NOTE,
                        'created_at' => now(),
                    ]);
                    $created++;
                }
            }

            $stale = $existing->whereNotIn('id', $retainedIds)->values();
            $this->guardStaleSemifinalAssignments($tournament, $stale);
            if ($stale->isNotEmpty()) {
                DB::table('tournament_round_lane_assignments')->whereIn('id', $stale->pluck('id')->all())->delete();
            }

            $metadata = [
                'source_snapshot_id' => (int) $sourceSnapshot->id,
                'qualifier_count' => $qualifierCount,
                'created_count' => $created,
                'updated_count' => $updated,
                'removed_count' => $stale->count(),
                'stage' => self::SEMIFINAL_STAGE,
                'round_label' => self::SEMIFINAL_ROUND_LABEL,
                'synced_at' => now()->toIso8601String(),
                'synced_by' => $syncedBy,
            ];
            $settings = (array) ($tournament->template_snapshot ?? []);
            data_set($settings, 'japan_open.semifinal_qualifier_count', $qualifierCount);
            data_set($settings, 'japan_open.semifinal_sync', $metadata);
            $tournament->template_snapshot = $settings;
            $tournament->save();

            return $metadata;
        });
    }

    /** @return array<string,mixed> */
    public function sync(
        Tournament $allEventsTournament,
        int $fieldSize,
        ?int $shiftACount = null,
        ?int $shiftBCount = null,
        ?int $syncedBy = null,
    ): array {
        $component = $this->componentCode($allEventsTournament);
        if (! in_array($component, ['men_all_events', 'women_all_events'], true)) {
            throw new InvalidArgumentException('ジャパンオープンの男女オールエベンツで実行してください。');
        }
        if ($fieldSize < 1 || $fieldSize > 500) {
            throw new InvalidArgumentException('予選進出定員は1～500名で指定してください。');
        }

        $target = $this->targetTournament($allEventsTournament);
        if (! $target) {
            throw new InvalidArgumentException('同年度のマスターズ／クイーンズ大会を確認できません。');
        }

        // マスターズ／クイーンズの大会シードを先に参加者へ実体化し、
        // オールエベンツ通過枠から確実に除外する。
        $this->syncDirectSeeds($target, $syncedBy);

        $snapshot = $this->currentSnapshot($allEventsTournament);
        if (! $snapshot) {
            throw new InvalidArgumentException('公開中の最新オールエベンツ9G成績を先に再計算してください。');
        }
        if ((int) $snapshot->games_count !== 9) {
            throw new InvalidArgumentException('進出者の選出元はオールエベンツ9G成績にしてください。');
        }

        $rows = $snapshot->rows()->orderBy('ranking')->orderByDesc('total_pin')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('オールエベンツ成績に選手がいません。');
        }
        if ($rows->contains(fn (TournamentResultSnapshotRow $row): bool => ! $row->is_complete || (int) $row->games !== 9)) {
            throw new InvalidArgumentException('9G未完了の選手がいるため、進出者を確定できません。');
        }

        $reserved = $this->reservedEntries($target);
        $qualifierCount = $fieldSize - (int) $reserved['count'];
        if ($qualifierCount < 0) {
            throw new InvalidArgumentException(sprintf(
                'シード・手動登録が%d名あり、進出定員%d名を超えています。',
                $reserved['count'],
                $fieldSize,
            ));
        }

        $candidates = $rows
            ->reject(fn (TournamentResultSnapshotRow $row): bool => $this->matchesLookup($row, $reserved['lookup']))
            ->values();
        $this->guardCandidateIdentity($candidates);

        if ($component === 'men_all_events') {
            [$shiftACount, $shiftBCount] = $this->resolveShiftCounts(
                $qualifierCount,
                $shiftACount,
                $shiftBCount,
            );
            $selected = $this->selectMen($candidates, $shiftACount, $shiftBCount);
        } else {
            $shiftACount = null;
            $shiftBCount = null;
            $selected = $this->selectWithBoundaryGuard($candidates, $qualifierCount, '女子総合');
        }

        return DB::transaction(function () use (
            $allEventsTournament,
            $target,
            $snapshot,
            $selected,
            $fieldSize,
            $reserved,
            $shiftACount,
            $shiftBCount,
            $component,
            $syncedBy,
        ): array {
            $existing = DB::table('tournament_participants')
                ->where('tournament_id', $target->id)
                ->where('source_note', 'like', self::QUALIFIER_SOURCE_NOTE.'%')
                ->lockForUpdate()
                ->get();
            $existingByIdentity = $existing->groupBy(fn (object $row): string => $this->participantIdentity($row));
            $retainedIds = [];

            foreach ($selected as $position => $row) {
                $identity = $this->snapshotRowIdentity($row);
                $matched = $existingByIdentity->get($identity)?->shift();
                $payload = $this->participantPayload(
                    $target,
                    $snapshot,
                    $row,
                    (int) $reserved['count'] + $position + 1,
                );

                if ($matched) {
                    DB::table('tournament_participants')->where('id', $matched->id)->update($payload);
                    $retainedIds[] = (int) $matched->id;
                } else {
                    $retainedIds[] = (int) DB::table('tournament_participants')->insertGetId($payload + [
                        'tournament_id' => $target->id,
                        'created_at' => now(),
                    ]);
                }
            }

            $stale = $existing->whereNotIn('id', $retainedIds)->values();
            $this->guardStaleParticipants($stale);
            if ($stale->isNotEmpty()) {
                DB::table('tournament_participants')->whereIn('id', $stale->pluck('id')->all())->delete();
            }

            $syncMetadata = [
                'source_snapshot_id' => (int) $snapshot->id,
                'target_tournament_id' => (int) $target->id,
                'field_size' => $fieldSize,
                'reserved_entry_count' => (int) $reserved['count'],
                'qualifier_count' => $selected->count(),
                'selection_mode' => $component === 'men_all_events' ? 'per_shift' : 'overall',
                'shift_a_count' => $shiftACount,
                'shift_b_count' => $shiftBCount,
                'synced_at' => now()->toIso8601String(),
                'synced_by' => $syncedBy,
            ];
            $this->storeConfiguration($allEventsTournament, $target, $fieldSize, $syncMetadata);

            return $syncMetadata + [
                'target_name' => $target->name,
                'created_or_updated_count' => $selected->count(),
                'removed_count' => $stale->count(),
            ];
        });
    }

    /** @return Collection<int,array{row:object,pro:ProBowler,identity:string,label:string,priority:int}> */
    private function directSeedCandidates(Tournament $tournament): Collection
    {
        $expectedSex = $tournament->gender === 'F' ? 2 : 1;

        return collect(array_values($this->seedService->seedMapForTournament((int) $tournament->id)))
            ->unique(function (object $row): string {
                if ($row->pro_bowler_id) {
                    return 'pro:'.(int) $row->pro_bowler_id;
                }

                return 'license:'.$this->normalizeLicense($row->license_no);
            })
            ->map(function (object $row) use ($tournament, $expectedSex): array {
                $license = $this->normalizeLicense($row->license_no);
                $pro = $row->pro_bowler_id
                    ? ProBowler::query()->find((int) $row->pro_bowler_id)
                    : null;
                if (! $pro && $license !== '') {
                    $pro = ProBowler::query()
                        ->whereRaw('UPPER(TRIM(license_no)) = ?', [$license])
                        ->first();
                }
                if (! $pro) {
                    throw new InvalidArgumentException(sprintf(
                        '大会シード（%s）を選手台帳へ紐づけられません。シード設定を確認してください。',
                        $license !== '' ? $license : 'ライセンス番号なし',
                    ));
                }
                if ((int) $pro->sex !== $expectedSex) {
                    throw new InvalidArgumentException(sprintf(
                        '%sさんの性別が大会区分（%s）と一致しません。シード設定を確認してください。',
                        $pro->name_kanji,
                        $tournament->gender === 'F' ? '女子' : '男子',
                    ));
                }

                $priority = (int) ($row->priority_order ?? $row->seed_rank ?? $row->ranking_rank ?? 9999);
                $label = trim((string) ($row->display_label ?? $row->seed_category ?? $row->seed_source_type ?? ''));

                return [
                    'row' => $row,
                    'pro' => $pro,
                    'identity' => 'pro:'.(int) $pro->id,
                    'label' => $label !== '' ? $label : '大会シード',
                    'priority' => $priority > 0 ? $priority : 9999,
                ];
            })
            ->sortBy([['priority', 'asc'], ['pro.license_no', 'asc']])
            ->values();
    }

    /** @param array{row:object,pro:ProBowler,identity:string,label:string,priority:int} $candidate */
    private function directSeedParticipantPayload(Tournament $tournament, array $candidate, int $sortOrder): array
    {
        /** @var ProBowler $pro */
        $pro = $candidate['pro'];
        $license = $this->normalizeLicense($pro->license_no);

        return [
            'pro_bowler_license_no' => $license,
            'pro_bowler_id' => $pro->id,
            'amateur_bowler_id' => null,
            'participant_type' => 'pro',
            'display_name' => $pro->name_kanji,
            'display_license_no' => $license,
            'gender' => $tournament->gender,
            'shift' => null,
            'sort_order' => $sortOrder,
            'source_note' => self::DIRECT_SEED_SOURCE_NOTE.'（'.$candidate['label'].'）',
            'is_temporary' => false,
            'updated_at' => now(),
        ];
    }

    private function currentChampionshipPrelimSnapshot(Tournament $tournament): ?TournamentResultSnapshot
    {
        return TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('result_code', 'prelim_total')
            ->where('is_current', true)
            ->whereNull('shift')
            ->where(function ($query) use ($tournament): void {
                $query->whereNull('gender')->orWhere('gender', $tournament->gender);
            })
            ->orderByRaw('CASE WHEN gender IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->first();
    }

    private function configuredSemifinalQualifierCount(Tournament $tournament): int
    {
        $configured = (int) data_get($tournament->template_snapshot, 'japan_open.semifinal_qualifier_count', 0);
        if ($configured > 0) {
            return $configured;
        }

        return $this->componentCode($tournament) === 'masters' ? 46 : 32;
    }

    private function resolveParticipantForSnapshotRow(Tournament $tournament, TournamentResultSnapshotRow $row): ?object
    {
        $query = DB::table('tournament_participants')->where('tournament_id', $tournament->id);

        if ($row->pro_bowler_id) {
            $participant = (clone $query)->where('pro_bowler_id', $row->pro_bowler_id)->first();
            if ($participant) {
                return $participant;
            }
        }
        if ($row->amateur_bowler_id) {
            $participant = (clone $query)->where('amateur_bowler_id', $row->amateur_bowler_id)->first();
            if ($participant) {
                return $participant;
            }
        }

        $license = $this->normalizeLicense($row->pro_bowler_license_no);
        if ($license !== '') {
            $participant = (clone $query)
                ->whereRaw('UPPER(TRIM(pro_bowler_license_no)) = ?', [$license])
                ->first();
            if ($participant) {
                return $participant;
            }
        }

        $name = $this->normalizeName((string) $row->display_name);
        if ($name === '') {
            return null;
        }

        $matches = (clone $query)
            ->whereRaw("replace(replace(replace(replace(display_name, '　', ''), ' ', ''), '･', ''), '・', '') = ?", [$name])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function semifinalAssignmentPayload(
        Tournament $tournament,
        TournamentResultSnapshot $snapshot,
        TournamentResultSnapshotRow $row,
        object $participant,
        int $sortOrder,
    ): array {
        $pro = $row->pro_bowler_id
            ? ProBowler::query()->find((int) $row->pro_bowler_id)
            : null;
        $license = $this->normalizeLicense($participant->pro_bowler_license_no ?? $row->pro_bowler_license_no);
        $organization = trim((string) ($pro?->organization_name ?? ''));
        $equipment = trim((string) ($pro?->equipment_contract ?? ''));
        $affiliation = implode('/', array_values(array_filter([$organization, $equipment], fn (string $value): bool => $value !== '')));

        return [
            'source_result_snapshot_id' => $snapshot->id,
            'tournament_participant_id' => $participant->id,
            'pro_bowler_id' => $row->pro_bowler_id ?: $participant->pro_bowler_id,
            'pro_bowler_license_no' => $license !== '' ? $license : null,
            'display_license_no' => $row->pro_bowler_id ? $this->shortLicense($license) : 'アマ',
            'display_name' => $row->display_name,
            'period_label' => $pro?->kibetsu !== null ? (string) $pro->kibetsu : null,
            'dominant_arm' => $this->normalizeArmLabel($pro?->dominant_arm),
            'affiliation_display' => $affiliation !== '' ? $affiliation : null,
            'source_total_pin' => (int) $row->total_pin,
            'source_games' => (int) $row->games,
            'source_average' => $row->average !== null ? round((float) $row->average, 3) : null,
            'game_from' => 1,
            'game_to' => 6,
            'seed_rank' => (int) $row->ranking,
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ];
    }

    private function assignmentIdentity(object $row, bool $participant = false): string
    {
        if ($participant) {
            return 'participant:'.(int) $row->id;
        }
        if ($row->tournament_participant_id) {
            return 'participant:'.(int) $row->tournament_participant_id;
        }
        if ($row->pro_bowler_id) {
            return 'pro:'.(int) $row->pro_bowler_id;
        }

        $license = $this->normalizeLicense($row->pro_bowler_license_no);
        if ($license !== '') {
            return 'license:'.$license;
        }

        return 'name:'.$this->normalizeName((string) $row->display_name);
    }

    private function guardStaleSemifinalAssignments(Tournament $tournament, Collection $stale): void
    {
        if ($stale->isEmpty()) {
            return;
        }

        $hasLaneWork = $stale->contains(function (object $row): bool {
            return $row->start_lane !== null
                || $row->lane_slot !== null
                || $row->start_lane_label !== null
                || $row->box_no !== null
                || $row->movement_boxes !== null
                || $row->game_start_time !== null
                || $row->tv_lane_from !== null
                || $row->tv_lane_to !== null;
        });
        $participantIds = $stale->pluck('tournament_participant_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $proIds = $stale->pluck('pro_bowler_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $hasScores = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', self::SEMIFINAL_STAGE)
            ->where(function ($query) use ($participantIds, $proIds): void {
                if ($participantIds !== []) {
                    $query->whereIn('tournament_participant_id', $participantIds);
                }
                if ($proIds !== []) {
                    $participantIds !== []
                        ? $query->orWhereIn('pro_bowler_id', $proIds)
                        : $query->whereIn('pro_bowler_id', $proIds);
                }
            })
            ->exists();

        if ($hasLaneWork || $hasScores) {
            throw new InvalidArgumentException('前回の準決勝進出者にレーン設定または準決勝スコアがあるため再同期できません。大会運用担当者が確認してください。');
        }
    }

    private function shortLicense(string $license): ?string
    {
        if ($license === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $license);
        if ($digits === '') {
            return $license;
        }

        $last = ltrim(substr($digits, -4), '0');

        return $last !== '' ? $last : '0';
    }

    private function normalizeArmLabel(mixed $arm): ?string
    {
        $arm = trim((string) $arm);

        return match ($arm) {
            '' => null,
            'R', '右投げ' => '右',
            'L', '左投げ' => '左',
            default => $arm,
        };
    }

    private function normalizeName(string $name): string
    {
        return str_replace(['　', ' ', '･', '・'], '', trim($name));
    }

    /** @return array{0:int,1:int} */
    private function resolveShiftCounts(int $qualifierCount, ?int $shiftACount, ?int $shiftBCount): array
    {
        if ($shiftACount === null && $shiftBCount === null) {
            if ($qualifierCount % 2 !== 0) {
                throw new InvalidArgumentException('男子の残り進出枠が奇数です。A・B各シフトの進出人数を指定してください。');
            }

            return [intdiv($qualifierCount, 2), intdiv($qualifierCount, 2)];
        }

        $shiftACount = max(0, (int) $shiftACount);
        $shiftBCount = max(0, (int) $shiftBCount);
        if ($shiftACount + $shiftBCount !== $qualifierCount) {
            throw new InvalidArgumentException(sprintf(
                'A・Bシフト進出人数の合計を、シード等を除く残り%d名に合わせてください。',
                $qualifierCount,
            ));
        }

        return [$shiftACount, $shiftBCount];
    }

    private function selectMen(Collection $candidates, int $shiftACount, int $shiftBCount): Collection
    {
        $unknown = $candidates->filter(fn (TournamentResultSnapshotRow $row): bool => $this->normalizeShift($row->shift) === null);
        if ($unknown->isNotEmpty()) {
            throw new InvalidArgumentException('男子進出者はA・Bシフト別に選出します。編成取込へシフト列を設定し、オールエベンツを再計算してください。');
        }

        $selectedA = $this->selectWithBoundaryGuard(
            $candidates->filter(fn (TournamentResultSnapshotRow $row): bool => $this->normalizeShift($row->shift) === 'A')->values(),
            $shiftACount,
            '男子Aシフト',
        );
        $selectedB = $this->selectWithBoundaryGuard(
            $candidates->filter(fn (TournamentResultSnapshotRow $row): bool => $this->normalizeShift($row->shift) === 'B')->values(),
            $shiftBCount,
            '男子Bシフト',
        );

        return $selectedA->concat($selectedB)
            ->sortBy([['total_pin', 'desc'], ['ranking', 'asc'], ['id', 'asc']])
            ->values();
    }

    private function selectWithBoundaryGuard(Collection $candidates, int $limit, string $label): Collection
    {
        $ordered = $candidates->sortBy([['total_pin', 'desc'], ['ranking', 'asc'], ['id', 'asc']])->values();
        if ($ordered->count() < $limit) {
            throw new InvalidArgumentException(sprintf('%sの対象者が不足しています（必要%d名／確認%d名）。', $label, $limit, $ordered->count()));
        }
        if ($limit > 0 && $ordered->count() > $limit) {
            $lastSelected = $ordered[$limit - 1];
            $firstExcluded = $ordered[$limit];
            if ((int) $lastSelected->total_pin === (int) $firstExcluded->total_pin) {
                throw new InvalidArgumentException($label.'の進出境界が同ピンです。正式な順位を確定してから再実行してください。');
            }
        }

        return $ordered->take($limit)->values();
    }

    private function guardCandidateIdentity(Collection $candidates): void
    {
        foreach ($candidates as $row) {
            $verified = (bool) data_get($row->breakdown, 'identity_verified', false);
            $license = strtoupper(trim((string) $row->pro_bowler_license_no));
            if (! $verified && ! str_starts_with($license, 'JO-')) {
                throw new InvalidArgumentException(sprintf(
                    '%sさんの選手識別を確定できません。参加者情報を修正してオールエベンツを再計算してください。',
                    $row->display_name,
                ));
            }
        }
    }

    private function guardStaleParticipants(Collection $stale): void
    {
        if ($stale->isEmpty()) {
            return;
        }

        $ids = $stale->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hasDependentRows = DB::table('game_scores')->whereIn('tournament_participant_id', $ids)->exists()
            || DB::table('tournament_competitor_group_members')->whereIn('tournament_participant_id', $ids)->exists()
            || DB::table('tournament_round_lane_assignments')->whereIn('tournament_participant_id', $ids)->exists();
        if ($hasDependentRows) {
            throw new InvalidArgumentException('前回の進出者に得点またはレーン割当があるため再同期できません。先に大会運用担当者が確認してください。');
        }
    }

    /** @return array{count:int,lookup:array<string,bool>} */
    private function reservedEntries(Tournament $target): array
    {
        $canonical = [];
        $lookup = [];

        foreach (collect($this->seedService->seedMapForTournament((int) $target->id))->unique(function (object $row): string {
            return $row->pro_bowler_id
                ? 'pro:'.(int) $row->pro_bowler_id
                : 'license:'.$this->normalizeLicense($row->license_no);
        }) as $row) {
            $key = $row->pro_bowler_id
                ? 'pro:'.(int) $row->pro_bowler_id
                : 'license:'.$this->normalizeLicense($row->license_no);
            if ($key === 'license:') {
                continue;
            }
            $canonical[$key] = true;
            $lookup[$key] = true;
            if ($row->pro_bowler_id) {
                $lookup['pro:'.(int) $row->pro_bowler_id] = true;
            }
            if ($this->normalizeLicense($row->license_no) !== '') {
                $lookup['license:'.$this->normalizeLicense($row->license_no)] = true;
            }
        }

        $preserved = DB::table('tournament_participants')
            ->where('tournament_id', $target->id)
            ->where(function ($query): void {
                $query->whereNull('source_note')
                    ->orWhere('source_note', 'not like', self::QUALIFIER_SOURCE_NOTE.'%');
            })
            ->get();
        foreach ($preserved as $row) {
            $key = $this->participantIdentity($row);
            $canonical[$key] = true;
            $lookup[$key] = true;
            if ($row->pro_bowler_id) {
                $lookup['pro:'.(int) $row->pro_bowler_id] = true;
            }
            if ($this->normalizeLicense($row->pro_bowler_license_no) !== '') {
                $lookup['license:'.$this->normalizeLicense($row->pro_bowler_license_no)] = true;
            }
        }

        return ['count' => count($canonical), 'lookup' => $lookup];
    }

    private function matchesLookup(TournamentResultSnapshotRow $row, array $lookup): bool
    {
        $keys = [$this->snapshotRowIdentity($row)];
        if ($row->pro_bowler_id) {
            $keys[] = 'pro:'.(int) $row->pro_bowler_id;
        }
        if ($this->normalizeLicense($row->pro_bowler_license_no) !== '') {
            $keys[] = 'license:'.$this->normalizeLicense($row->pro_bowler_license_no);
        }

        return collect($keys)->contains(fn (string $key): bool => isset($lookup[$key]));
    }

    /** @return array<string,mixed> */
    private function participantPayload(
        Tournament $target,
        TournamentResultSnapshot $snapshot,
        TournamentResultSnapshotRow $row,
        int $sortOrder,
    ): array {
        $isProfessional = $row->pro_bowler_id !== null;
        $amateurNo = $row->amateur_bowler_id
            ? DB::table('amateur_bowlers')->where('id', $row->amateur_bowler_id)->value('amateur_no')
            : null;
        $internalLicense = $isProfessional
            ? $this->normalizeLicense($row->pro_bowler_license_no)
            : ($amateurNo ?: $this->normalizeLicense($row->pro_bowler_license_no));
        if ($internalLicense === '') {
            $internalLicense = sprintf('JO-%d-%s-AE-%d', $target->year, $target->gender, $row->id);
        }

        return [
            'pro_bowler_license_no' => $internalLicense,
            'pro_bowler_id' => $row->pro_bowler_id,
            'amateur_bowler_id' => $row->amateur_bowler_id,
            'participant_type' => $isProfessional ? 'pro' : 'amateur',
            'display_name' => $row->display_name,
            'display_license_no' => $isProfessional ? $internalLicense : null,
            'gender' => $target->gender,
            'shift' => $this->normalizeShift($row->shift),
            'sort_order' => $sortOrder,
            'source_note' => self::QUALIFIER_SOURCE_NOTE.'（成績 #'.$snapshot->id.'）',
            'is_temporary' => ! $isProfessional,
            'updated_at' => now(),
        ];
    }

    private function storeConfiguration(
        Tournament $source,
        Tournament $target,
        int $fieldSize,
        array $syncMetadata,
    ): void {
        foreach ([$source, $target] as $tournament) {
            $settings = (array) ($tournament->template_snapshot ?? []);
            data_set($settings, 'japan_open.advancement_field_size', $fieldSize);
            data_set($settings, 'japan_open.advancement_sync', $syncMetadata);
            $tournament->template_snapshot = $settings;
            $tournament->save();
        }
    }

    private function configuredFieldSize(Tournament $source): int
    {
        $configured = (int) data_get($source->template_snapshot, 'japan_open.advancement_field_size', 0);
        if ($configured > 0) {
            return $configured;
        }

        return $this->componentCode($source) === 'men_all_events' ? 125 : 100;
    }

    private function currentSnapshot(Tournament $source): ?TournamentResultSnapshot
    {
        return TournamentResultSnapshot::query()
            ->where('tournament_id', $source->id)
            ->where('result_code', 'aggregate:all_events_9g')
            ->where('is_current', true)
            ->where('is_published', true)
            ->latest('id')
            ->first();
    }

    private function targetTournament(Tournament $source): ?Tournament
    {
        $targetCode = $this->componentCode($source) === 'men_all_events' ? 'masters' : 'queens';

        return Tournament::query()
            ->where('tournament_edition_id', $source->tournament_edition_id)
            ->get()
            ->first(fn (Tournament $row): bool => $this->componentCode($row) === $targetCode);
    }

    private function componentCode(Tournament $tournament): string
    {
        return trim((string) data_get($tournament->template_snapshot, 'japan_open.component_code'));
    }

    private function snapshotRowIdentity(TournamentResultSnapshotRow $row): string
    {
        if ($row->pro_bowler_id) {
            return 'pro:'.(int) $row->pro_bowler_id;
        }
        if ($row->amateur_bowler_id) {
            return 'amateur:'.(int) $row->amateur_bowler_id;
        }
        $license = $this->normalizeLicense($row->pro_bowler_license_no);
        if ($license !== '') {
            return 'license:'.$license;
        }

        return 'snapshot-row:'.(int) $row->id;
    }

    private function participantIdentity(object $row): string
    {
        if ($row->pro_bowler_id) {
            return 'pro:'.(int) $row->pro_bowler_id;
        }
        if ($row->amateur_bowler_id) {
            return 'amateur:'.(int) $row->amateur_bowler_id;
        }
        $license = $this->normalizeLicense($row->pro_bowler_license_no);

        return $license !== '' ? 'license:'.$license : 'participant:'.(int) $row->id;
    }

    private function normalizeLicense(?string $license): string
    {
        return strtoupper(trim((string) $license));
    }

    private function normalizeShift(?string $shift): ?string
    {
        $normalized = strtoupper(trim((string) $shift));
        $normalized = str_replace(['Ａ', 'Ｂ', 'シフト', 'SHIFT'], ['A', 'B', '', ''], $normalized);

        return in_array($normalized, ['A', 'B'], true) ? $normalized : null;
    }
}
