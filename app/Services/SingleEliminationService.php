<?php

namespace App\Services;

use InvalidArgumentException;

class SingleEliminationService
{
    public const TYPE = 'single_elimination';

    public const RANKING_POLICY_SAME_LOST_ROUND = 'same_lost_round_shared_rank';

    /**
     * トーナメント表の骨格を作る。
     *
     * @param int $qualifierCount 進出人数
     * @param string|null $seedPolicy standard / higher_seed_bye / custom
     * @param array|null $seedSettings single_elimination_seed_settings
     * @param array $seedEntries seedごとの表示情報。例: [['seed' => 1, 'display_name' => '山田太郎']]
     */
    public function buildBracket(
        int $qualifierCount,
        ?string $seedPolicy = 'standard',
        ?array $seedSettings = null,
        array $seedEntries = []
    ): array {
        if ($qualifierCount < 2) {
            throw new InvalidArgumentException('トーナメント進出人数は2人以上で指定してください。');
        }

        if ($qualifierCount > 64) {
            throw new InvalidArgumentException('トーナメント進出人数は64人以内で指定してください。');
        }

        $seedPolicy = $seedPolicy ?: 'standard';
        $seedSettings = $this->normalizeSeedSettings($seedSettings);
        $bracketSize = $this->nextPowerOfTwo($qualifierCount);
        $roundCount = (int) log($bracketSize, 2);
        $byeCount = $bracketSize - $qualifierCount;

        $entriesBySeed = $this->normalizeSeedEntries($seedEntries);
        $seedNodes = $this->buildSeedNodes($qualifierCount, $entriesBySeed);

        $seedNodes = $this->applyDefaultByes(
            seedNodes: $seedNodes,
            byeCount: $byeCount,
            seedPolicy: $seedPolicy
        );

        $seedNodes = $this->applySeedOverrides(
            seedNodes: $seedNodes,
            seedSettings: $seedSettings,
            roundCount: $roundCount
        );

        return $this->buildRounds(
            seedNodes: $seedNodes,
            qualifierCount: $qualifierCount,
            bracketSize: $bracketSize,
            roundCount: $roundCount,
            byeCount: $byeCount,
            seedPolicy: $seedPolicy,
            seedSettings: $seedSettings
        );
    }

    /**
     * snapshot反映時に calculation_definition へ保存する前提の定義を作る。
     */
    public function buildCalculationDefinition(
        int $qualifierCount,
        string $seedSourceResultCode,
        ?int $seedSnapshotId,
        ?string $seedPolicy = 'standard',
        ?array $seedSettings = null
    ): array {
        $bracket = $this->buildBracket(
            qualifierCount: $qualifierCount,
            seedPolicy: $seedPolicy,
            seedSettings: $seedSettings
        );

        return [
            'type' => self::TYPE,
            'seed_source_result_code' => $seedSourceResultCode,
            'seed_snapshot_id' => $seedSnapshotId,
            'qualifier_count' => $qualifierCount,
            'bracket_size' => $bracket['bracket_size'],
            'round_count' => $bracket['round_count'],
            'bye_count' => $bracket['bye_count'],
            'seed_policy' => $bracket['seed_policy'],
            'seed_settings' => $bracket['seed_settings'],
            'ranking_policy' => self::RANKING_POLICY_SAME_LOST_ROUND,
            'rounds' => array_map(function (array $round): array {
                return [
                    'round_no' => $round['round_no'],
                    'round_name' => $round['round_name'],
                    'loser_rank' => $round['loser_rank'],
                    'match_count' => count($round['matches']),
                ];
            }, $bracket['rounds']),
        ];
    }

