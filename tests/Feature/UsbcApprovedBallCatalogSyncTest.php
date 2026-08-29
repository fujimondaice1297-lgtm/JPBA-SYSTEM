<?php

use App\Models\ApprovedBall;
use App\Models\UsbcApprovedBallEntry;
use App\Models\UsbcApprovedBallList;

test('the saved USBC snapshot fills every missing official ball into the registration catalog', function () {
    $list = UsbcApprovedBallList::query()->create([
        'official_updated_on' => '2026-08-25',
        'source_page_url' => 'https://bowl.com/approved-ball-list',
        'source_pdf_url' => 'https://bowl.com/current.pdf',
        'source_api_url' => 'https://bowl.com/api/approvedballs',
        'source_sha256' => str_repeat('a', 64),
        'status' => 'completed',
        'fetched_at' => now(),
        'completed_at' => now(),
        'brand_count' => 2,
        'entry_count' => 2,
    ]);

    $representedFingerprint = hash('sha256', 'represented');
    $missingFingerprint = hash('sha256', 'missing');
    UsbcApprovedBallEntry::query()->create([
        'list_id' => $list->id,
        'brand' => '900 Global',
        'name' => 'Existing Ball',
        'approved_date_text' => 'January 10, 2020',
        'approved_on' => '2020-01-10',
        'normalized_brand' => '900GLOBAL',
        'normalized_name' => 'EXISTINGBALL',
        'source_fingerprint' => $representedFingerprint,
    ]);
    UsbcApprovedBallEntry::query()->create([
        'list_id' => $list->id,
        'brand' => 'ABS',
        'name' => 'PRO-am Missing 2018 Ball',
        'approved_date_text' => "Mar'18",
        'approved_on' => null,
        'image_url' => 'https://images.example.test/missing-2018-ball.png',
        'normalized_brand' => 'ABS',
        'normalized_name' => 'PROAMMISSING2018BALL',
        'source_fingerprint' => $missingFingerprint,
    ]);

    $existing = ApprovedBall::query()->create([
        'name' => 'Existing Ball',
        'manufacturer' => 'ABS',
        'brand' => '900GLOBAL',
        'catalog_status' => 'listed',
        'release_date' => '2026-04-01',
        'source_payload' => [
            'release_text' => '2026年4月発売',
            'release_date_basis' => 'official_publish_date',
        ],
    ]);

    $this->artisan('balls:sync-usbc-approved', [
        '--use-latest' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(ApprovedBall::query()->count())->toBe(2)
        ->and($existing->fresh()->usbc_matched_brand)->toBe('900 Global')
        ->and($existing->fresh()->registration_brand)->toBe('900GLOBAL')
        ->and($existing->fresh()->release_date->format('Y-m-d'))->toBe('2020-01-10')
        ->and($existing->fresh()->source_payload['domestic_release_date'])->toBe('2026-04-01')
        ->and($existing->fresh()->source_payload['domestic_release_text'])->toBe('2026年4月発売')
        ->and($existing->fresh()->registration_period_label)->toBe('USBC承認 2020-01-10');
    $imported = ApprovedBall::query()
        ->where('source_fingerprint', $missingFingerprint)
        ->firstOrFail();
    expect($imported->manufacturer)->toBe('USBC')
        ->and($imported->brand)->toBe('PRO-am')
        ->and($imported->registration_brand)->toBe('PRO-am')
        ->and($imported->release_date->format('Y-m-d'))->toBe('2018-03-01')
        ->and($imported->registration_period_label)->toBe('USBC承認 2018-03')
        ->and($imported->usbc_match_status)->toBe('matched')
        ->and($imported->source_payload['source_type'])->toBe('usbc_approved_list')
        ->and($imported->source_payload['usbc_official_brand'])->toBe('ABS')
        ->and($imported->image_url)->toContain('images/ball-no-image.svg');
    expect(UsbcApprovedBallEntry::query()
        ->where('source_fingerprint', $missingFingerprint)
        ->firstOrFail()
        ->approved_on
        ->format('Y-m-d'))->toBe('2018-03-01');

    $this->artisan('balls:sync-usbc-approved', [
        '--use-latest' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(ApprovedBall::query()->count())->toBe(2);
});
