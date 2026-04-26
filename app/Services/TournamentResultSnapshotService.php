<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\ProBowler;
use App\Models\TournamentResult;
use App\Models\TournamentResultSnapshot;
use App\Models\TournamentResultSnapshotRow;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class TournamentResultSnapshotService
{
    private array $proBowlerResolveCache = [];

    /**
     * トータルピン方式の正式成績スナップショットを作成する。
     *
     * definition には最低限、次を渡す想定。
     * - tournament_id
     * - result_code
     * - result_name
     * - result_type (省略時 total_pin)
     * - stage_name (表示用。nullable)
     * - gender (nullable)
     * - shift (nullable)
     * - is_final (bool)
     * - is_published (bool)
     * - reflected_by (nullable)
     * - notes (nullable)
     * - calculation_definition.source_sets
     *   例:
     *   [
     *     ['stage' => '予選', 'game_from' => 1, 'game_to' => 5, 'bucket' => 'scratch'],
     *     ['stage' => '準々決勝', 'game_from' => 1, 'game_to' => 3, 'bucket' => 'carry'],
     *   ]
     */
    public function createTotalPinSnapshot(array $definition): TournamentResultSnapshot
    {
        $normalized = $this->normalizeDefinition($definition);
        $sourceSets = $this->extractSourceSets($normalized['calculation_definition']);

        $rows = $this->collectScoreRows(
            tournamentId: $normalized['tournament_id'],
            gender: $normalized['gender'],
            shift: $normalized['shift'],
            sourceSets: $sourceSets,
        );

        $aggregatedRows = $this->aggregateRows($rows, $sourceSets);
        $rankedRows = $this->rankRows($aggregatedRows);

        return DB::transaction(function () use ($normalized, $rankedRows, $sourceSets) {
            $this->closeCurrentSnapshots(
                tournamentId: $normalized['tournament_id'],
                resultCode: $normalized['result_code'],
                gender: $normalized['gender'],
                shift: $normalized['shift'],
            );

            $snapshot = TournamentResultSnapshot::create([
                'tournament_id' => $normalized['tournament_id'],
                'result_code' => $normalized['result_code'],
                'result_name' => $normalized['result_name'],
                'result_type' => $normalized['result_type'],
                'stage_name' => $normalized['stage_name'],
                'gender' => $normalized['gender'],
                'shift' => $normalized['shift'],
                'games_count' => $this->countGames($sourceSets, ['scratch', 'carry']),
                'carry_game_count' => $this->countGames($sourceSets, ['carry']),
                'carry_stage_names' => $this->collectCarryStageNames($sourceSets),
                'calculation_definition' => $normalized['calculation_definition'],
                'reflected_at' => Carbon::now(),
                'reflected_by' => $normalized['reflected_by'],
                'is_final' => $normalized['is_final'],
                'is_published' => $normalized['is_published'],
                'is_current' => true,
                'notes' => $normalized['notes'],
            ]);

            foreach ($rankedRows as $row) {
                TournamentResultSnapshotRow::create([
                    'snapshot_id' => $snapshot->id,
                    'ranking' => $row['ranking'],
                    'pro_bowler_id' => $row['pro_bowler_id'],
                    'pro_bowler_license_no' => $row['pro_bowler_license_no'],
                    'amateur_name' => $row['amateur_name'],
                    'display_name' => $row['display_name'],
                    'gender' => $row['gender'],
                    'shift' => $row['shift'],
                    'entry_number' => $row['entry_number'],
                    'scratch_pin' => $row['scratch_pin'],
                    'carry_pin' => $row['carry_pin'],
                    'total_pin' => $row['total_pin'],
                    'games' => $row['games'],
                    'average' => $row['average'],
                    'tie_break_value' => $row['tie_break_value'],
                    'points' => null,
                    'prize_money' => null,
                ]);
            }

            $syncedToTournamentResults = false;
            if ($normalized['is_final'] === true) {
                $syncedToTournamentResults = $this->syncFinalSnapshotToTournamentResults($snapshot);
            }

            $snapshot = $snapshot->load(['rows', 'tournament', 'reflectedBy']);
            $snapshot->setAttribute('synced_to_tournament_results', $syncedToTournamentResults);

            return $snapshot;
        });
    }

    private function normalizeDefinition(array $definition): array
    {
        $tournamentId = (int) ($definition['tournament_id'] ?? 0);
        $resultCode = trim((string) ($definition['result_code'] ?? ''));
        $resultName = trim((string) ($definition['result_name'] ?? ''));

        if ($tournamentId <= 0) {
            throw new InvalidArgumentException('tournament_id は必須です。');
        }
        if ($resultCode === '') {
            throw new InvalidArgumentException('result_code は必須です。');
        }
        if ($resultName === '') {
            throw new InvalidArgumentException('result_name は必須です。');
        }

        $gender = trim((string) ($definition['gender'] ?? ''));
        if ($gender === '') {
            $gender = null;
        }

        $shift = trim((string) ($definition['shift'] ?? ''));
        if ($shift === '') {
            $shift = null;
        }

        return [
            'tournament_id' => $tournamentId,
            'result_code' => $resultCode,
            'result_name' => $resultName,
            'result_type' => trim((string) ($definition['result_type'] ?? 'total_pin')) ?: 'total_pin',
            'stage_name' => $this->nullableTrim($definition['stage_name'] ?? null),
            'gender' => $gender,
            'shift' => $shift,
            'reflected_by' => isset($definition['reflected_by']) && $definition['reflected_by'] !== ''
                ? (int) $definition['reflected_by']
                : null,
            'is_final' => (bool) ($definition['is_final'] ?? false),
            'is_published' => (bool) ($definition['is_published'] ?? false),
            'notes' => $this->nullableTrim($definition['notes'] ?? null),
            'calculation_definition' => (array) ($definition['calculation_definition'] ?? []),
        ];
    }

    private function extractSourceSets(array $calculationDefinition): array
    {
        $sourceSets = array_values(array_filter(
            (array) Arr::get($calculationDefinition, 'source_sets', []),
            fn ($set) => is_array($set)
        ));

        if ($sourceSets === []) {
            throw new InvalidArgumentException('calculation_definition.source_sets は必須です。');
        }

        $normalized = [];
        foreach ($sourceSets as $index => $set) {
            $stage = trim((string) ($set['stage'] ?? ''));
            $gameFrom = (int) ($set['game_from'] ?? 0);
            $gameTo = (int) ($set['game_to'] ?? 0);
            $bucket = trim((string) ($set['bucket'] ?? 'scratch')) ?: 'scratch';

            if ($stage === '' || $gameFrom <= 0 || $gameTo <= 0 || $gameTo < $gameFrom) {
                throw new InvalidArgumentException('source_sets[' . $index . '] の stage / game_from / game_to が不正です。');
            }
            if (!in_array($bucket, ['scratch', 'carry'], true)) {
                throw new InvalidArgumentException('source_sets[' . $index . '] の bucket は scratch / carry のみ許可します。');
            }

            $normalized[] = [
                'stage' => $stage,
                'game_from' => $gameFrom,
                'game_to' => $gameTo,
                'bucket' => $bucket,
            ];
        }

        return $normalized;
    }

    private function collectScoreRows(int $tournamentId, ?string $gender, ?string $shift, array $sourceSets)
    {
        return DB::table('game_scores')
            ->select([
                'id',
                'tournament_id',
                'stage',
                'shift',
                'gender',
                'license_number',
                'name',
                'entry_number',
                'game_number',
                'score',
                'pro_bowler_id',
            ])
            ->where('tournament_id', $tournamentId)
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            ->when($shift !== null, fn ($q) => $q->where('shift', $shift))
            ->where(function ($outer) use ($sourceSets) {
                foreach ($sourceSets as $set) {
                    $outer->orWhere(function ($inner) use ($set) {
                        $inner->where('stage', $set['stage'])
                            ->whereBetween('game_number', [$set['game_from'], $set['game_to']]);
                    });
                }
            })
            ->orderBy('stage')
            ->orderBy('game_number')
            ->orderBy('id')
            ->get();
    }

    private function aggregateRows($rows, array $sourceSets): array
    {
        $setMap = $this->buildSetMap($sourceSets);
        $grouped = [];

        foreach ($rows as $row) {
            $sourceKey = $this->buildSourceSetKey((string) $row->stage, (int) $row->game_number);
            $bucket = $setMap[$sourceKey] ?? null;
            if ($bucket === null) {
                continue;
            }

            $groupKey = $this->buildParticipantKey($row);
            if (!isset($grouped[$groupKey])) {
                $identity = $this->resolveParticipantIdentityFromGameScoreRow($row);

                $grouped[$groupKey] = [
                    'pro_bowler_id' => $identity['pro_bowler_id'],
                    'pro_bowler_license_no' => $identity['pro_bowler_license_no'],
                    'amateur_name' => $identity['amateur_name'],
                    'display_name' => $identity['display_name'],
                    'gender' => $this->nullableTrim($row->gender),
                    'shift' => $this->nullableTrim($row->shift),
                    'entry_number' => $this->nullableTrim($row->entry_number),
                    'scratch_pin' => 0,
                    'carry_pin' => 0,
                    'total_pin' => 0,
                    'games' => 0,
                    'scores' => [],
                ];
            }

            $score = (int) ($row->score ?? 0);
            $grouped[$groupKey]['scores'][] = $score;
            $grouped[$groupKey]['games']++;
            $grouped[$groupKey]['total_pin'] += $score;

            if ($bucket === 'carry') {
                $grouped[$groupKey]['carry_pin'] += $score;
            } else {
                $grouped[$groupKey]['scratch_pin'] += $score;
            }
        }

        return array_values(array_map(function (array $entry): array {
            $entry['average'] = $entry['games'] > 0
                ? round($entry['total_pin'] / $entry['games'], 3)
                : null;
            $entry['tie_break_value'] = null;
            unset($entry['scores']);
            return $entry;
        }, $grouped));
    }

    private function rankRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $byTotal = $b['total_pin'] <=> $a['total_pin'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            $byScratch = $b['scratch_pin'] <=> $a['scratch_pin'];
            if ($byScratch !== 0) {
                return $byScratch;
            }

            return strcmp((string) $a['display_name'], (string) $b['display_name']);
        });

        $rank = 1;
        foreach ($rows as $index => &$row) {
            $row['ranking'] = $rank;
            $rank = $index + 2;
        }
        unset($row);

        return $rows;
    }

    private function closeCurrentSnapshots(int $tournamentId, string $resultCode, ?string $gender, ?string $shift): void
    {
        TournamentResultSnapshot::query()
            ->where('tournament_id', $tournamentId)
            ->where('result_code', $resultCode)
            ->when($gender === null,
                fn ($q) => $q->whereNull('gender'),
                fn ($q) => $q->where('gender', $gender)
            )
            ->when($shift === null,
                fn ($q) => $q->whereNull('shift'),
                fn ($q) => $q->where('shift', $shift)
            )
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function buildSetMap(array $sourceSets): array
    {
        $map = [];
        foreach ($sourceSets as $set) {
            for ($game = $set['game_from']; $game <= $set['game_to']; $game++) {
                $map[$this->buildSourceSetKey($set['stage'], $game)] = $set['bucket'];
            }
        }

        return $map;
    }

    private function buildSourceSetKey(string $stage, int $gameNumber): string
    {
        return $stage . '#' . $gameNumber;
    }

    private function buildParticipantKey(object $row): string
    {
        // pro_bowler_id が入っている行と、license_number だけの行が混在しても、
        // 同じ選手を別人扱いしないよう、まずライセンスから正式プロを解決する。
        //
        // 例:
        // - 予選 game_scores: pro_bowler_id = null / license_number = M00001289
        // - 準決勝 game_scores: pro_bowler_id = 123 / license_number = M00001289
        //
        // 従来は license:M00001289 と pro_id:123 に分かれてしまい、
        // carry + scratch が合算されなかった。
        $resolvedBowler = $this->resolveBowlerFromGameScoreRow($row);

        if ($resolvedBowler) {
            return 'pro_id:' . (int) $resolvedBowler->id;
        }

        $license = $this->nullableTrim($row->license_number);
        if ($license !== null) {
            return 'license:' . strtoupper($license);
        }

        $entryNumber = $this->nullableTrim($row->entry_number);
        if ($entryNumber !== null) {
            return 'entry:' . $entryNumber;
        }

        $name = $this->nullableTrim($row->name);
        if ($name !== null) {
            return 'name:' . $name;
        }

        return 'row:' . (string) $row->id;
    }

    private function resolveDisplayName(object $row): string
    {
        $name = $this->nullableTrim($row->name);
        if ($name !== null) {
            return $name;
        }

        $license = $this->nullableTrim($row->license_number);
        if ($license !== null) {
            return $license;
        }

        $entryNumber = $this->nullableTrim($row->entry_number);
        if ($entryNumber !== null) {
            return $entryNumber;
        }

        return 'unknown';
    }


    private function resolveParticipantIdentityFromGameScoreRow(object $row): array
    {
        $resolvedBowler = $this->resolveBowlerFromGameScoreRow($row);
        $displayName = $resolvedBowler?->name_kanji
            ?? $resolvedBowler?->name_kana
            ?? $this->resolveDisplayName($row);

        return [
            'pro_bowler_id' => $resolvedBowler?->id,
            'pro_bowler_license_no' => $resolvedBowler?->license_no ?? $this->nullableTrim($row->license_number),
            'amateur_name' => $resolvedBowler ? null : $this->nullableTrim($displayName),
            'display_name' => $displayName,
        ];
    }

    private function resolveBowlerFromGameScoreRow(object $row): ?ProBowler
    {
        if ($row->pro_bowler_id !== null) {
            $byId = ProBowler::query()->find((int) $row->pro_bowler_id);
            if ($byId) {
                return $byId;
            }
        }

        return $this->resolveBowlerByLicenseTail(
            license: $this->nullableTrim($row->license_number),
            gender: $this->nullableTrim($row->gender),
        );
    }

    private function resolveBowlerFromSnapshotRow(object $row): ?ProBowler
    {
        if ($row->pro_bowler_id !== null) {
            $byId = ProBowler::query()->find((int) $row->pro_bowler_id);
            if ($byId) {
                return $byId;
            }
        }

        return $this->resolveBowlerByLicenseTail(
            license: $this->nullableTrim($row->pro_bowler_license_no),
            gender: $this->nullableTrim($row->gender),
        );
    }

    private function resolveBowlerByLicenseTail(?string $license, ?string $gender): ?ProBowler
    {
        if ($license === null) {
            return null;
        }

        $normalizedLicense = strtoupper(trim($license));
        $gender = strtoupper(trim((string) $gender));
        $last4 = $this->extractLast4Digits($normalizedLicense);
        if ($last4 === null) {
            return null;
        }

        $cacheKey = ($gender !== '' ? $gender : '*') . ':' . $last4 . ':' . $normalizedLicense;
        if (array_key_exists($cacheKey, $this->proBowlerResolveCache)) {
            return $this->proBowlerResolveCache[$cacheKey];
        }

        $exact = ProBowler::query()
            ->whereRaw('upper(license_no) = ?', [$normalizedLicense])
            ->first();
        if ($exact) {
            return $this->proBowlerResolveCache[$cacheKey] = $exact;
        }

        $query = ProBowler::query()
            ->whereRaw("right(regexp_replace(upper(license_no), '[^0-9]', '', 'g'), 4) = ?", [$last4]);

        if (in_array($gender, ['M', 'F'], true)) {
            $query->whereRaw('upper(left(license_no, 1)) = ?', [$gender]);
        }

        $candidates = $query->orderBy('id')->get();
        if ($candidates->count() === 1) {
            return $this->proBowlerResolveCache[$cacheKey] = $candidates->first();
        }

        return $this->proBowlerResolveCache[$cacheKey] = null;
    }

    private function extractLast4Digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === null || $digits === '') {
            return null;
        }

        $last4 = substr($digits, -4);
        if ($last4 === false || $last4 === '') {
            return null;
        }

        return str_pad($last4, 4, '0', STR_PAD_LEFT);
    }

    private function countGames(array $sourceSets, array $buckets): int
    {
        $count = 0;
        foreach ($sourceSets as $set) {
            if (!in_array($set['bucket'], $buckets, true)) {
                continue;
            }
            $count += ($set['game_to'] - $set['game_from'] + 1);
        }

        return $count;
    }

    private function collectCarryStageNames(array $sourceSets): array
    {
        $names = [];
        foreach ($sourceSets as $set) {
            if ($set['bucket'] !== 'carry') {
                continue;
            }
            $names[] = $set['stage'];
        }

        return array_values(array_unique($names));
    }


    private function syncFinalSnapshotToTournamentResults(TournamentResultSnapshot $snapshot): bool
    {
        if ($snapshot->gender !== null || $snapshot->shift !== null) {
            return false;
        }

        $tournament = Tournament::query()->find($snapshot->tournament_id);
        if (!$tournament) {
            return false;
        }

        $rankingYear = $this->resolveRankingYear($tournament);
        $pointMap = $this->loadPointMap((int) $snapshot->tournament_id);
        $prizeMap = $this->loadPrizeMap((int) $snapshot->tournament_id);

        TournamentResult::query()
            ->where('tournament_id', $snapshot->tournament_id)
            ->delete();

        $hasProBowlerId = Schema::hasColumn('tournament_results', 'pro_bowler_id');
        $hasLicenseNo = Schema::hasColumn('tournament_results', 'pro_bowler_license_no');
        $hasPoints = Schema::hasColumn('tournament_results', 'points');
        $hasPrizeMoney = Schema::hasColumn('tournament_results', 'prize_money');
        $hasAmateurName = Schema::hasColumn('tournament_results', 'amateur_name');

        $rows = $snapshot->rows()->orderBy('ranking')->orderBy('id')->get();

        foreach ($rows as $row) {
            $resolvedBowler = $this->resolveBowlerFromSnapshotRow($row);
            $resolvedProBowlerId = $resolvedBowler?->id ?? ($row->pro_bowler_id !== null ? (int) $row->pro_bowler_id : null);
            $resolvedLicenseNo = $resolvedBowler?->license_no ?? $this->nullableTrim($row->pro_bowler_license_no);
            $resolvedDisplayName = $resolvedBowler?->name_kanji
                ?? $resolvedBowler?->name_kana
                ?? $this->nullableTrim($row->display_name)
                ?? $this->nullableTrim($row->amateur_name);

            $points = $resolvedProBowlerId !== null ? (int) ($pointMap[$row->ranking] ?? 0) : 0;
            $prizeMoney = $resolvedProBowlerId !== null ? (int) ($prizeMap[$row->ranking] ?? 0) : 0;

            $payload = [
                'tournament_id' => (int) $snapshot->tournament_id,
                'ranking_year' => $rankingYear,
                'ranking' => (int) $row->ranking,
                'total_pin' => (int) $row->total_pin,
                'games' => (int) $row->games,
                'average' => $row->average !== null ? (float) $row->average : null,
            ];

            if ($hasLicenseNo) {
                $payload['pro_bowler_license_no'] = $resolvedLicenseNo;
            }
            if ($hasProBowlerId) {
                $payload['pro_bowler_id'] = $resolvedProBowlerId;
            }
            if ($hasPoints) {
                $payload['points'] = $points;
            }
            if ($hasPrizeMoney) {
                $payload['prize_money'] = $prizeMoney;
            }
            if ($hasAmateurName) {
                $payload['amateur_name'] = $resolvedProBowlerId === null ? $resolvedDisplayName : null;
            }

            TournamentResult::query()->create($payload);

            $row->forceFill([
                'pro_bowler_id' => $resolvedProBowlerId,
                'pro_bowler_license_no' => $resolvedLicenseNo,
                'display_name' => $resolvedDisplayName,
                'amateur_name' => $resolvedProBowlerId === null ? $resolvedDisplayName : null,
                'points' => $points,
                'prize_money' => $prizeMoney,
            ])->save();
        }

        return true;
    }

    private function resolveRankingYear(Tournament $tournament): int
    {
        if (!empty($tournament->year)) {
            return (int) $tournament->year;
        }

        if (!empty($tournament->start_date)) {
            try {
                return Carbon::parse($tournament->start_date)->year;
            } catch (\Throwable) {
                // no-op
            }
        }

        return (int) now()->year;
    }

    /**
     * @return array<int,int>
     */
    private function loadPointMap(int $tournamentId): array
    {
        return DB::table('point_distributions')
            ->where('tournament_id', $tournamentId)
            ->pluck('points', 'rank')
            ->mapWithKeys(fn ($points, $rank) => [(int) $rank => (int) $points])
            ->all();
    }

    /**
     * @return array<int,int>
     */
    private function loadPrizeMap(int $tournamentId): array
    {
        return DB::table('prize_distributions')
            ->where('tournament_id', $tournamentId)
            ->pluck('amount', 'rank')
            ->mapWithKeys(fn ($amount, $rank) => [(int) $rank => (int) $amount])
            ->all();
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