    private function buildRounds(
        array $seedNodes,
        int $qualifierCount,
        int $bracketSize,
        int $roundCount,
        int $byeCount,
        string $seedPolicy,
        array $seedSettings
    ): array {
        $waitingByRound = [];

        foreach ($seedNodes as $node) {
            $entryRound = max(1, min((int) $node['entry_round'], $roundCount));
            $waitingByRound[$entryRound][] = $node;
        }

        $rounds = [];
        $carryNodes = [];
        $actualMatchCount = 0;
        $autoAdvanceCount = 0;

        for ($roundNo = 1; $roundNo <= $roundCount; $roundNo++) {
            $activeNodes = array_merge(
                $carryNodes,
                $waitingByRound[$roundNo] ?? []
            );

            $activeNodes = $this->sortNodesForPairing($activeNodes);

            if (count($activeNodes) % 2 === 1) {
                $activeNodes[] = $this->makeByeNode();
            }

            $matches = [];
            $nextCarryNodes = [];
            $matchNo = 1;

            while (!empty($activeNodes)) {
                $slotA = array_shift($activeNodes);
                $slotB = array_pop($activeNodes);

                if ($slotB === null) {
                    $slotB = $this->makeByeNode();
                }

                $isBye = $slotA['type'] === 'bye' || $slotB['type'] === 'bye';
                $isFinal = $roundNo === $roundCount;

                if ($isBye) {
                    $autoAdvanceCount++;
                } else {
                    $actualMatchCount++;
                }

                $match = [
                    'round_no' => $roundNo,
                    'round_name' => $this->roundName($roundNo, $roundCount),
                    'match_no' => $matchNo,
                    'match_key' => 'R' . $roundNo . '-M' . $matchNo,
                    'label' => $this->roundName($roundNo, $roundCount) . ' 第' . $matchNo . '試合',
                    'slot_a' => $slotA,
                    'slot_b' => $slotB,
                    'is_bye' => $isBye,
                    'loser_rank' => $this->loserRank($roundNo, $roundCount),
                    'winner_to' => $isFinal ? null : 'R' . ($roundNo + 1),
                ];

                $matches[] = $match;

                if (!$isFinal) {
                    $nextCarryNodes[] = $this->makeWinnerNode($match);
                }

                $matchNo++;
            }

            $rounds[] = [
                'round_no' => $roundNo,
                'round_name' => $this->roundName($roundNo, $roundCount),
                'loser_rank' => $this->loserRank($roundNo, $roundCount),
                'matches' => $matches,
            ];

            $carryNodes = $nextCarryNodes;
        }

        return [
            'type' => self::TYPE,
            'qualifier_count' => $qualifierCount,
            'bracket_size' => $bracketSize,
            'round_count' => $roundCount,
            'bye_count' => $byeCount,
            'seed_policy' => $seedPolicy,
            'seed_settings' => $seedSettings,
            'ranking_policy' => self::RANKING_POLICY_SAME_LOST_ROUND,
            'summary' => [
                'qualifier_count' => $qualifierCount,
                'bracket_size' => $bracketSize,
                'round_count' => $roundCount,
                'bye_count' => $byeCount,
                'actual_match_count' => $actualMatchCount,
                'auto_advance_count' => $autoAdvanceCount,
            ],
            'rounds' => $rounds,
        ];
    }

    private function buildSeedNodes(int $qualifierCount, array $entriesBySeed): array
    {
        $nodes = [];

        for ($seed = 1; $seed <= $qualifierCount; $seed++) {
            $entry = $entriesBySeed[$seed] ?? [];

            $nodes[] = [
                'type' => 'seed',
                'seed' => $seed,
                'entry_round' => 1,
                'label' => 'seed' . $seed,
                'display_name' => $entry['display_name'] ?? ('seed' . $seed),
                'pro_bowler_id' => $entry['pro_bowler_id'] ?? null,
                'pro_bowler_license_no' => $entry['pro_bowler_license_no'] ?? null,
                'amateur_name' => $entry['amateur_name'] ?? null,
                'source_row_id' => $entry['source_row_id'] ?? null,
                'participant_key' => $entry['participant_key'] ?? null,
                'source_ranking' => $entry['source_ranking'] ?? null,
                'total_pin' => $entry['total_pin'] ?? null,
                'games' => $entry['games'] ?? null,
                'average' => $entry['average'] ?? null,
                'min_seed' => $seed,
                'max_seed' => $seed,
            ];
        }

        return $nodes;
    }

