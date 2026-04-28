<?php

namespace App\Services;

use InvalidArgumentException;

class ShootoutService
{
    public const TYPE = 'shootout';

    /**
     * JPBA標準8名シュートアウトの表示・勝ち上がり用データを作る。
     *
     * 標準構成:
     * - SO1: 5位〜8位通過者
     * - SO2: 2位〜4位通過者 + SO1勝者
     * - SO3: 1位通過者 + SO2勝者
     *
     * 重要:
     * - 各マッチのスコアは勝ち上がり判定だけに使う。
     * - 敗退者順位はスコア順ではなく、進出元snapshotの通過順位を引き継ぐ。
     */
    public function buildStandard8(array $seedEntries = [], array $matchScores = []): array
    {
        $entriesBySeed = $this->normalizeSeedEntries($seedEntries);
        $seedNodes = $this->buildSeedNodes($entriesBySeed);

        $matches = [];

        $matches[] = $this->makeMatch(
            matchNo: 1,
            matchKey: 'SO1',
            label: '1stマッチ',
            description: '5位〜8位通過者。最上位者だけが2ndマッチへ進出。',
            slotCodes: ['A', 'B', 'C', 'D'],
            slots: [
                'A' => $seedNodes[5],
                'B' => $seedNodes[6],
                'C' => $seedNodes[7],
                'D' => $seedNodes[8],
            ],
            winnerTo: '2ndマッチ',
            loserRankRange: '6位〜8位',
            matchScores: $matchScores['SO1'] ?? []
        );

        $winnerSo1 = (array) ($matches[0]['winner_node'] ?? []);
        $matches[] = $this->makeMatch(
            matchNo: 2,
            matchKey: 'SO2',
            label: '2ndマッチ',
            description: '2位〜4位通過者 + 1stマッチ勝者。最上位者だけが優勝決定戦へ進出。',
            slotCodes: ['A', 'B', 'C', 'D'],
            slots: [
                'A' => $seedNodes[2],
                'B' => $seedNodes[3],
                'C' => $seedNodes[4],
                'D' => !empty($winnerSo1) ? $this->makeAdvancedNode($winnerSo1, 'SO1') : $this->makePendingNode('1stマッチ勝者'),
            ],
            winnerTo: '優勝決定戦',
            loserRankRange: '3位〜5位',
            matchScores: $matchScores['SO2'] ?? []
        );

        $winnerSo2 = (array) ($matches[1]['winner_node'] ?? []);
        $matches[] = $this->makeMatch(
            matchNo: 3,
            matchKey: 'SO3',
            label: '優勝決定戦',
            description: '1位通過者 + 2ndマッチ勝者。勝者が優勝。',
            slotCodes: ['A', 'B'],
            slots: [
                'A' => $seedNodes[1],
                'B' => !empty($winnerSo2) ? $this->makeAdvancedNode($winnerSo2, 'SO2') : $this->makePendingNode('2ndマッチ勝者'),
            ],
            winnerTo: '優勝',
            loserRankRange: '2位',
            matchScores: $matchScores['SO3'] ?? []
        );

        return [
            'type' => self::TYPE,
            'format' => 'standard_8',
            'ranking_policy' => 'carry_seed_order_for_losers',
            'summary' => [
                'qualifier_count' => 8,
                'match_count' => 3,
                'completed_match_count' => collect($matches)->filter(fn (array $match) => !empty($match['is_complete']))->count(),
                'winner_name' => $matches[2]['winner_node']['display_name'] ?? null,
            ],
            'seed_rows' => array_values($seedNodes),
            'matches' => $matches,
        ];
    }

