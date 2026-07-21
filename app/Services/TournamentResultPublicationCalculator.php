<?php

namespace App\Services;

final class TournamentResultPublicationCalculator
{
    public const SEASON_TRIAL_AWARD_POINTS = [
        1 => 50,
        2 => 40,
        3 => 35,
        4 => 30,
        5 => 25,
        6 => 23,
        7 => 20,
        8 => 18,
    ];

    /**
     * @param  array<int,array{snapshot_id:int,result_code:string,rows:array<int,array<string,mixed>>}>  $snapshotGroups
     * @param  array<int,int>  $pointMap
     * @param  array<int,int>  $prizeMap
     * @param  array<string,mixed>  $options
     * @return array<int,array<string,mixed>>
     */
    public function build(
        array $snapshotGroups,
        array $pointMap,
        array $prizeMap,
        array $options = [],
    ): array {
        $merged = [];
        $seen = [];
        $nextRank = 1;

        foreach ($snapshotGroups as $group) {
            $previousSourceRank = null;
            $previousAssignedRank = null;
            $rows = array_values($group['rows'] ?? []);
            usort($rows, fn (array $a, array $b): int => ((int) ($a['ranking'] ?? 0) <=> (int) ($b['ranking'] ?? 0))
                ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0)));

            foreach ($rows as $row) {
                $identityKey = $this->identityKey($row);
                if ($identityKey === '' || isset($seen[$identityKey])) {
                    continue;
                }

                $sourceRank = max(1, (int) ($row['ranking'] ?? $nextRank));
                $assignedRank = $previousSourceRank === $sourceRank && $previousAssignedRank !== null
                    ? $previousAssignedRank
                    : max($sourceRank, $nextRank);
                $row['ranking'] = $assignedRank;
                $row['identity_key'] = $identityKey;
                $row['source_snapshot_id'] = (int) ($group['snapshot_id'] ?? 0) ?: null;
                $row['source_snapshot_row_id'] = (int) ($row['id'] ?? 0) ?: null;
                $row['source_result_code'] = (string) ($group['result_code'] ?? '');

                $merged[] = $row;
                $seen[$identityKey] = true;
                $previousSourceRank = $sourceRank;
                $previousAssignedRank = $assignedRank;
                $nextRank = max($nextRank, $assignedRank + 1);
            }
        }

        $isSeasonTrial = (bool) ($options['is_season_trial'] ?? false);
        $countsForPoints = (bool) ($options['counts_for_points'] ?? true);
        $countsForPrize = (bool) ($options['counts_for_prize'] ?? true);
        $semifinalQualifierCount = max(0, (int) ($options['semifinal_qualifier_count'] ?? 0));
        $useSnapshotPoints = (bool) ($options['use_snapshot_points'] ?? false);
        $useSnapshotPrize = (bool) ($options['use_snapshot_prize'] ?? false);

        foreach ($merged as &$row) {
            $rank = (int) $row['ranking'];
            $isPro = (int) ($row['pro_bowler_id'] ?? 0) > 0;
            $awardPoints = 0;
            $stepPoints = 0;
            $points = 0;

            if ($isPro && $countsForPoints) {
                if ($isSeasonTrial) {
                    $awardPoints = self::SEASON_TRIAL_AWARD_POINTS[$rank] ?? 0;
                    $stepPoints = $semifinalQualifierCount > 0 && $rank <= $semifinalQualifierCount
                        ? $semifinalQualifierCount - $rank + 1
                        : 0;
                    $points = $awardPoints + $stepPoints;
                } elseif (array_key_exists($rank, $pointMap)) {
                    $points = (int) $pointMap[$rank];
                } elseif ($useSnapshotPoints) {
                    $points = (int) ($row['points'] ?? 0);
                }
            }

            $prizeMoney = 0;
            if ($isPro && $countsForPrize) {
                if (array_key_exists($rank, $prizeMap)) {
                    $prizeMoney = (int) $prizeMap[$rank];
                } elseif ($useSnapshotPrize) {
                    $prizeMoney = (int) ($row['prize_money'] ?? 0);
                }
            }

            $row['points'] = $points;
            $row['award_points'] = $awardPoints;
            $row['step_points'] = $stepPoints;
            $row['prize_money'] = $prizeMoney;
        }
        unset($row);

        return $merged;
    }

    /** @param array<string,mixed> $row */
    public function identityKey(array $row): string
    {
        $proBowlerId = (int) ($row['pro_bowler_id'] ?? 0);
        if ($proBowlerId > 0) {
            return 'pro:'.$proBowlerId;
        }

        $amateurBowlerId = (int) ($row['amateur_bowler_id'] ?? 0);
        if ($amateurBowlerId > 0) {
            return 'amateur:'.$amateurBowlerId;
        }

        $existingKey = trim((string) ($row['identity_key'] ?? ''));
        if ($existingKey !== '') {
            return $existingKey;
        }

        $license = $this->normalizeLicense($row['pro_bowler_license_no'] ?? null);
        if ($license !== '' && $license !== 'アマ' && ! str_starts_with($license, 'AMATEUR-')) {
            return 'license:'.$license;
        }

        $name = trim((string) ($row['display_name'] ?? $row['amateur_name'] ?? ''));
        $name = mb_strtolower(preg_replace('/[\s　]+/u', '', $name) ?? '');
        if ($name === '') {
            return '';
        }

        return 'name:'.$name.':'.strtoupper(trim((string) ($row['gender'] ?? 'X')));
    }

    private function normalizeLicense(mixed $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $value)) ?? '');
    }
}
