<?php

use App\Services\JpbaArchiveSnapshotMerger;

test('a single year archive refresh retains older years and saved documents', function () {
    $oldArchive = ['source_key' => 'old', 'source_url' => 'old-url', 'year' => 2016, 'start_on' => null, 'title' => '過去大会', 'assets' => [['path' => 'old.pdf']]];
    $currentArchive = ['source_key' => 'current', 'source_url' => 'current-url', 'year' => 2026, 'start_on' => null, 'title' => '今年大会', 'assets' => [['path' => 'saved.pdf']]];
    $previous = ['year_from' => 2016, 'year_to' => 2026, 'archives' => [$oldArchive, $currentArchive], 'page_failures' => [['url' => 'current-url']], 'asset_failures' => [['url' => 'missing-old.pdf']]];
    $incoming = ['year_from' => 2026, 'year_to' => 2026, 'summary' => ['archive_count' => 1, 'discovered_page_count' => 1], 'archives' => [array_replace($currentArchive, ['title' => '更新大会', 'assets' => [['path' => 'new.pdf']]])]];
    $service = new JpbaArchiveSnapshotMerger;
    $merged = $service->merge($previous, $incoming, fn (string $path): int => 100);
    expect($merged['year_from'])->toBe(2016)
        ->and($merged['archives'][0])->toBe($oldArchive)
        ->and($merged['archives'][1]['title'])->toBe('更新大会')
        ->and($merged['archives'][1]['assets'])->toHaveCount(2)
        ->and($merged['summary']['archive_count'])->toBe(2)
        ->and($merged['summary']['asset_count'])->toBe(3)
        ->and($merged['summary']['asset_bytes'])->toBe(300)
        ->and($merged['page_failures'])->toBeEmpty()
        ->and($merged['asset_failures'])->toHaveCount(1)
        ->and($service->merge($merged, $incoming, fn (string $path): int => 100)['archives'])->toBe($merged['archives']);
});

test('a failed or empty refresh never removes saved archive entries', function () {
    $row = ['source_key' => 'old', 'source_url' => 'url', 'year' => 2015, 'title' => '保存済', 'assets' => []];
    $merged = (new JpbaArchiveSnapshotMerger)->merge(
        ['year_from' => 2015, 'year_to' => 2025, 'archives' => [$row]],
        ['year_from' => 2026, 'year_to' => 2026, 'summary' => ['year_page_count' => 1], 'archives' => [], 'page_failures' => [['url' => '2026-url', 'reason' => 'timeout']]],
        fn (string $path): int => 0,
    );
    expect($merged['archives'])->toBe([$row])
        ->and($merged['summary']['page_failure_count'])->toBe(1);
});

test('topics only refresh preserves information and older attachments', function () {
    $info = ['source_key' => 'info-1', 'published_on' => '2026-01-01', 'source_type' => 'legacy_information', 'assets' => [['path' => 'info.pdf']]];
    $topic = ['source_key' => 'topic-1', 'published_on' => '2026-02-01', 'source_type' => 'legacy_topic', 'assets' => [['path' => 'old.jpg']]];
    $previous = ['summary' => ['source_page_counts' => ['information' => 1, 'topics' => 1]], 'articles' => [$info, $topic]];
    $incoming = ['summary' => ['source_page_counts' => ['topics' => 2]], 'articles' => [array_replace($topic, ['assets' => [['path' => 'new.jpg']]])]];
    $merged = (new JpbaArchiveSnapshotMerger)->mergeContent($previous, $incoming, fn (string $path): int => 100);
    expect($merged['articles'])->toHaveCount(2)
        ->and($merged['summary']['information_count'])->toBe(1)
        ->and($merged['summary']['source_page_counts'])->toBe(['information' => 1, 'topics' => 2])
        ->and($merged['summary']['asset_count'])->toBe(3)
        ->and($merged['summary']['asset_bytes'])->toBe(300)
        ->and($merged['articles'][1])->toBe($info);
});