    private function makeMatch(
        int $matchNo,
        string $matchKey,
        string $label,
        string $description,
        array $slotCodes,
        array $slots,
        string $winnerTo,
        string $loserRankRange,
        array $matchScores
    ): array {
        $normalizedSlots = [];
        $scoreBySlot = [];
        $playableSlotCodes = [];
        $missingPlayableSlot = false;
        $missingScore = false;

        foreach ($slotCodes as $slotCode) {
            $slot = (array) ($slots[$slotCode] ?? $this->makeEmptyNode());
            $normalizedSlots[$slotCode] = $slot;

            if ($this->isPlayableSlot($slot)) {
                $playableSlotCodes[] = $slotCode;
            } else {
                $missingPlayableSlot = true;
            }

            $score = $this->nullableScore($matchScores[$slotCode]['score'] ?? null);
            $scoreBySlot[$slotCode] = $score;

            if ($this->isPlayableSlot($slot) && $score === null) {
                $missingScore = true;
            }
        }

        $winnerSlot = null;
        $winnerNode = null;
        $isComplete = false;
        $isTied = false;

        if (!$missingPlayableSlot && count($playableSlotCodes) >= 2 && !$missingScore) {
            $maxScore = null;
            $maxSlots = [];

            foreach ($playableSlotCodes as $slotCode) {
                $score = $scoreBySlot[$slotCode];

                if ($maxScore === null || $score > $maxScore) {
                    $maxScore = $score;
                    $maxSlots = [$slotCode];
                } elseif ($score === $maxScore) {
                    $maxSlots[] = $slotCode;
                }
            }

            if (count($maxSlots) === 1) {
                $winnerSlot = $maxSlots[0];
                $winnerNode = $normalizedSlots[$winnerSlot];
                $isComplete = true;
            } else {
                $isTied = true;
            }
        }

        return [
            'match_no' => $matchNo,
            'match_key' => $matchKey,
            'label' => $label,
            'description' => $description,
            'slot_codes' => $slotCodes,
            'slots' => $normalizedSlots,
            'scores' => $scoreBySlot,
            'score_rows' => $matchScores,
            'winner_slot' => $winnerSlot,
            'winner_node' => $winnerNode,
            'winner_to' => $winnerTo,
            'loser_rank_range' => $loserRankRange,
            'is_complete' => $isComplete,
            'is_tied' => $isTied,
            'can_input' => !$missingPlayableSlot && count($playableSlotCodes) >= 2,
        ];
    }

    /**
     * シュートアウトの最終順位を作る。
     *
     * 重要:
     * - 優勝決定戦の勝者を1位、敗者を2位にする。
     * - 2ndマッチ敗退者はスコア順ではなく元通過順位順で3〜5位にする。
     * - 1stマッチ敗退者もスコア順ではなく元通過順位順で6〜8位にする。
     *
     * @return array<int,array<string,mixed>>
     */
    public function buildFinalStandings(array $shootout): array
    {
        $matches = [];
        foreach ((array) ($shootout['matches'] ?? []) as $match) {
            $match = (array) $match;
            $key = (string) ($match['match_key'] ?? '');
            if ($key !== '') {
                $matches[$key] = $match;
            }
        }

        foreach (['SO1', 'SO2', 'SO3'] as $requiredKey) {
            if (empty($matches[$requiredKey]) || ($matches[$requiredKey]['is_complete'] ?? false) !== true) {
                throw new InvalidArgumentException('シュートアウトの全マッチが確定していません。');
            }
            if (($matches[$requiredKey]['is_tied'] ?? false) === true) {
                throw new InvalidArgumentException('シュートアウトに同点マッチがあります。勝者を確定できません。');
            }
        }

        $shootoutScores = $this->collectShootoutScoresByParticipant($matches);

        $so3Winner = (array) ($matches['SO3']['winner_node'] ?? []);

        $so3Losers = $this->loserNodes($matches['SO3']);
        $so2Losers = $this->sortNodesBySourceOrder($this->loserNodes($matches['SO2']));
        $so1Losers = $this->sortNodesBySourceOrder($this->loserNodes($matches['SO1']));

        $orderedNodes = [];
        $orderedNodes[] = $so3Winner;
        foreach ($so3Losers as $node) {
            $orderedNodes[] = $node;
        }
        foreach ($so2Losers as $node) {
            $orderedNodes[] = $node;
        }
        foreach ($so1Losers as $node) {
            $orderedNodes[] = $node;
        }

        $standings = [];
        $usedKeys = [];

        foreach ($orderedNodes as $node) {
            $node = (array) $node;
            if (!$this->isPlayableSlot($node)) {
                continue;
            }

            $key = $this->nodeParticipantKey($node);
            if ($key !== '' && isset($usedKeys[$key])) {
                continue;
            }

            $score = $shootoutScores[$key] ?? ['pin' => 0, 'games' => 0];

            $standings[] = [
                'ranking' => count($standings) + 1,
                'node' => $node,
                'shootout_pin' => (int) ($score['pin'] ?? 0),
                'shootout_games' => (int) ($score['games'] ?? 0),
            ];

            if ($key !== '') {
                $usedKeys[$key] = true;
            }
        }

        return $standings;
    }

