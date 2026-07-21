<?php

namespace App\Services;

final class TournamentAggregateCalculator
{
    public function finalize(
        array $subjects,
        array $sources,
        string $subjectType,
        bool $requireAllSources = true,
        string $tieBreakPolicy = 'shared_rank',
    ): array {
        $requiredSources = array_values(array_filter(
            $sources,
            fn (array $source): bool => $requireAllSources || ! empty($source['is_required'])
        ));

        foreach ($subjects as &$subject) {
            $subject['games'] = (int) ($subject['games'] ?? 0);
            $subject['total_pin'] = (int) ($subject['total_pin'] ?? 0);
            $subject['average'] = $subject['games'] > 0
                ? round($subject['total_pin'] / $subject['games'], 3)
                : null;
            $subject['source_count'] = count(array_filter(
                $subject['source_breakdown'] ?? [],
                fn (array $row): bool => (int) ($row['games'] ?? 0) > 0
            ));
            $subject['tie_break_value'] = $this->scoreSpread($subject['score_values'] ?? []);

            $reasons = [];
            if ($subjectType === 'group') {
                $memberKeys = array_values($subject['member_keys'] ?? []);
                $expectedMembers = (int) ($subject['expected_member_count'] ?? 0);

                if ($expectedMembers > 0 && count($memberKeys) !== $expectedMembers) {
                    $reasons[] = sprintf('編成人数 %d/%d名', count($memberKeys), $expectedMembers);
                }

                foreach ($requiredSources as $source) {
                    $this->validateGroupSource($subject, $source, $memberKeys, $reasons);
                }
            } else {
                foreach ($requiredSources as $source) {
                    $this->validateIndividualSource($subject, $source, $reasons);
                }
            }

            if ($requiredSources === [] && $subject['games'] === 0) {
                $reasons[] = '集計対象スコアなし';
            }

            $subject['is_complete'] = $reasons === [];
            $subject['incomplete_reasons'] = $reasons;
            unset($subject['score_values']);
        }
        unset($subject);

        usort($subjects, function (array $a, array $b) use ($tieBreakPolicy): int {
            $byComplete = ((int) ! empty($b['is_complete'])) <=> ((int) ! empty($a['is_complete']));
            if ($byComplete !== 0) {
                return $byComplete;
            }

            $byTotal = ((int) $b['total_pin']) <=> ((int) $a['total_pin']);
            if ($byTotal !== 0) {
                return $byTotal;
            }

            if ($tieBreakPolicy === 'low_high') {
                $bySpread = ((int) ($a['tie_break_value'] ?? PHP_INT_MAX))
                    <=> ((int) ($b['tie_break_value'] ?? PHP_INT_MAX));
                if ($bySpread !== 0) {
                    return $bySpread;
                }
            }

            return strcmp((string) $a['display_name'], (string) $b['display_name']);
        });

        $eligiblePosition = 0;
        $incompleteRank = count(array_filter($subjects, fn (array $row): bool => ! empty($row['is_complete']))) + 1;
        $previousTieKey = null;
        $previousRank = null;
        foreach ($subjects as &$subject) {
            if (empty($subject['is_complete'])) {
                $subject['ranking'] = $incompleteRank++;

                continue;
            }

            $eligiblePosition++;
            $tieKey = (string) $subject['total_pin'];
            if ($tieBreakPolicy === 'low_high') {
                $tieKey .= ':'.(string) ($subject['tie_break_value'] ?? '');
            }

            if ($tieKey === $previousTieKey && $previousRank !== null) {
                $subject['ranking'] = $previousRank;
            } else {
                $subject['ranking'] = $eligiblePosition;
                $previousRank = $eligiblePosition;
                $previousTieKey = $tieKey;
            }
        }
        unset($subject);

        return $subjects;
    }

    private function validateIndividualSource(array $subject, array $source, array &$reasons): void
    {
        $sourceId = (int) $source['id'];
        $bucket = $subject['source_breakdown'][$sourceId] ?? null;
        $games = (int) ($bucket['games'] ?? 0);
        $expectedGames = (int) ($source['expected_games_per_member'] ?? 0);
        $label = (string) ($source['label'] ?? ('集計元 #'.$sourceId));

        if ($games === 0) {
            $reasons[] = $label.'のスコアなし';
        } elseif ($expectedGames > 0 && $games !== $expectedGames) {
            $reasons[] = sprintf('%s %d/%dG', $label, $games, $expectedGames);
        }
    }

    private function validateGroupSource(
        array $subject,
        array $source,
        array $memberKeys,
        array &$reasons,
    ): void {
        $sourceId = (int) $source['id'];
        $bucket = $subject['source_breakdown'][$sourceId] ?? null;
        $memberGames = (array) ($bucket['member_games'] ?? []);
        $expectedGames = (int) ($source['expected_games_per_member'] ?? 0);
        $label = (string) ($source['label'] ?? ('集計元 #'.$sourceId));

        if ((int) ($bucket['games'] ?? 0) === 0) {
            $reasons[] = $label.'のスコアなし';

            return;
        }

        foreach ($memberKeys as $memberKey) {
            $games = (int) ($memberGames[$memberKey] ?? 0);
            $minimum = $expectedGames > 0 ? $expectedGames : 1;
            if (($expectedGames > 0 && $games !== $expectedGames)
                || ($expectedGames === 0 && $games < $minimum)) {
                $reasons[] = sprintf('%sに未入力メンバーあり', $label);

                return;
            }
        }
    }

    private function scoreSpread(array $scores): int
    {
        $scores = array_values(array_filter(
            array_map('intval', $scores),
            fn (int $score): bool => $score > 0
        ));

        return count($scores) <= 1 ? 0 : max($scores) - min($scores);
    }
}
