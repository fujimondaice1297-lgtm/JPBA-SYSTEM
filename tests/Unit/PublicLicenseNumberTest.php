<?php

use App\Support\PublicLicenseNumber;

test('internal JPBA identifiers are converted to public license numbers', function () {
    expect(PublicLicenseNumber::format('M00001297'))->toBe('1297')
        ->and(PublicLicenseNumber::format('M0001297'))->toBe('1297')
        ->and(PublicLicenseNumber::format('F00000599'))->toBe('0599')
        ->and(PublicLicenseNumber::format('M000T012'))->toBe('T012')
        ->and(PublicLicenseNumber::format('T7'))->toBe('T007')
        ->and(PublicLicenseNumber::format('AMATEUR-42'))->toBe('アマ')
        ->and(PublicLicenseNumber::format(null))->toBe('-');
});

test('non JPBA identifiers are not silently rewritten', function () {
    expect(PublicLicenseNumber::format('OVERSEAS-A12'))->toBe('OVERSEAS-A12');
});
