<?php

test('the committed approved event archive is complete and self contained', function () {
    $projectRoot = dirname(__DIR__, 2);
    $snapshotPath = $projectRoot.'/resources/data/jpba_approved_event_archive.json';

    expect(is_file($snapshotPath))->toBeTrue();

    $snapshot = json_decode(file_get_contents($snapshotPath), true, flags: JSON_THROW_ON_ERROR);
    $archives = collect($snapshot['archives'] ?? []);
    $assets = $archives->flatMap(fn (array $archive): array => $archive['assets'] ?? []);
    $assetPaths = $assets->pluck('path')->unique()->values();

    expect($snapshot['schema_version'] ?? null)->toBe(1)
        ->and($snapshot['summary']['archive_count'] ?? null)->toBe(265)
        ->and($snapshot['summary']['asset_count'] ?? null)->toBe(556)
        ->and($snapshot['summary']['asset_failure_count'] ?? null)->toBe(8)
        ->and($archives)->toHaveCount(265)
        ->and($archives->pluck('source_key')->unique())->toHaveCount(265)
        ->and($archives->where('classification', 'approved_event'))->toHaveCount(265)
        ->and($assets)->toHaveCount(1085)
        ->and($assetPaths)->toHaveCount(556)
        ->and($assets->where('type', 'oil_pattern'))->toHaveCount(18);

    $publicRoot = realpath($projectRoot.'/public');
    $archiveRoot = realpath($publicRoot.'/documents/jpba/approved-event-archive/assets');
    expect($publicRoot)->not->toBeFalse()
        ->and($archiveRoot)->not->toBeFalse();

    $invalidPaths = [];
    $missingFiles = [];
    $emptyFiles = [];
    $escapedFiles = [];
    $invalidPdfFiles = [];

    foreach ($assetPaths as $relativePath) {
        if (! str_starts_with($relativePath, 'documents/jpba/approved-event-archive/assets/')) {
            $invalidPaths[] = $relativePath;
        }

        $absolutePath = realpath($publicRoot.'/'.$relativePath);
        if ($absolutePath === false) {
            $missingFiles[] = $relativePath;

            continue;
        }

        if (filesize($absolutePath) <= 0) {
            $emptyFiles[] = $relativePath;
        }

        if (! str_starts_with($absolutePath, $archiveRoot.DIRECTORY_SEPARATOR)) {
            $escapedFiles[] = $relativePath;
        }

        if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf') {
            $handle = fopen($absolutePath, 'rb');
            $signature = $handle === false ? '' : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== '%PDF-') {
                $invalidPdfFiles[] = $relativePath;
            }
        }
    }

    expect($invalidPaths)->toBeEmpty()
        ->and($missingFiles)->toBeEmpty()
        ->and($emptyFiles)->toBeEmpty()
        ->and($escapedFiles)->toBeEmpty()
        ->and($invalidPdfFiles)->toBeEmpty();
});
