<?php

test('official 2026 Seahorse selection dataset is internally complete', function (): void {
    $path = dirname(__DIR__, 2).'/database/data/jpba_official_2026_seahorse_selection_scores.json';
    $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $event = $payload['events'][0];
    $licenses = [];
    $scoreCount = 0;
    $totalPin = 0;
    $attemptCount = 0;

    expect($payload['events'])->toHaveCount(1)
        ->and($event['attempt_count'])->toBe(240)
        ->and($event['participant_count'])->toBe(101)
        ->and($event['expected_score_count'])->toBe(720)
        ->and($event['expected_total_pin'])->toBe(147459)
        ->and($event['tournament']['counts_for_official_points'])->toBeFalse()
        ->and($event['tournament']['counts_for_average'])->toBeTrue()
        ->and($event['tournament']['counts_for_prize'])->toBeFalse()
        ->and($event['tournament']['title_scope'])->toBe('none');

    foreach ($event['stages'] as $stage) {
        $stageLicenses = [];
        foreach ($stage['rows'] as $row) {
            $scores = array_column($row['games'], 'score');
            expect($scores)->toHaveCount(3)
                ->and(array_sum($scores))->toBe($row['stage_total_pin']);

            $license = strtoupper($row['license_no']);
            expect($stageLicenses)->not->toHaveKey($license);
            $stageLicenses[$license] = true;
            $licenses[$license] = true;
            $attemptCount++;
            $scoreCount += count($scores);
            $totalPin += array_sum($scores);
        }
    }

    expect($attemptCount)->toBe(240)
        ->and($licenses)->toHaveCount(101)
        ->and($scoreCount)->toBe(720)
        ->and($totalPin)->toBe(147459);
});
