<?php

namespace App\Console\Commands;

use App\Models\ApprovedBall;
use App\Models\BallManufacturer;
use App\Models\UsbcApprovedBallEntry;
use App\Models\UsbcApprovedBallList;
use App\Services\UsbcApprovedBallSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncUsbcApprovedBallsCommand extends Command
{
    protected $signature = 'balls:sync-usbc-approved
        {--sleep-ms=100 : Delay between USBC brand API requests}
        {--use-latest : Re-match using the latest saved USBC snapshot without network access}
        {--force : Save the official snapshot and catalog match results}
        {--json : Output the final report as JSON}';

    protected $description = 'Synchronize the weekly USBC approved ball list and match it to the JPBA catalog.';

    public function handle(UsbcApprovedBallSyncService $service): int
    {
        $force = (bool) $this->option('force');
        $useLatest = (bool) $this->option('use-latest');
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        if (! $this->option('json')) {
            $this->info(
                ($force ? '実行' : 'ドライラン')
                .'：'.($useLatest
                    ? '保存済みの最新USBC一覧で再照合しています。'
                    : 'USBC公式承認ボール一覧を取得しています。')
            );
        }

        try {
            $snapshot = $useLatest
                ? $this->latestSnapshot()
                : $service->fetchSnapshot($sleepMs);
        } catch (Throwable $error) {
            $this->error('USBC一覧の取得に失敗しました：'.$error->getMessage());

            return self::FAILURE;
        }

        $indexes = $service->buildIndexes($snapshot['entries']);
        $catalogBalls = ApprovedBall::query()
            ->select([
                'id',
                'manufacturer',
                'brand',
                'name',
                'source_url',
                'release_date',
                'source_key',
                'source_fingerprint',
                'source_payload',
                'catalog_status',
            ])
            ->orderBy('id')
            ->get();

        $matches = [];
        $summary = [
            'matched' => 0,
            'ambiguous' => 0,
            'not_listed' => 0,
        ];

        foreach ($catalogBalls as $ball) {
            $match = $service->matchCatalogBall($ball->toArray(), $indexes);
            $matches[$ball->id] = $match;
            $summary[$match['status']]++;
        }

        $catalogPlan = $this->buildCatalogSyncPlan(
            $snapshot['entries'],
            $catalogBalls,
            $matches
        );

        $report = [
            'mode' => $force ? 'executed' : 'dry-run',
            'official_updated_on' => $snapshot['official_updated_on'],
            'source_page_url' => $snapshot['source_page_url'],
            'source_pdf_url' => $snapshot['source_pdf_url'],
            'source_api_url' => $snapshot['source_api_url'],
            'source_sha256' => $snapshot['source_sha256'],
            'brand_count' => $snapshot['brand_count'],
            'official_entry_count' => $snapshot['entry_count'],
            'catalog_count' => $catalogBalls->count(),
            'matched_catalog_count' => $summary['matched'],
            'ambiguous_catalog_count' => $summary['ambiguous'],
            'unlisted_catalog_count' => $summary['not_listed'],
            'official_catalog_existing_count' => $catalogPlan['existing_count'],
            'official_catalog_create_count' => count($catalogPlan['create']),
            'official_catalog_update_count' => count($catalogPlan['update']),
            'official_catalog_archive_count' => count($catalogPlan['archive_ids']),
            'ambiguous' => $this->reportRows($catalogBalls, $matches, 'ambiguous'),
            'not_listed' => $this->reportRows($catalogBalls, $matches, 'not_listed'),
        ];

        if ($force) {
            try {
                DB::transaction(function () use (
                    $snapshot,
                    $report,
                    $catalogBalls,
                    $matches,
                    $catalogPlan,
                    $useLatest
                ): void {
                    $list = UsbcApprovedBallList::query()->updateOrCreate(
                        ['source_sha256' => $snapshot['source_sha256']],
                        [
                            'official_updated_on' => $snapshot['official_updated_on'],
                            'source_page_url' => $snapshot['source_page_url'],
                            'source_pdf_url' => $snapshot['source_pdf_url'],
                            'source_api_url' => $snapshot['source_api_url'],
                            'status' => 'running',
                            'fetched_at' => now(),
                            'completed_at' => null,
                            'brand_count' => $snapshot['brand_count'],
                            'entry_count' => $snapshot['entry_count'],
                            'matched_catalog_count' => 0,
                            'ambiguous_catalog_count' => 0,
                            'unlisted_catalog_count' => 0,
                            'report' => null,
                        ]
                    );

                    if (! $useLatest) {
                        $list->entries()->delete();
                        foreach (array_chunk($snapshot['entries'], 500) as $chunk) {
                            $now = now();
                            UsbcApprovedBallEntry::query()->insert(array_map(
                                static fn (array $entry): array => $entry + [
                                    'list_id' => $list->id,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ],
                                $chunk
                            ));
                        }
                    }

                    foreach ($catalogBalls as $ball) {
                        $match = $matches[$ball->id];
                        $matched = $match['matched'];
                        $sourcePayload = array_merge(
                            (array) $ball->source_payload,
                            array_filter([
                                'usbc_source_fingerprint' => $matched['source_fingerprint'] ?? null,
                                'usbc_approved_date_text' => $matched['approved_date_text'] ?? null,
                            ], static fn (mixed $value): bool => $value !== null && $value !== '')
                        );
                        ApprovedBall::query()
                            ->whereKey($ball->id)
                            ->update([
                                'usbc_match_status' => $match['status'],
                                'usbc_match_method' => $match['method'],
                                'usbc_matched_brand' => $matched['brand'] ?? null,
                                'usbc_matched_name' => $matched['name'] ?? null,
                                'usbc_match_candidates' => $this->compactCandidates(
                                    $match['candidates']
                                ),
                                'usbc_checked_at' => now(),
                                'source_payload' => $sourcePayload,
                            ]);
                    }

                    $this->applyCatalogSyncPlan($catalogPlan, $snapshot);

                    $list->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'matched_catalog_count' => $report['matched_catalog_count'],
                        'ambiguous_catalog_count' => $report['ambiguous_catalog_count'],
                        'unlisted_catalog_count' => $report['unlisted_catalog_count'],
                        'report' => $report,
                    ]);
                });
            } catch (Throwable $error) {
                $this->error('USBC照合結果の保存に失敗しました：'.$error->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRETTY_PRINT
            ) ?: '{}');
        } else {
            $this->newLine();
            $this->info(sprintf(
                '公式更新日 %s / 公式%s件 / カタログ%s件',
                $snapshot['official_updated_on'] ?? '不明',
                number_format($snapshot['entry_count']),
                number_format($catalogBalls->count())
            ));
            $this->line(sprintf(
                '掲載あり %s件 / 要確認 %s件 / 未掲載 %s件',
                number_format($summary['matched']),
                number_format($summary['ambiguous']),
                number_format($summary['not_listed'])
            ));
            $this->line(sprintf(
                '公式全件補完：既存 %s件 / 追加 %s件 / 更新 %s件 / 旧版化 %s件',
                number_format($catalogPlan['existing_count']),
                number_format(count($catalogPlan['create'])),
                number_format(count($catalogPlan['update'])),
                number_format(count($catalogPlan['archive_ids']))
            ));
            if (! $force) {
                $this->comment('保存するには --force を付けて再実行してください。');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @param iterable<int,ApprovedBall> $catalogBalls
     * @param array<int,array<string,mixed>> $matches
     * @return array{
     *   existing_count:int,
     *   create:array<int,array<string,mixed>>,
     *   update:array<int,array{ball_id:int,entry:array<string,mixed>}>,
     *   archive_ids:array<int,int>
     * }
     */
    private function buildCatalogSyncPlan(
        array $entries,
        iterable $catalogBalls,
        array $matches
    ): array {
        $officialByFingerprint = [];
        foreach ($entries as $entry) {
            $fingerprint = trim((string) ($entry['source_fingerprint'] ?? ''));
            if ($fingerprint !== '') {
                $officialByFingerprint[$fingerprint] = $entry;
            }
        }

        $represented = [];
        $mirrors = [];
        foreach ($catalogBalls as $ball) {
            $payload = (array) $ball->source_payload;
            if (($payload['source_type'] ?? null) === 'usbc_approved_list') {
                $fingerprint = trim((string) (
                    $payload['usbc_source_fingerprint']
                    ?? $ball->source_fingerprint
                    ?? ''
                ));
                if ($fingerprint !== '') {
                    $mirrors[$fingerprint][] = $ball;
                }
                continue;
            }

            $match = $matches[$ball->id] ?? null;
            $fingerprint = trim((string) (
                $match['matched']['source_fingerprint']
                ?? ''
            ));
            if (($match['status'] ?? null) === 'matched' && $fingerprint !== '') {
                $represented[$fingerprint] = true;
            }
        }

        $create = [];
        $update = [];
        $archiveIds = [];
        foreach ($officialByFingerprint as $fingerprint => $entry) {
            if (isset($represented[$fingerprint])) {
                foreach ($mirrors[$fingerprint] ?? [] as $duplicateMirror) {
                    $archiveIds[] = (int) $duplicateMirror->id;
                }
                continue;
            }

            $existingMirrors = $mirrors[$fingerprint] ?? [];
            if ($existingMirrors === []) {
                $create[] = $entry;
                continue;
            }

            $primaryMirror = array_shift($existingMirrors);
            $update[] = [
                'ball_id' => (int) $primaryMirror->id,
                'entry' => $entry,
            ];
            foreach ($existingMirrors as $duplicateMirror) {
                $archiveIds[] = (int) $duplicateMirror->id;
            }
        }

        foreach ($mirrors as $fingerprint => $existingMirrors) {
            if (isset($officialByFingerprint[$fingerprint])) {
                continue;
            }
            foreach ($existingMirrors as $staleMirror) {
                $archiveIds[] = (int) $staleMirror->id;
            }
        }

        return [
            'existing_count' => count($officialByFingerprint) - count($create),
            'create' => $create,
            'update' => $update,
            'archive_ids' => array_values(array_unique($archiveIds)),
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $snapshot
     */
    private function applyCatalogSyncPlan(array $plan, array $snapshot): void
    {
        $manufacturer = BallManufacturer::query()->updateOrCreate(
            ['name' => 'USBC'],
            [
                'slug' => 'usbc',
                'base_url' => 'https://bowl.com/',
                'catalog_url' => (string) $snapshot['source_page_url'],
                'is_active' => true,
                'sort_order' => 40,
            ]
        );

        $now = now();
        $rows = [];
        foreach ($plan['create'] as $entry) {
            $rows[] = $this->officialCatalogRow(
                $entry,
                $snapshot,
                (int) $manufacturer->id,
                $now,
                $now
            );
        }
        foreach ($plan['update'] as $item) {
            $existing = ApprovedBall::query()->find($item['ball_id']);
            $rows[] = $this->officialCatalogRow(
                $item['entry'],
                $snapshot,
                (int) $manufacturer->id,
                $existing?->first_seen_at ?? $now,
                $now
            );
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('approved_balls')->upsert(
                $chunk,
                ['source_key'],
                [
                    'name',
                    'manufacturer',
                    'manufacturer_id',
                    'brand',
                    'sort_name',
                    'usbc_match_status',
                    'usbc_match_method',
                    'usbc_matched_brand',
                    'usbc_matched_name',
                    'usbc_match_candidates',
                    'usbc_checked_at',
                    'release_date',
                    'source_url',
                    'source_image_url',
                    'catalog_status',
                    'source_payload',
                    'source_fingerprint',
                    'last_seen_at',
                    'imported_at',
                    'updated_at',
                ]
            );
        }

        if ($plan['archive_ids'] !== []) {
            ApprovedBall::query()
                ->whereIn('id', $plan['archive_ids'])
                ->update([
                    'catalog_status' => 'archive',
                    'usbc_match_status' => 'not_listed',
                    'usbc_match_method' => 'official_source_missing',
                    'usbc_checked_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private function officialCatalogRow(
        array $entry,
        array $snapshot,
        int $manufacturerId,
        mixed $firstSeenAt,
        mixed $now
    ): array {
        $fingerprint = (string) $entry['source_fingerprint'];
        $payload = [
            'source_type' => 'usbc_approved_list',
            'usbc_source_fingerprint' => $fingerprint,
            'usbc_approved_date_text' => $entry['approved_date_text'] ?? null,
            'official_updated_on' => $snapshot['official_updated_on'] ?? null,
            'release_date_basis' => 'usbc_approved_on',
        ];

        return [
            'name' => (string) $entry['name'],
            'name_kana' => null,
            'manufacturer' => 'USBC',
            'manufacturer_id' => $manufacturerId,
            'brand' => (string) $entry['brand'],
            'sort_name' => mb_strtoupper((string) $entry['name'], 'UTF-8'),
            'approved' => false,
            'usbc_match_status' => 'matched',
            'usbc_match_method' => 'official_source',
            'usbc_matched_brand' => (string) $entry['brand'],
            'usbc_matched_name' => (string) $entry['name'],
            'usbc_match_candidates' => json_encode([], JSON_UNESCAPED_UNICODE),
            'usbc_checked_at' => $now,
            'release_date' => $entry['approved_on'] ?? null,
            'source_key' => hash('sha256', 'usbc-approved-ball|'.$fingerprint),
            'source_url' => (string) $snapshot['source_page_url'],
            'source_image_url' => $entry['image_url'] ?? null,
            'image_path' => null,
            'image_sha256' => null,
            'catalog_status' => 'listed',
            'source_payload' => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'source_fingerprint' => $fingerprint,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $now,
            'imported_at' => $now,
            'image_imported_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function latestSnapshot(): array
    {
        $list = UsbcApprovedBallList::query()
            ->where('status', 'completed')
            ->orderByDesc('official_updated_on')
            ->orderByDesc('id')
            ->with('entries')
            ->first();

        if (! $list) {
            throw new \RuntimeException('保存済みのUSBC公式一覧がありません。');
        }

        return [
            'official_updated_on' => $list->official_updated_on?->format('Y-m-d'),
            'source_page_url' => $list->source_page_url,
            'source_pdf_url' => $list->source_pdf_url,
            'source_api_url' => $list->source_api_url,
            'brand_count' => $list->brand_count,
            'entry_count' => $list->entry_count,
            'source_sha256' => $list->source_sha256,
            'entries' => $list->entries->map(fn ($entry): array => [
                'brand' => $entry->brand,
                'name' => $entry->name,
                'approved_date_text' => $entry->approved_date_text,
                'approved_on' => $entry->approved_on?->format('Y-m-d'),
                'image_url' => $entry->image_url,
                'normalized_brand' => $entry->normalized_brand,
                'normalized_name' => $entry->normalized_name,
                'source_fingerprint' => $entry->source_fingerprint,
            ])->all(),
        ];
    }

    /**
     * @param iterable<int,ApprovedBall> $balls
     * @param array<int,array<string,mixed>> $matches
     * @return array<int,array<string,mixed>>
     */
    private function reportRows(iterable $balls, array $matches, string $status): array
    {
        $rows = [];
        foreach ($balls as $ball) {
            $match = $matches[$ball->id];
            if ($match['status'] !== $status) {
                continue;
            }

            $rows[] = [
                'catalog_ball_id' => $ball->id,
                'manufacturer' => $ball->manufacturer,
                'brand' => $ball->brand,
                'name' => $ball->name,
                'source_url' => $ball->source_url,
                'method' => $match['method'],
                'candidates' => $this->compactCandidates($match['candidates']),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function compactCandidates(array $candidates): array
    {
        return array_map(
            static fn (array $candidate): array => array_filter([
                'brand' => $candidate['brand'] ?? null,
                'name' => $candidate['name'] ?? null,
                'approved_date_text' => $candidate['approved_date_text'] ?? null,
                'similarity' => $candidate['similarity'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            array_slice($candidates, 0, 5)
        );
    }
}
