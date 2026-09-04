<?php

test('the committed public content archive is complete and self contained', function () {
    $projectRoot = dirname(__DIR__, 2);
    $snapshotPath = $projectRoot.'/resources/data/jpba_public_content_archive.json';

    expect(is_file($snapshotPath))->toBeTrue();

    $snapshot = json_decode(file_get_contents($snapshotPath), true, flags: JSON_THROW_ON_ERROR);
    $articles = collect($snapshot['articles'] ?? []);
    $sourceKeys = $articles->pluck('source_key');

    expect($snapshot['schema_version'] ?? null)->toBe(1)
        ->and($articles)->toHaveCount(978)
        ->and($articles->where('source_type', 'legacy_information'))->toHaveCount(395)
        ->and($articles->where('source_type', 'legacy_topic'))->toHaveCount(583)
        ->and($sourceKeys->unique())->toHaveCount(978)
        ->and($articles->filter(fn (array $article): bool => trim((string) ($article['title'] ?? '')) === ''))->toBeEmpty()
        ->and($articles->filter(fn (array $article): bool => trim((string) ($article['body_html'] ?? '')) === ''))->toBeEmpty()
        ->and($articles->filter(fn (array $article): bool => str_contains((string) ($article['body_html'] ?? ''), 'jpba.or.jp')))->toBeEmpty()
        ->and($articles->filter(fn (array $article): bool => str_contains((string) ($article['body_html'] ?? ''), 'jpba1.jp')))->toBeEmpty();

    $publicRoot = $projectRoot.'/public';
    $archiveRoot = realpath($publicRoot.'/documents/jpba/content-archive/assets');
    expect($archiveRoot)->not->toBeFalse();

    $assetPaths = $articles->flatMap(fn (array $article): array => $article['assets'] ?? [])
        ->pluck('path')
        ->unique()
        ->values();

    expect($assetPaths)->toHaveCount(1756);

    $invalidPaths = [];
    $missingFiles = [];
    $emptyFiles = [];
    $escapedFiles = [];

    foreach ($assetPaths as $relativePath) {
        if (! str_starts_with($relativePath, 'documents/jpba/content-archive/assets/')) {
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
    }

    expect($invalidPaths)->toBeEmpty()
        ->and($missingFiles)->toBeEmpty()
        ->and($emptyFiles)->toBeEmpty()
        ->and($escapedFiles)->toBeEmpty();
});
