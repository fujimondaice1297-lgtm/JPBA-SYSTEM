<?php

test('official 2026 standard detail dataset is internally complete', function (): void {
    $path = dirname(__DIR__, 2).'/database/data/jpba_official_2026_standard_detail_scores.json';
    $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['event_count'])->toBe(12)
        ->and($payload['events'])->toHaveCount(12)
        ->and(array_sum(array_column($payload['events'], 'expected_score_count')))->toBe(14595);

    foreach ($payload['events'] as $event) {
        $scoreCount = 0;
        $identities = [];

        foreach ($event['stages'] as $stage) {
            expect($stage['stage'])->not->toBeEmpty()
                ->and($stage['rows'])->not->toBeEmpty();

            foreach ($stage['rows'] as $row) {
                $identities[$row['identity']] = true;
                $scores = array_column($row['games'], 'score');
                $scoreCount += count($scores);

                expect(array_sum($scores))->toBe((int) $row['stage_total_pin'])
                    ->and(count($scores))->toBe((int) $row['games_count'])
                    ->and(min($scores))->toBeGreaterThanOrEqual(0)
                    ->and(max($scores))->toBeLessThanOrEqual(300);
            }
        }

        expect($scoreCount)->toBe((int) $event['expected_score_count'])
            ->and(count($identities))->toBe((int) $event['expected_player_count']);
    }
});
