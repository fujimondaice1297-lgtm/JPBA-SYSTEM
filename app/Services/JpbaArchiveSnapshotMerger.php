<?php

namespace App\Services;

final class JpbaArchiveSnapshotMerger
{
    /** A topics-only refresh must not remove INFORMATION or older saved articles. */
    public function mergeContent(array $previous, array $incoming, ?callable $fileSize = null): array
    {
        $articles = collect($previous['articles'] ?? [])->keyBy('source_key');
        foreach ($incoming['articles'] ?? [] as $article) {
            $old = $articles->get($article['source_key'], []);
            $article['assets'] = collect($old['assets'] ?? [])->keyBy('path')
                ->merge(collect($article['assets'] ?? [])->keyBy('path'))->values()->all();
            $articles->put($article['source_key'], $article);
        }
        $articles = $articles->sortByDesc(fn (array $row): string => $row['published_on'].'|'.$row['source_key'])->values();
        $assets = $articles->flatMap(fn (array $row): array => $row['assets'] ?? [])->unique('path');
        $failures = collect($previous['missing_assets'] ?? [])->concat($incoming['missing_assets'] ?? [])->unique('url')->values();
        $fileSize ??= static fn (string $path): int => is_file(public_path($path)) ? (int) filesize(public_path($path)) : 0;
        $snapshot = $incoming;
        $snapshot['last_refresh'] = ['summary' => $incoming['summary']];
        $snapshot['articles'] = $articles->all();
        $snapshot['missing_assets'] = $failures->all();
        $snapshot['summary'] = array_replace($incoming['summary'], [
            'article_count' => $articles->count(),
            'information_count' => $articles->where('source_type', 'legacy_information')->count(),
            'topic_count' => $articles->where('source_type', 'legacy_topic')->count(),
            'source_page_counts' => array_replace($previous['summary']['source_page_counts'] ?? [], $incoming['summary']['source_page_counts'] ?? []),
            'asset_count' => $assets->count(),
            'asset_bytes' => $assets->sum(fn (array $asset): int => $fileSize($asset['path'])),
            'missing_asset_count' => $failures->count(),
        ]);

        return $snapshot;
    }

    /** Preserve saved years and documents when refreshing only a selected year. */
    public function merge(array $previous, array $incoming, ?callable $fileSize = null): array
    {
        $archives = collect($previous['archives'] ?? [])->keyBy('source_key');
        foreach ($incoming['archives'] ?? [] as $archive) {
            $old = $archives->get($archive['source_key'], []);
            $archive['assets'] = collect($old['assets'] ?? [])->keyBy('path')
                ->merge(collect($archive['assets'] ?? [])->keyBy('path'))->values()->all();
            $archives->put($archive['source_key'], $archive);
        }
        $archives = $archives->sortBy(fn (array $row): string => sprintf('%04d|%s|%s', $row['year'], $row['start_on'] ?? '9999-12-31', $row['title']))->values();
        $assets = $archives->flatMap(fn (array $row): array => $row['assets'] ?? [])->unique('path');
        $successfulPages = collect($incoming['archives'] ?? [])->pluck('source_url')->all();
        $pageFailures = collect($previous['page_failures'] ?? [])
            ->reject(fn (array $row): bool => in_array($row['url'] ?? null, $successfulPages, true))
            ->concat($incoming['page_failures'] ?? [])->unique('url')->values();
        // Keep unresolved legacy failures visible; a current-year refresh must not hide them.
        $assetFailures = collect($previous['asset_failures'] ?? [])
            ->concat($incoming['asset_failures'] ?? [])->unique('url')->values();
        $fileSize ??= static fn (string $path): int => is_file(public_path($path)) ? (int) filesize(public_path($path)) : 0;
        $snapshot = $incoming;
        $snapshot['year_from'] = min((int) ($previous['year_from'] ?? $incoming['year_from']), (int) $incoming['year_from']);
        $snapshot['year_to'] = max((int) ($previous['year_to'] ?? $incoming['year_to']), (int) $incoming['year_to']);
        $snapshot['last_refresh'] = [
            'year_from' => $incoming['year_from'], 'year_to' => $incoming['year_to'],
            'summary' => $incoming['summary'],
        ];
        $snapshot['archives'] = $archives->all();
        $snapshot['page_failures'] = $pageFailures->all();
        $snapshot['asset_failures'] = $assetFailures->all();
        $snapshot['summary'] = array_replace($incoming['summary'], [
            'archive_count' => $archives->count(),
            'asset_count' => $assets->count(),
            'asset_bytes' => $assets->sum(fn (array $asset): int => $fileSize($asset['path'])),
            'page_failure_count' => $pageFailures->count(),
            'asset_failure_count' => $assetFailures->count(),
        ]);
        if (array_key_exists('year_page_count', $snapshot['summary'])) {
            $snapshot['summary']['year_page_count'] = $archives->pluck('year')->unique()->count();
        }
        if (array_key_exists('discovered_page_count', $snapshot['summary'])) {
            $snapshot['summary']['discovered_page_count'] = $archives->count() + $pageFailures->count();
        }

        return $snapshot;
    }
}
