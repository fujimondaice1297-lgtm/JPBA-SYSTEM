<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JapanOpenAdvancementService
{
    public const QUALIFIER_SOURCE_NOTE = 'ジャパンオープン オールエベンツ通過';

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
                $payload = $this->participantPayload($target, $snapshot, $row, $position + 1);

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
