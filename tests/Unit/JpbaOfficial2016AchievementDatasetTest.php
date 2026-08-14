<?php

function official2016AchievementRows(): array
{
    return json_decode(
        (string) file_get_contents(
            dirname(__DIR__, 2)
            . '/database/data/jpba_official_2016_achievement_records.json'
        ),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

it('contains every verified 2016 JPBA professional achievement from the official tournament pages', function () {
    $rows = collect(official2016AchievementRows());

    expect($rows)->toHaveCount(55)
        ->and($rows->where('record_type', 'perfect'))->toHaveCount(40)
        ->and($rows->where('record_type', 'eight_hundred'))->toHaveCount(6)
        ->and($rows->where('record_type', 'seven_ten'))->toHaveCount(9)
        ->and($rows->pluck('source_url')->unique())->toHaveCount(15);
});

it('keeps certification identities and required detail fields unique and complete', function () {
    $rows = collect(official2016AchievementRows());
    $identities = $rows->map(fn (array $row): string => implode('|', [
        $row['record_type'],
        $row['gender'],
        $row['certification_number_value'],
    ]));

    expect($identities->unique())->toHaveCount($rows->count());

    foreach ($rows as $row) {
        expect($row['awarded_on'])->toStartWith('2016-')
            ->and($row['source_url'])->toContain('/tournament2016/')
            ->and($row['license_no'])->toMatch('/^[MF]\d{8}$/');

        if ($row['record_type'] === 'perfect') {
            expect($row['game_numbers'] ?? null)->not->toBeNull();
        }
        if ($row['record_type'] === 'eight_hundred') {
            expect($row['series_label'] ?? null)->not->toBeNull()
                ->and(
                    (int) $row['series_end_game']
                    - (int) $row['series_start_game']
                )->toBe(2);
        }
        if ($row['record_type'] === 'seven_ten') {
            expect($row['game_numbers'] ?? null)->not->toBeNull()
                ->and($row['frame_number'] ?? null)->not->toBeNull();
        }
    }
});

it('preserves the official-page corrections and PDF-verified venue dates', function () {
    $rows = collect(official2016AchievementRows());

    $mkKomori = $rows->firstWhere('certification_number_value', 1348);
    $springItoyama = $rows->firstWhere('certification_number_value', 1329);
    $summerOta = $rows->firstWhere('certification_number_value', 1342);

    expect($mkKomori['license_no'])->toBe('M00001225')
        ->and($mkKomori['notes'])->toContain('No.1225')
        ->and($springItoyama['awarded_on'])->toBe('2016-05-17')
        ->and($springItoyama['shift'])->toBe('C会場')
        ->and($summerOta['awarded_on'])->toBe('2016-07-05')
        ->and($summerOta['shift'])->toBe('D会場');
});