    /**
     * @param array<string,array<string,mixed>> $matches
     * @return array<string,array{pin:int,games:int}>
     */
    private function collectShootoutScoresByParticipant(array $matches): array
    {
        $scores = [];

        foreach ($matches as $match) {
            $slots = (array) ($match['slots'] ?? []);
            $matchScores = (array) ($match['scores'] ?? []);

            foreach ($slots as $slotCode => $node) {
                $node = (array) $node;
                if (!$this->isPlayableSlot($node)) {
                    continue;
                }

                $score = $this->nullableScore($matchScores[$slotCode] ?? null);
                if ($score === null) {
                    continue;
                }

                $key = $this->nodeParticipantKey($node);
                if ($key === '') {
                    continue;
                }

                if (!isset($scores[$key])) {
                    $scores[$key] = ['pin' => 0, 'games' => 0];
                }

                $scores[$key]['pin'] += $score;
                $scores[$key]['games']++;
            }
        }

        return $scores;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loserNodes(array $match): array
    {
        $winnerSlot = (string) ($match['winner_slot'] ?? '');
        $nodes = [];

        foreach ((array) ($match['slots'] ?? []) as $slotCode => $node) {
            $node = (array) $node;
            if (!$this->isPlayableSlot($node)) {
                continue;
            }
            if ((string) $slotCode === $winnerSlot) {
                continue;
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<int,array<string,mixed>> $nodes
     * @return array<int,array<string,mixed>>
     */
    private function sortNodesBySourceOrder(array $nodes): array
    {
        usort($nodes, function (array $a, array $b): int {
            $aOrder = (int) ($a['source_ranking'] ?? $a['min_seed'] ?? $a['seed'] ?? 9999);
            $bOrder = (int) ($b['source_ranking'] ?? $b['min_seed'] ?? $b['seed'] ?? 9999);

            if ($aOrder === $bOrder) {
                return (int) ($a['seed'] ?? $a['min_seed'] ?? 9999) <=> (int) ($b['seed'] ?? $b['min_seed'] ?? 9999);
            }

            return $aOrder <=> $bOrder;
        });

        return $nodes;
    }

    private function nodeParticipantKey(array $node): string
    {
        $key = trim((string) ($node['participant_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        $proBowlerId = (int) ($node['pro_bowler_id'] ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:' . $proBowlerId;
        }

        $license = strtoupper(trim((string) ($node['pro_bowler_license_no'] ?? '')));
        if ($license !== '') {
            return 'license:' . $license;
        }

        $name = trim((string) ($node['display_name'] ?? $node['amateur_name'] ?? ''));
        if ($name !== '') {
            return 'name:' . $name;
        }

        return '';
    }

    private function normalizeSeedEntries(array $seedEntries): array
    {
        $entriesBySeed = [];

        foreach ($seedEntries as $entry) {
            $entry = (array) $entry;
            $seed = (int) ($entry['seed'] ?? 0);

            if ($seed < 1 || $seed > 8) {
                continue;
            }

            $entriesBySeed[$seed] = $entry;
        }

        return $entriesBySeed;
    }

    private function buildSeedNodes(array $entriesBySeed): array
    {
        $nodes = [];

        for ($seed = 1; $seed <= 8; $seed++) {
            $entry = $entriesBySeed[$seed] ?? [];

            $nodes[$seed] = [
                'type' => !empty($entry) ? 'seed' : 'empty',
                'seed' => $seed,
                'label' => !empty($entry) ? ('#' . $seed) : ('seed' . $seed . ' 未確定'),
                'display_name' => $entry['display_name'] ?? ('seed' . $seed . ' 未確定'),
                'pro_bowler_id' => $entry['pro_bowler_id'] ?? null,
                'pro_bowler_license_no' => $entry['pro_bowler_license_no'] ?? null,
                'amateur_name' => $entry['amateur_name'] ?? null,
                'source_row_id' => $entry['source_row_id'] ?? null,
                'participant_key' => $entry['participant_key'] ?? ('seed:' . $seed),
                'source_ranking' => $entry['source_ranking'] ?? $seed,
                'total_pin' => $entry['total_pin'] ?? null,
                'games' => $entry['games'] ?? null,
                'average' => $entry['average'] ?? null,
                'min_seed' => $seed,
                'max_seed' => $seed,
            ];
        }

        return $nodes;
    }

    private function makeAdvancedNode(array $winnerNode, string $sourceMatchKey): array
    {
        $advanced = $winnerNode;
        $advanced['type'] = 'advanced';
        $advanced['advanced_from_match_key'] = $sourceMatchKey;
        $advanced['label'] = $winnerNode['display_name'] ?? $winnerNode['label'] ?? '勝者';
        $advanced['display_name'] = $winnerNode['display_name'] ?? $winnerNode['label'] ?? '勝者';

        return $advanced;
    }

    private function makePendingNode(string $label): array
    {
        return [
            'type' => 'pending',
            'label' => $label,
            'display_name' => $label,
            'participant_key' => null,
        ];
    }

    private function makeEmptyNode(): array
    {
        return [
            'type' => 'empty',
            'label' => '未確定',
            'display_name' => '未確定',
            'participant_key' => null,
        ];
    }

    private function isPlayableSlot(array $slot): bool
    {
        return in_array((string) ($slot['type'] ?? ''), ['seed', 'advanced'], true);
    }

    private function nullableScore($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(300, (int) $value));
    }
}
