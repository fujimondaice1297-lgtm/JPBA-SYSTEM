<?php

namespace App\Console\Commands;

use App\Models\ApprovedBall;
use App\Models\BallCatalogImportFailure;
use App\Models\BallCatalogImportRun;
use App\Models\BallManufacturer;
use App\Services\BallCatalogScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncBallCatalogCommand extends Command
{
    protected $signature = 'balls:sync-catalog
        {--manufacturer=* : Source slug(s): abs, hi-sp, sunbridge, or all}
        {--limit-pages= : Stop after this many pages per source for inspection}
        {--sleep-ms=750 : Delay between listing pages}
        {--image-sleep-ms=200 : Delay between image requests}
        {--detail-sleep-ms=250 : Delay between product detail requests}
        {--without-images : Import names and source metadata without downloading photos}
        {--without-release-details : Do not fetch missing release information from product pages}
        {--refresh-release-details : Re-fetch release information already saved}
        {--force : Write catalog rows, photos, and import logs; otherwise dry-run}
        {--json : Output the final report as JSON}';

    protected $description = 'Import every listed bowling ball and photo from the ABS, HI-SP, and Sunbridge official catalogs.';

    public function handle(BallCatalogScraperService $scraper): int
    {
        $definitions = $scraper->sourceDefinitions();
        $selected = $this->selectedSources(array_keys($definitions));
        if ($selected === []) {
            return self::INVALID;
        }

        $force = (bool) $this->option('force');
        $withImages = ! (bool) $this->option('without-images');
        $withReleaseDetails = ! (bool) $this->option('without-release-details');
        $refreshReleaseDetails = (bool) $this->option('refresh-release-details');
        $limitPages = $this->nullablePositiveInt($this->option('limit-pages'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $imageSleepMs = max(0, (int) $this->option('image-sleep-ms'));
        $detailSleepMs = max(0, (int) $this->option('detail-sleep-ms'));
        $reports = [];
        $failedSources = 0;

        if (! $this->option('json')) {
            $this->info(
                ($force ? '実行' : 'ドライラン')
                .'：'.implode('、', $selected)
                .($withImages ? '（画像あり）' : '（画像なし）')
                .($withReleaseDetails ? '（発売時期あり）' : '（発売時期なし）')
            );
        }

        foreach ($selected as $sourceSlug) {
            $definition = $definitions[$sourceSlug];
            $manufacturer = $force
                ? BallManufacturer::query()->updateOrCreate(
                    ['slug' => $sourceSlug],
                    $definition + ['is_active' => true]
                )
                : new BallManufacturer($definition + ['is_active' => true]);

            if ($force) {
                BallCatalogImportRun::query()
                    ->where('manufacturer_id', $manufacturer->id)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'interrupted',
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            $run = $force
                ? BallCatalogImportRun::query()->create([
                    'manufacturer_id' => $manufacturer->id,
                    'mode' => 'full',
                    'status' => 'running',
                    'started_at' => now(),
                    'cursor_url' => $definition['catalog_url'],
                ])
                : null;

            $report = $this->emptyReport(
                $sourceSlug,
                $definition['name'],
                $force,
                $withImages
            );
            $pageUrl = $definition['catalog_url'];
            $seenPages = [];

            if (! $this->option('json')) {
                $this->newLine();
                $this->line("{$definition['name']}：{$pageUrl}");
            }

            try {
                while ($pageUrl !== null) {
                    if (isset($seenPages[$pageUrl])) {
                        throw new \RuntimeException(
                            "Pagination loop detected: {$pageUrl}"
                        );
                    }
                    if ($limitPages !== null && $report['page_count'] >= $limitPages) {
                        $report['stopped_by_page_limit'] = true;
                        break;
                    }

                    $seenPages[$pageUrl] = true;
                    $html = $scraper->fetchHtml($pageUrl);
                    $page = $scraper->parseListingPage(
                        $sourceSlug,
                        $html,
                        $pageUrl
                    );
                    $items = $page['items'];

                    if ($items === []) {
                        throw new \RuntimeException(
                            "No ball cards found on listing page: {$pageUrl}"
                        );
                    }

                    $report['page_count']++;
                    $report['item_count'] += count($items);
                    foreach ($items as $item) {
                        $brand = trim((string) ($item['brand'] ?? ''));
                        if ($brand !== '') {
                            $report['brands'][$brand] = true;
                        }
                        $this->importItem(
                            scraper: $scraper,
                            manufacturer: $manufacturer,
                            run: $run,
                            sourceSlug: $sourceSlug,
                            pageUrl: $pageUrl,
                            item: $item,
                            report: $report,
                            force: $force,
                            withImages: $withImages,
                            imageSleepMs: $imageSleepMs,
                            withReleaseDetails: $withReleaseDetails,
                            refreshReleaseDetails: $refreshReleaseDetails,
                            detailSleepMs: $detailSleepMs
                        );
                    }

                    $pageUrl = $page['next_url'];
                    if ($force && $run) {
                        $this->updateRun($run, $report, $pageUrl);
                    }
                    if (! $this->option('json')) {
                        $this->line(sprintf(
                            '  %dページ / %d件（追加%d・更新%d・発売時期%d・画像%d・画像失敗%d）',
                            $report['page_count'],
                            $report['item_count'],
                            $report['created_count'],
                            $report['updated_count'],
                            $report['release_detail_fetched_count'],
                            $report['image_downloaded_count'],
                            $report['image_failed_count']
                        ));
                    }
                    if ($pageUrl !== null && $sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                }

                $report['status'] = $report['error_count'] > 0
                    ? 'completed_with_errors'
                    : 'completed';
            } catch (Throwable $error) {
                $report['status'] = 'failed';
                $report['error_count']++;
                $report['errors'][] = [
                    'phase' => 'listing',
                    'url' => $pageUrl,
                    'message' => $error->getMessage(),
                ];
                $this->recordFailure(
                    $run,
                    $manufacturer,
                    'listing',
                    $pageUrl,
                    null,
                    null,
                    $error
                );
                $failedSources++;
            }

            $report['brands'] = array_values(array_keys($report['brands']));
            sort($report['brands']);
            if ($force && $run) {
                $run->update([
                    'status' => $report['status'],
                    'completed_at' => now(),
                    'cursor_url' => $pageUrl,
                    'report' => $report,
                ]);
                $this->updateRun($run, $report, $pageUrl);
            }

            $reports[] = $report;
        }

        $final = [
            'mode' => $force ? 'executed' : 'dry-run',
            'with_images' => $withImages,
            'with_release_details' => $withReleaseDetails,
            'source_count' => count($reports),
            'failed_source_count' => $failedSources,
            'totals' => $this->totals($reports),
            'sources' => $reports,
        ];

        if ($force) {
            $final['catalog_total'] = ApprovedBall::query()
                ->whereNotNull('source_key')
                ->count();
            $final['catalog_with_image_total'] = ApprovedBall::query()
                ->whereNotNull('source_key')
                ->whereNotNull('image_path')
                ->count();
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $final,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRETTY_PRINT
            ) ?: '{}');
        } else {
            $this->newLine();
            $this->info(sprintf(
                '合計：%dページ / %d件 / 追加%d / 更新%d / 画像%d / エラー%d',
                $final['totals']['page_count'],
                $final['totals']['item_count'],
                $final['totals']['created_count'],
                $final['totals']['updated_count'],
                $final['totals']['image_downloaded_count'],
                $final['totals']['error_count']
            ));
            if (! $force) {
                $this->comment('DBへ保存するには --force を付けて再実行してください。');
            }
        }

        return $failedSources > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $report
     */
    private function importItem(
        BallCatalogScraperService $scraper,
        BallManufacturer $manufacturer,
        ?BallCatalogImportRun $run,
        string $sourceSlug,
        string $pageUrl,
        array $item,
        array &$report,
        bool $force,
        bool $withImages,
        int $imageSleepMs,
        bool $withReleaseDetails,
        bool $refreshReleaseDetails,
        int $detailSleepMs
    ): void {
        $existing = ApprovedBall::query()
            ->where('source_key', $item['source_key'])
            ->first();
        $matchedByAlternateSource = false;
        if (
            ! $existing
            && ! empty($item['source_image_url'])
        ) {
            $existing = ApprovedBall::query()
                ->where('manufacturer_id', $manufacturer->id)
                ->where('brand', $item['brand'] ?? null)
                ->where('name', $item['name'])
                ->where('source_image_url', $item['source_image_url'])
                ->orderBy('id')
                ->first();
            $matchedByAlternateSource = (bool) $existing;
        }
        $previousImageUrl = $existing?->source_image_url;
        $previousImagePath = $existing?->image_path;

        $needsReleaseDetail = $force
            && $withReleaseDetails
            && empty($item['release_date'])
            && ($refreshReleaseDetails || ! $existing?->release_date);
        if ($needsReleaseDetail) {
            try {
                $detailHtml = $scraper->fetchHtml($item['source_url']);
                $detail = $scraper->parseProductDetail(
                    $sourceSlug,
                    $detailHtml,
                    $item['source_url']
                );
                $item['source_payload'] = array_merge(
                    (array) ($item['source_payload'] ?? []),
                    $detail
                );
                if ($detail['release_date']) {
                    $item['release_date'] = $detail['release_date'];
                    $report['release_detail_fetched_count']++;
                } else {
                    $report['release_detail_unavailable_count']++;
                }
            } catch (Throwable $error) {
                $report['release_detail_failed_count']++;
                $report['error_count']++;
                if (count($report['errors']) < 100) {
                    $report['errors'][] = [
                        'phase' => 'release_detail',
                        'url' => $item['source_url'],
                        'message' => $error->getMessage(),
                    ];
                }
                $this->recordFailure(
                    $run,
                    $manufacturer,
                    'release_detail',
                    $pageUrl,
                    $item['source_url'],
                    null,
                    $error
                );
            }

            if ($detailSleepMs > 0) {
                usleep($detailSleepMs * 1000);
            }
        }

        if ($matchedByAlternateSource) {
            $report['duplicate_merged_count']++;
            $report['unchanged_count']++;
        } elseif (! $existing) {
            $report['created_count']++;
        } elseif ($existing->source_fingerprint !== $item['source_fingerprint']) {
            $report['updated_count']++;
        } else {
            $report['unchanged_count']++;
        }

        if (! $force) {
            return;
        }

        $now = now();
        $existingSourcePayload = (array) ($existing?->source_payload ?? []);
        $payload = [
            'manufacturer_id' => $manufacturer->id,
            'manufacturer' => $manufacturer->name,
            'brand' => $item['brand'] ?? null,
            'name' => $item['name'],
            'name_kana' => $item['name_kana'] ?? null,
            'sort_name' => $item['sort_name'],
            'source_url' => $item['source_url'],
            'source_image_url' => $item['source_image_url'] ?? null,
            'catalog_status' => $item['catalog_status'],
            'source_payload' => array_merge(
                $existingSourcePayload,
                (array) ($item['source_payload'] ?? []),
                ['source_slug' => $sourceSlug, 'listing_page_url' => $pageUrl]
            ),
            'source_fingerprint' => $item['source_fingerprint'],
            'last_seen_at' => $now,
            'imported_at' => $now,
        ];
        if (! empty($item['release_date'])) {
            $payload['release_date'] = $item['release_date'];
        }

        if ($matchedByAlternateSource) {
            $sourcePayload = (array) $existing->source_payload;
            $alternateUrls = array_values(array_unique(array_filter(array_merge(
                (array) ($sourcePayload['alternate_source_urls'] ?? []),
                [$item['source_url']]
            ))));
            $payload = [
                'source_payload' => array_merge($sourcePayload, [
                    'alternate_source_urls' => $alternateUrls,
                ]),
                'last_seen_at' => $now,
                'imported_at' => $now,
            ];
        }

        $ball = DB::transaction(function () use (
            $existing,
            $item,
            $payload,
            $now
        ): ApprovedBall {
            if ($existing) {
                $existing->fill($payload)->save();

                return $existing;
            }

            return ApprovedBall::query()->create($payload + [
                'source_key' => $item['source_key'],
                'approved' => false,
                'first_seen_at' => $now,
            ]);
        });

        if (! $withImages || empty($item['source_image_url'])) {
            return;
        }

        $sameImageUrl = $existing
            && $previousImageUrl === $item['source_image_url'];
        if (
            $sameImageUrl
            && $previousImagePath
            && Storage::disk('public')->exists($previousImagePath)
        ) {
            $report['image_reused_count']++;

            return;
        }

        try {
            $image = $scraper->downloadImage(
                $sourceSlug,
                $item['source_key'],
                $item['source_image_url']
            );
            $ball->forceFill([
                'image_path' => $image['path'],
                'image_sha256' => $image['sha256'],
                'image_imported_at' => now(),
            ])->save();
            $report['image_downloaded_count']++;
        } catch (Throwable $error) {
            $report['image_failed_count']++;
            $report['error_count']++;
            if (count($report['errors']) < 100) {
                $report['errors'][] = [
                    'phase' => 'image',
                    'url' => $item['source_image_url'],
                    'product_url' => $item['source_url'],
                    'message' => $error->getMessage(),
                ];
            }
            $this->recordFailure(
                $run,
                $manufacturer,
                'image',
                $pageUrl,
                $item['source_url'],
                $item['source_image_url'],
                $error
            );
        }

        if ($imageSleepMs > 0) {
            usleep($imageSleepMs * 1000);
        }
    }

    /**
     * @param array<string,mixed> $report
     */
    private function updateRun(
        BallCatalogImportRun $run,
        array $report,
        ?string $cursorUrl
    ): void {
        $run->update([
            'page_count' => $report['page_count'],
            'item_count' => $report['item_count'],
            'created_count' => $report['created_count'],
            'updated_count' => $report['updated_count'],
            'unchanged_count' => $report['unchanged_count'],
            'image_downloaded_count' => $report['image_downloaded_count'],
            'image_reused_count' => $report['image_reused_count'],
            'image_failed_count' => $report['image_failed_count'],
            'error_count' => $report['error_count'],
            'cursor_url' => $cursorUrl,
            'report' => $report,
        ]);
    }

    private function recordFailure(
        ?BallCatalogImportRun $run,
        BallManufacturer $manufacturer,
        string $phase,
        ?string $pageUrl,
        ?string $productUrl,
        ?string $imageUrl,
        Throwable $error
    ): void {
        if (! $run || ! $run->exists) {
            return;
        }

        BallCatalogImportFailure::query()->create([
            'import_run_id' => $run->id,
            'manufacturer_id' => $manufacturer->id,
            'phase' => $phase,
            'page_url' => $pageUrl,
            'product_url' => $productUrl,
            'image_url' => $imageUrl,
            'error_message' => mb_substr($error->getMessage(), 0, 8000),
            'attempt_count' => 4,
        ]);
    }

    /**
     * @param array<int,string> $available
     * @return array<int,string>
     */
    private function selectedSources(array $available): array
    {
        $requested = array_values(array_unique(array_filter(array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            (array) $this->option('manufacturer')
        ))));

        if ($requested === [] || in_array('all', $requested, true)) {
            return $available;
        }

        $invalid = array_values(array_diff($requested, $available));
        if ($invalid !== []) {
            $this->error(
                '不明なメーカー指定です：'.implode('、', $invalid)
                .'。指定可能：'.implode('、', $available)
            );

            return [];
        }

        return $requested;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyReport(
        string $sourceSlug,
        string $name,
        bool $force,
        bool $withImages
    ): array {
        return [
            'source' => $sourceSlug,
            'manufacturer' => $name,
            'status' => 'running',
            'mode' => $force ? 'executed' : 'dry-run',
            'with_images' => $withImages,
            'page_count' => 0,
            'item_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'duplicate_merged_count' => 0,
            'image_downloaded_count' => 0,
            'image_reused_count' => 0,
            'image_failed_count' => 0,
            'release_detail_fetched_count' => 0,
            'release_detail_unavailable_count' => 0,
            'release_detail_failed_count' => 0,
            'error_count' => 0,
            'stopped_by_page_limit' => false,
            'brands' => [],
            'errors' => [],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $reports
     * @return array<string,int>
     */
    private function totals(array $reports): array
    {
        $fields = [
            'page_count',
            'item_count',
            'created_count',
            'updated_count',
            'unchanged_count',
            'duplicate_merged_count',
            'image_downloaded_count',
            'image_reused_count',
            'image_failed_count',
            'release_detail_fetched_count',
            'release_detail_unavailable_count',
            'release_detail_failed_count',
            'error_count',
        ];
        $totals = array_fill_keys($fields, 0);

        foreach ($reports as $report) {
            foreach ($fields as $field) {
                $totals[$field] += (int) ($report[$field] ?? 0);
            }
        }

        return $totals;
    }
}
