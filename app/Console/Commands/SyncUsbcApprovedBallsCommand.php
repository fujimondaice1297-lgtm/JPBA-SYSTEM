<?php

namespace App\Console\Commands;

use App\Models\ApprovedBall;
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
                            ]);
                    }

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
            if (! $force) {
                $this->comment('保存するには --force を付けて再実行してください。');
            }
        }

        return self::SUCCESS;
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
