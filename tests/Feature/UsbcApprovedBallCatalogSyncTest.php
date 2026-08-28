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
        'brand' => 'Track Inc.',
        'name' => 'Missing 2018 Ball',
        'approved_date_text' => 'March 08, 2018',
        'approved_on' => '2018-03-08',
        'image_url' => 'https://images.example.test/missing-2018-ball.png',
        'normalized_brand' => 'TRACKINC',
        'normalized_name' => 'MISSING2018BALL',
        'source_fingerprint' => $missingFingerprint,
    ]);

    $existing = ApprovedBall::query()->create([
        'name' => 'Existing Ball',
        'manufacturer' => 'ABS',
        'brand' => '900GLOBAL',
        'catalog_status' => 'listed',
    ]);

    $this->artisan('balls:sync-usbc-approved', [
        '--use-latest' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(ApprovedBall::query()->count())->toBe(2)
        ->and($existing->fresh()->usbc_matched_brand)->toBe('900 Global');
    $imported = ApprovedBall::query()
        ->where('source_fingerprint', $missingFingerprint)
        ->firstOrFail();
    expect($imported->manufacturer)->toBe('USBC')
        ->and($imported->registration_brand)->toBe('Track Inc.')
        ->and($imported->release_date->format('Y-m-d'))->toBe('2018-03-08')
        ->and($imported->registration_period_label)->toBe('USBC承認 2018-03-08')
        ->and($imported->usbc_match_status)->toBe('matched')
        ->and($imported->source_payload['source_type'])->toBe('usbc_approved_list');

    $this->artisan('balls:sync-usbc-approved', [
        '--use-latest' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(ApprovedBall::query()->count())->toBe(2);
});