    private function applyDefaultByes(array $seedNodes, int $byeCount, string $seedPolicy): array
    {
        if ($byeCount <= 0) {
            return $seedNodes;
        }

        // standard / higher_seed_bye は、どちらもまず上位seedへBYEを与える。
        // custom の場合も、未指定分のBYEは上位seed優先にする。
        foreach ($seedNodes as &$node) {
            if (($node['seed'] ?? 0) <= $byeCount) {
                $node['entry_round'] = 2;
            }
        }

        unset($node);

        return $seedNodes;
    }

    private function applySeedOverrides(array $seedNodes, array $seedSettings, int $roundCount): array
    {
        $overrides = $seedSettings['seed_overrides'] ?? [];

        if (!is_array($overrides)) {
            return $seedNodes;
        }

        $entryRoundBySeed = [];

        foreach ($overrides as $override) {
            if (!is_array($override)) {
                continue;
            }

            $seed = (int) ($override['seed'] ?? 0);
            $entryRound = (int) ($override['entry_round'] ?? 1);

            if ($seed <= 0) {
                continue;
            }

            $entryRoundBySeed[$seed] = max(1, min($entryRound, $roundCount));
        }

        foreach ($seedNodes as &$node) {
            $seed = (int) ($node['seed'] ?? 0);

            if (isset($entryRoundBySeed[$seed])) {
                $node['entry_round'] = $entryRoundBySeed[$seed];
            }
        }

        unset($node);

        return $seedNodes;
    }

    private function normalizeSeedSettings(?array $seedSettings): array
    {
        if (empty($seedSettings)) {
            return [];
        }

        return $seedSettings;
    }

    private function normalizeSeedEntries(array $seedEntries): array
    {
        $normalized = [];

        foreach ($seedEntries as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $seed = (int) ($entry['seed'] ?? (is_int($key) ? $key : 0));

            if ($seed <= 0) {
                continue;
            }

            $normalized[$seed] = $entry;
        }

        return $normalized;
    }

    private function sortNodesForPairing(array $nodes): array
    {
        usort($nodes, function (array $a, array $b): int {
            $aSeed = $this->nodeMinSeed($a);
            $bSeed = $this->nodeMinSeed($b);

            if ($aSeed === $bSeed) {
                return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
            }

            return $aSeed <=> $bSeed;
        });

        return $nodes;
    }

    private function makeWinnerNode(array $match): array
    {
        $slotA = $match['slot_a'];
        $slotB = $match['slot_b'];

        return [
            'type' => 'winner',
            'source_round_no' => $match['round_no'],
            'source_match_no' => $match['match_no'],
            'source_match_key' => $match['match_key'],
            'label' => $match['label'] . ' 勝者',
            'display_name' => $match['label'] . ' 勝者',
            'min_seed' => min($this->nodeMinSeed($slotA), $this->nodeMinSeed($slotB)),
            'max_seed' => max($this->nodeMaxSeed($slotA), $this->nodeMaxSeed($slotB)),
        ];
    }

    private function makeByeNode(): array
    {
        return [
            'type' => 'bye',
            'label' => 'BYE',
            'display_name' => 'BYE',
            'min_seed' => 999999,
            'max_seed' => 999999,
        ];
    }

    private function nodeMinSeed(array $node): int
    {
        return (int) ($node['min_seed'] ?? $node['seed'] ?? 999999);
    }

    private function nodeMaxSeed(array $node): int
    {
        return (int) ($node['max_seed'] ?? $node['seed'] ?? 999999);
    }

    private function nextPowerOfTwo(int $number): int
    {
        $power = 1;

        while ($power < $number) {
            $power *= 2;
        }

        return $power;
    }

    private function roundName(int $roundNo, int $roundCount): string
    {
        if ($roundNo === $roundCount) {
            return '決勝';
        }

        if ($roundNo === $roundCount - 1) {
            return '準決勝';
        }

        if ($roundNo === $roundCount - 2) {
            return '準々決勝';
        }

        return $roundNo . '回戦';
    }

    private function loserRank(int $roundNo, int $roundCount): int
    {
        if ($roundNo === $roundCount) {
            return 2;
        }

        return (2 ** ($roundCount - $roundNo)) + 1;
    }
}