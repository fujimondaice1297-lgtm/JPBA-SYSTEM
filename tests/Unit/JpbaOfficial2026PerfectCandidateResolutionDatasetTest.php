<?php

function official2026PerfectCandidateResolutionRows(): array
{
    return json_decode(
        (string) file_get_contents(
            dirname(__DIR__, 2)
            . '/database/data/jpba_official_2026_perfect_candidate_resolutions.json'
        ),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

it('contains all nine score candidates verified by official JPBA sources', function () {
    $rows = collect(official2026PerfectCandidateResolutionRows());

    expect($rows)->toHaveCount(9)
        ->and($rows->pluck('record_id')->unique())->toHaveCount(9)
        ->and($rows->whereNotNull('canonical_record_id'))->toHaveCount(4)
        ->and($rows->pluck('canonical_record_id')->filter()->unique())
        ->toHaveCount(4)
        ->and($rows->where('gender', 'M'))->toHaveCount(8)
        ->and($rows->where('gender', 'F'))->toHaveCount(1);
});

it('keeps official certification numbers unique by gender', function () {
    $rows = collect(official2026PerfectCandidateResolutionRows());
    $identities = $rows->map(
        fn (array $row): string => $row['gender']
            . '|' . $row['certification_number_value']
    );

    expect($identities->unique())->toHaveCount(9)
        ->and($rows->where('gender', 'M')
            ->pluck('certification_number_value')
            ->sort()
            ->values()
            ->all())->toBe([
                1787, 1790, 1791, 1792, 1797, 1798, 1799, 1800,
            ])
        ->and($rows->where('gender', 'F')
            ->pluck('certification_number_value')
            ->values()
            ->all())->toBe([378]);
});

it('stores the exact official date and game for every resolution', function () {
    $rows = collect(official2026PerfectCandidateResolutionRows());

    foreach ($rows as $row) {
        expect($row['awarded_on'])->toStartWith('2026-')
            ->and($row['official_player_perfect_count'])->toBeGreaterThan(0)
            ->and($row['game_numbers'])->not->toBeEmpty()
            ->and($row['stage'])->not->toBeEmpty()
            ->and($row['source_url'])->toStartWith('https://www.jpba.or.jp/');
    }

    expect($rows->firstWhere('record_id', 19)['awarded_on'])
        ->toBe('2026-05-24')
        ->and($rows->firstWhere('record_id', 22)['awarded_on'])
        ->toBe('2026-06-19')
        ->and($rows->firstWhere('record_id', 23)['stage'])
        ->toBe('TOP12ラウンド')
        ->and($rows->firstWhere('record_id', 24)['game_numbers'])
        ->toBe('TOP12ラウンド6G目')
        ->and($rows->firstWhere('record_id', 24)['official_player_perfect_count'])
        ->toBe(13)
        ->and($rows->firstWhere('record_id', 27)['official_player_perfect_count'])
        ->toBe(6);
});
