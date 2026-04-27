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
     * 保存済みのトーナメントスコアをブラケットへ反映し、勝者を次ラウンドへ進める。
     *
     * matchScores 形式:
     * [
     *   'R1-M1' => [
     *      'A' => ['score' => 210],
     *      'B' => ['score' => 198],
     *   ],
     * ]
     */
    public function applyMatchScores(array $bracket, array $matchScores): array
    {
        $winnerByMatch = [];

        foreach ($bracket['rounds'] as &$round) {
            foreach ($round['matches'] as &$match) {
                $matchKey = (string) ($match['match_key'] ?? '');

                $match['slot_a'] = $this->resolveWinnerSlot(
                    slot: (array) ($match['slot_a'] ?? []),
                    winnerByMatch: $winnerByMatch
                );

                $match['slot_b'] = $this->resolveWinnerSlot(
                    slot: (array) ($match['slot_b'] ?? []),
                    winnerByMatch: $winnerByMatch
                );

                $slotA = (array) ($match['slot_a'] ?? []);
                $slotB = (array) ($match['slot_b'] ?? []);

                $scoreA = $this->nullableScore($matchScores[$matchKey]['A']['score'] ?? null);
                $scoreB = $this->nullableScore($matchScores[$matchKey]['B']['score'] ?? null);

                $match['score_a'] = $scoreA;
                $match['score_b'] = $scoreB;
                $match['score_rows'] = $matchScores[$matchKey] ?? [];
                $match['is_complete'] = false;
                $match['is_tied'] = false;
                $match['winner_slot'] = null;
                $match['winner_node'] = null;
                $match['loser_node'] = null;

                $slotAType = (string) ($slotA['type'] ?? '');
                $slotBType = (string) ($slotB['type'] ?? '');

                if ($slotAType === 'bye' && $slotBType !== 'bye') {
                    $match['is_complete'] = true;
                    $match['winner_slot'] = 'B';
                    $match['winner_node'] = $slotB;
                    $winnerByMatch[$matchKey] = $this->makeAdvancedNode($slotB, $match);
                    continue;
                }

                if ($slotBType === 'bye' && $slotAType !== 'bye') {
                    $match['is_complete'] = true;
                    $match['winner_slot'] = 'A';
                    $match['winner_node'] = $slotA;
                    $winnerByMatch[$matchKey] = $this->makeAdvancedNode($slotA, $match);
                    continue;
                }

                if (!$this->isPlayableSlot($slotA) || !$this->isPlayableSlot($slotB)) {
                    continue;
                }

                if ($scoreA === null || $scoreB === null) {
                    continue;
                }

                if ($scoreA === $scoreB) {
                    $match['is_tied'] = true;
                    continue;
                }

                $winnerSlot = $scoreA > $scoreB ? 'A' : 'B';
                $winnerNode = $winnerSlot === 'A' ? $slotA : $slotB;
                $loserNode = $winnerSlot === 'A' ? $slotB : $slotA;

                $match['is_complete'] = true;
                $match['winner_slot'] = $winnerSlot;
                $match['winner_node'] = $winnerNode;
                $match['loser_node'] = $loserNode;

                $winnerByMatch[$matchKey] = $this->makeAdvancedNode($winnerNode, $match);
            }

            unset($match);
        }

        unset($round);

        $bracket['winners_by_match'] = $winnerByMatch;

        return $bracket;
    }

    private function resolveWinnerSlot(array $slot, array $winnerByMatch): array
    {
        if (($slot['type'] ?? '') !== 'winner') {
            return $slot;
        }

        $sourceMatchKey = (string) ($slot['source_match_key'] ?? '');

        if ($sourceMatchKey !== '' && isset($winnerByMatch[$sourceMatchKey])) {
            return $winnerByMatch[$sourceMatchKey];
        }

        return $slot;
    }

    private function nullableScore($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(300, (int) $value));
    }

    private function isPlayableSlot(array $slot): bool
    {
        return in_array((string) ($slot['type'] ?? ''), ['seed', 'advanced'], true);
    }

    private function makeAdvancedNode(array $winnerNode, array $match): array
    {
        $advanced = $winnerNode;
        $advanced['type'] = 'advanced';
        $advanced['advanced_from_match_key'] = $match['match_key'] ?? null;
        $advanced['advanced_from_round_no'] = $match['round_no'] ?? null;
        $advanced['advanced_from_match_no'] = $match['match_no'] ?? null;
        $advanced['label'] = $winnerNode['display_name'] ?? $winnerNode['label'] ?? '勝者';
        $advanced['display_name'] = $winnerNode['display_name'] ?? $winnerNode['label'] ?? '勝者';

        return $advanced;
    }

    /**
     * トーナメント完了後の正式順位行を作る。
     *
     * 順位ルール:
     * - 決勝勝者 = 1位
     * - 決勝敗者 = 2位
     * - 準決勝敗者 = 3位タイ
     * - 準々決勝敗者 = 5位タイ
     * - 1回戦敗者 = 9位タイ
     */
    public function buildFinalStandingRows(array $bracket): array
    {
        $rounds = array_values((array) ($bracket['rounds'] ?? []));
        $roundCount = (int) ($bracket['summary']['round_count'] ?? count($rounds));

        if ($roundCount <= 0 || empty($rounds)) {
            throw new InvalidArgumentException('トーナメント表が作成されていません。');
        }

        $nodesByKey = [];
        $scoreStatsByKey = [];
        $rankByKey = [];

        foreach ($rounds as $round) {
            $round = (array) $round;
            $roundNo = (int) ($round['round_no'] ?? 0);
            $matches = array_values((array) ($round['matches'] ?? []));

            foreach ($matches as $match) {
                $match = (array) $match;
                $slotA = (array) ($match['slot_a'] ?? []);
                $slotB = (array) ($match['slot_b'] ?? []);
                $matchKey = (string) ($match['match_key'] ?? '');

                $this->rememberStandingNode($nodesByKey, $slotA);
                $this->rememberStandingNode($nodesByKey, $slotB);

                if ((bool) ($match['is_bye'] ?? false)) {
                    continue;
                }

                if ($matchKey === '') {
                    throw new InvalidArgumentException('トーナメント試合キーが見つかりません。');
                }

                if (!empty($match['is_tied'])) {
                    throw new InvalidArgumentException($matchKey . ' が同点です。タイブレーク後のスコアに修正してください。');
                }

                if (empty($match['is_complete']) || empty($match['winner_node'])) {
                    throw new InvalidArgumentException($matchKey . ' が未確定です。全試合のスコアを入力してください。');
                }

                $scoreA = $this->nullableScore($match['score_a'] ?? null);
                $scoreB = $this->nullableScore($match['score_b'] ?? null);

                if ($scoreA === null || $scoreB === null) {
                    throw new InvalidArgumentException($matchKey . ' のスコアが未入力です。');
                }

                $this->addStandingScore($nodesByKey, $scoreStatsByKey, $slotA, $scoreA);
                $this->addStandingScore($nodesByKey, $scoreStatsByKey, $slotB, $scoreB);

                $winnerNode = (array) ($match['winner_node'] ?? []);
                $loserNode = (array) ($match['loser_node'] ?? []);
                $loserRank = (int) ($match['loser_rank'] ?? 0);

                $this->rememberStandingNode($nodesByKey, $winnerNode);
                $this->rememberStandingNode($nodesByKey, $loserNode);

                $loserKey = $this->standingNodeKey($loserNode);
                if ($loserKey !== '' && $loserRank > 0) {
                    $rankByKey[$loserKey] = $loserRank;
                }

                if ($roundNo === $roundCount) {
                    $winnerKey = $this->standingNodeKey($winnerNode);
                    if ($winnerKey !== '') {
                        $rankByKey[$winnerKey] = 1;
                    }
                }
            }
        }

        if (!in_array(1, array_values($rankByKey), true)) {
            throw new InvalidArgumentException('優勝者を確定できませんでした。決勝スコアを確認してください。');
        }

        $rows = [];

        foreach ($rankByKey as $key => $ranking) {
            $node = $nodesByKey[$key] ?? null;
            if (!is_array($node)) {
                continue;
            }

            $stats = $scoreStatsByKey[$key] ?? [
                'scratch_pin' => 0,
                'games' => 0,
            ];

            $carryPin = (int) ($node['total_pin'] ?? 0);
            $carryGames = (int) ($node['games'] ?? 0);
            $scratchPin = (int) ($stats['scratch_pin'] ?? 0);
            $scratchGames = (int) ($stats['games'] ?? 0);
            $games = $carryGames + $scratchGames;
            $totalPin = $carryPin + $scratchPin;
            $average = $games > 0 ? round($totalPin / $games, 2) : null;

            $displayName = trim((string) ($node['display_name'] ?? $node['label'] ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($node['pro_bowler_license_no'] ?? 'unknown'));
            }

            $proBowlerId = isset($node['pro_bowler_id']) && $node['pro_bowler_id'] !== null
                ? (int) $node['pro_bowler_id']
                : null;

            $rows[] = [
                'ranking' => (int) $ranking,
                'pro_bowler_id' => $proBowlerId && $proBowlerId > 0 ? $proBowlerId : null,
                'pro_bowler_license_no' => $node['pro_bowler_license_no'] ?? null,
                'amateur_name' => $proBowlerId ? null : ($node['amateur_name'] ?? $displayName),
                'display_name' => $displayName,
                'gender' => null,
                'shift' => null,
                'entry_number' => $node['participant_key'] ?? null,
                'scratch_pin' => $scratchPin,
                'carry_pin' => $carryPin,
                'total_pin' => $totalPin,
                'games' => $games,
                'average' => $average,
                'tie_break_value' => (float) (100000 - (int) $ranking),
                'points' => null,
                'prize_money' => null,
                '_sort_seed' => (int) ($node['seed'] ?? $node['min_seed'] ?? 999999),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $byRank = ((int) $a['ranking']) <=> ((int) $b['ranking']);
            if ($byRank !== 0) {
                return $byRank;
            }

            $bySeed = ((int) ($a['_sort_seed'] ?? 999999)) <=> ((int) ($b['_sort_seed'] ?? 999999));
            if ($bySeed !== 0) {
                return $bySeed;
            }

            return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        foreach ($rows as &$row) {
            unset($row['_sort_seed']);
        }
        unset($row);

        return $rows;
    }

    private function rememberStandingNode(array &$nodesByKey, array $node): void
    {
        if (!$this->isPlayableSlot($node)) {
            return;
        }

        $key = $this->standingNodeKey($node);
        if ($key === '') {
            return;
        }

        if (!isset($nodesByKey[$key])) {
            $nodesByKey[$key] = $node;
        }
    }

    private function addStandingScore(array &$nodesByKey, array &$scoreStatsByKey, array $node, int $score): void
    {
        if (!$this->isPlayableSlot($node)) {
            return;
        }

        $key = $this->standingNodeKey($node);
        if ($key === '') {
            return;
        }

        if (!isset($nodesByKey[$key])) {
            $nodesByKey[$key] = $node;
        }

        if (!isset($scoreStatsByKey[$key])) {
            $scoreStatsByKey[$key] = [
                'scratch_pin' => 0,
                'games' => 0,
            ];
        }

        $scoreStatsByKey[$key]['scratch_pin'] += $score;
        $scoreStatsByKey[$key]['games']++;
    }

    private function standingNodeKey(array $node): string
    {
        $participantKey = trim((string) ($node['participant_key'] ?? ''));
        if ($participantKey !== '') {
            return 'participant:' . $participantKey;
        }

        $proBowlerId = (int) ($node['pro_bowler_id'] ?? 0);
        if ($proBowlerId > 0) {
            return 'pro:' . $proBowlerId;
        }

        $license = strtoupper(trim((string) ($node['pro_bowler_license_no'] ?? '')));
        if ($license !== '') {
            return 'license:' . $license;
        }

        $amateurName = trim((string) ($node['amateur_name'] ?? ''));
        if ($amateurName !== '') {
            return 'amateur:' . md5($amateurName);
        }

        $displayName = trim((string) ($node['display_name'] ?? $node['label'] ?? ''));
        if ($displayName !== '') {
            return 'name:' . md5($displayName);
        }

        return '';
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