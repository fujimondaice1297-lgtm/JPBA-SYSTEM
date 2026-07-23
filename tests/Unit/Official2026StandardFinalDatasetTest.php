<?php

test('official 2026 standard final dataset is internally complete', function (): void {
    $path = dirname(__DIR__, 2).'/database/data/jpba_official_2026_standard_final_scores.json';
    $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['event_count'])->toBe(11)
        ->and($payload['score_sheet_count'])->toBe(29)
        ->and($payload['frame_player_count'])->toBe(63)
        ->and($payload['frame_count'])->toBe(630)
        ->and($payload['additional_stage_score_count'])->toBe(96)
        ->and($payload['bracket_match_count'])->toBe(41)
        ->and($payload['bracket_score_count'])->toBe(158);

    foreach ($payload['events'] as $event) {
        foreach ($event['score_sheets'] as $sheet) {
            expect($sheet['players'])->not->toBeEmpty();
            foreach ($sheet['players'] as $player) {
                expect($player['frames'])->toHaveCount(10)
                    ->and($player['frames'][9]['cumulative_score'])->toBe($player['score']);
            }
        }
        foreach ($event['bracket_matches'] ?? [] as $match) {
            $winnerCount = 0;
            foreach ($match['players'] as $player) {
                expect(array_sum(array_column($player['scores'], 'score')))
                    ->toBe($player['match_total_pin']);
                $winnerCount += $player['is_winner'] ? 1 : 0;
            }
            expect($winnerCount)->toBe(1);
        }
    }
});
