<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentArchive;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class JpbaApprovedEventArchiveService
{
    public const INDEX_URL = 'https://www.jpba.or.jp/information/tournament/Approval.html';

    public const SNAPSHOT_PATH = 'resources/data/jpba_approved_event_archive.json';

    private const ASSET_ROOT = 'documents/jpba/approved-event-archive/assets';

    /** @return array<string,mixed> */
    public function refresh(
        int $yearFrom = 2015,
        ?int $yearTo = null,
        bool $downloadAssets = true,
        ?callable $progress = null,
    ): array {
        $yearTo ??= (int) now()->year;
        $yearPages = $this->discoverYearPages($yearFrom, $yearTo);
        $progress && $progress("承認イベント年度ページ: {$yearPages->count()}件を取得します。");

        $archives = [];
        $pageFailures = [];
        foreach ($yearPages as $row) {
            try {
                $parsed = $this->parseApprovedEventHtml(
                    $this->fetchHtml($row['url']),
                    $row['url'],
                    (int) $row['year'],
                );
                array_push($archives, ...$parsed);
                $progress && $progress($row['year'].'年度: '.count($parsed).'件');
            } catch (Throwable $exception) {
                $pageFailures[] = ['url' => $row['url'], 'reason' => $exception->getMessage()];
            }
        }

        $archives = collect($archives)
            ->unique('source_key')
            ->sortBy(fn (array $row): string => sprintf('%04d|%s|%s', $row['year'], $row['start_on'] ?? '9999-12-31', $row['title']))
            ->values()
            ->all();

        [$archives, $assetSummary, $assetFailures] = $this->archiveAssets($archives, $downloadAssets, $progress);
        $snapshot = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source_site' => self::INDEX_URL,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'summary' => [
                'year_page_count' => $yearPages->count(),
                'archive_count' => count($archives),
                'page_failure_count' => count($pageFailures),
                'asset_count' => $assetSummary['asset_count'],
                'asset_bytes' => $assetSummary['asset_bytes'],
                'asset_failure_count' => count($assetFailures),
            ],
            'page_failures' => $pageFailures,
            'asset_failures' => $assetFailures,
            'archives' => $archives,
        ];

        File::ensureDirectoryExists(dirname(base_path(self::SNAPSHOT_PATH)));
        File::put(
            base_path(self::SNAPSHOT_PATH),
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        return $snapshot;
    }

    /** @return array<string,int> */
    public function applySnapshot(bool $dryRun = false): array
    {
        $snapshot = $this->loadSnapshot();
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'preserved_manual_edits' => 0];

        $apply = function () use ($snapshot, $dryRun, &$stats): void {
            foreach ($snapshot['archives'] as $archive) {
                $existing = TournamentArchive::query()->where('source_key', $archive['source_key'])->first();
                $incomingFingerprint = $this->fingerprint($archive);

                if ($existing && $existing->source_fingerprint) {
                    $currentFingerprint = $this->modelFingerprint($existing);
                    if (! hash_equals((string) $existing->source_fingerprint, $currentFingerprint)) {
                        $stats['preserved_manual_edits']++;

                        continue;
                    }
                }

                $values = [
                    'tournament_id' => $this->matchingTournamentId($archive),
                    'classification' => 'approved_event',
                    'year' => $archive['year'],
                    'title' => $archive['title'],
                    'start_on' => $archive['start_on'],
                    'end_on' => $archive['end_on'],
                    'venue_name' => $archive['venue_name'],
                    'organizer_name' => $archive['organizer_name'],
                    'approval_number' => $archive['approval_number'],
                    'status' => $archive['status'],
                    'body_html' => $archive['body_html'],
                    'assets' => $archive['assets'],
                    'source_url' => $archive['source_url'],
                    'source_fingerprint' => $incomingFingerprint,
                    'source_synced_at' => now(),
                    'is_public' => true,
                ];

                if (! $existing) {
                    $stats['created']++;
                } elseif (hash_equals($incomingFingerprint, $this->modelFingerprint($existing))) {
                    $stats['unchanged']++;
                } else {
                    $stats['updated']++;
                }

                if (! $dryRun) {
                    TournamentArchive::query()->updateOrCreate(
                        ['source_key' => $archive['source_key']],
                        $values,
                    );
                }
            }
        };

        $dryRun ? $apply() : DB::transaction($apply);

        return $stats;
    }

    /** @return array<string,mixed> */
    public function loadSnapshot(): array
    {
        $path = base_path(self::SNAPSHOT_PATH);
        if (! File::isFile($path)) {
            throw new RuntimeException('承認イベントアーカイブJSONがありません。先に --refresh を実行してください。');
        }

        $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot['archives'] ?? null)) {
            throw new RuntimeException('承認イベントアーカイブJSONの形式が不正です。');
        }

        return $snapshot;
    }

    /** @return array<int,array<string,mixed>> */
    public function parseApprovedEventHtml(string $html, string $url, int $year): array
    {
        $xpath = $this->xpath($html);
        $archives = [];

        foreach ($xpath->query('//tr') ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $text = $this->cleanText($row->textContent);
            $header = null;
            foreach ($xpath->query('.//p', $row) ?: [] as $paragraph) {
                $header = $this->eventHeader($this->cleanText($paragraph->textContent), $year);
                if ($header !== null) {
                    break;
                }
            }
            $header ??= $this->eventHeader($text, $year);
            if ($header === null) {
                continue;
            }

            $venue = $this->eventVenue($text);
            $organizer = $this->eventOrganizer($text);
            $approvalNumber = $this->approvalNumber($text);
            $assets = $this->extractAssets($row, $url);
            $fragment = $this->firstDescendantId($xpath, $row);
            $sourceUrl = $url.($fragment ? '#'.$fragment : '');
            $sourceKey = 'legacy-approved-event-'.substr(sha1(implode('|', [
                $year,
                $header['start_on'],
                $header['title'],
            ])), 0, 32);

            $archive = [
                'classification' => 'approved_event',
                'year' => $year,
                'title' => $header['title'],
                'start_on' => $header['start_on'],
                'end_on' => $header['end_on'],
                'venue_name' => $venue,
                'organizer_name' => $organizer,
                'approval_number' => $approvalNumber,
                'status' => CarbonImmutable::parse($header['start_on'])->isFuture() ? 'scheduled' : 'completed',
                'body_html' => $this->eventBodyHtml($venue, $organizer, $approvalNumber),
                'assets' => $assets,
                'source_key' => $sourceKey,
                'source_url' => $sourceUrl,
            ];

            if (! isset($archives[$sourceKey])
                || count($assets) > count($archives[$sourceKey]['assets'])
                || ($venue !== null && $archives[$sourceKey]['venue_name'] === null)) {
                $archives[$sourceKey] = $archive;
            }
        }

        return array_values($archives);
    }

    /** @return \Illuminate\Support\Collection<int,array{url:string,year:int}> */
    private function discoverYearPages(int $yearFrom, int $yearTo)
    {
        $html = $this->fetchHtml(self::INDEX_URL);
        preg_match_all('/href=["\']([^"\']*tournament(20\d{2})\/event_result\.html(?:\?[^"\']*)?)["\']/i', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match): array => [
                'url' => $this->absoluteUrl(self::INDEX_URL, $match[1]),
                'year' => (int) $match[2],
            ])
            ->filter(fn (array $row): bool => $row['year'] >= $yearFrom && $row['year'] <= $yearTo)
            ->unique('url')
            ->sortBy('year')
            ->values();
    }

    private function fetchHtml(string $url): string
    {
        $request = Http::connectTimeout(15)->timeout(75)->retry(3, 400)
            ->withHeaders(['User-Agent' => 'JPBA-New-Site-Approved-Event-Archive/1.0']);
        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }
        $response = $request->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("取得できませんでした ({$response->status()}): {$url}");
        }

        return $response->body();
    }

    private function xpath(string $html): DOMXPath
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'SJIS'], true) ?: 'SJIS-win';
        if ($encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }
        $html = preg_replace('/charset\s*=\s*["\']?[^"\'\s;>]+/i', 'charset=UTF-8', $html) ?? $html;
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /** @return null|array{title:string,start_on:string,end_on:?string} */
    private function eventHeader(string $text, int $fallbackYear): ?array
    {
        if (preg_match('/^(?:(20\d{2})\s*[\/年]\s*)?(\d{1,2})\s*[\/月]\s*(\d{1,2})日?(?:\s*[（(][^）)]*[）)])?(?:\s*[-～〜]\s*(?:(\d{1,2})\s*[\/月]\s*)?(\d{1,2})日?(?:\s*[（(][^）)]*[）)])?)?\s*/u', $text, $dateMatch) !== 1) {
            return null;
        }

        try {
            $start = CarbonImmutable::create(
                (int) ($dateMatch[1] ?: $fallbackYear),
                (int) $dateMatch[2],
                (int) $dateMatch[3],
            );
            $end = null;
            if (! empty($dateMatch[5])) {
                $endMonth = ! empty($dateMatch[4]) ? (int) $dateMatch[4] : $start->month;
                $endYear = $start->year;
                if ($endMonth < $start->month || (empty($dateMatch[4]) && (int) $dateMatch[5] < $start->day)) {
                    $nextMonth = $start->addMonthNoOverflow();
                    $endYear = $nextMonth->year;
                    $endMonth = $nextMonth->month;
                }
                $end = CarbonImmutable::create($endYear, $endMonth, (int) $dateMatch[5]);
            }

            $title = trim(mb_substr($text, mb_strlen($dateMatch[0])));
            if (preg_match('/^[「『](.+?)[」』]/u', $title, $titleMatch) === 1) {
                $title = trim($titleMatch[1]);
            } else {
                $title = trim((string) preg_replace('/\s*(?:[＜<]\s*会\s*場\s*[＞>]|開催要項|大会要項|募集要項|参加申込|参加プロ|オイルパターン|レーンコンディション|最終成績|大会結果).*$/u', '', $title));
            }
            if ($title === '') {
                return null;
            }

            return [
                'title' => mb_substr($title, 0, 255),
                'start_on' => $start->toDateString(),
                'end_on' => $end?->toDateString(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function eventVenue(string $text): ?string
    {
        if (preg_match('/[＜<]\s*会\s*場\s*[＞>]\s*(.+?)(?=\s*(?:承認(?:番号|No\.?|№)|開催要項|大会要項|募集要項|参加申込|参加プロ|オイル|レーンコンディション|最終成績|大会結果|寄付御礼|写真提供|$))/u', $text, $match) !== 1) {
            return null;
        }

        return $this->limitedText($match[1]);
    }

    private function eventOrganizer(string $text): ?string
    {
        if (preg_match('/[＜<]\s*(?:主\s*催|共\s*催)\s*[＞>]\s*(.+?)(?=\s*(?:開催要項|大会要項|参加申込|参加プロ|オイル|最終成績|写真提供|$))/u', $text, $match) !== 1) {
            return null;
        }

        return $this->limitedText($match[1]);
    }

    private function approvalNumber(string $text): ?string
    {
        if (preg_match('/承認(?:番号|No\.?|№)\s*[:：]?\s*([A-Za-z0-9-]+)/iu', $text, $match) !== 1) {
            return null;
        }

        return $this->limitedText($match[1], 64);
    }

    private function eventBodyHtml(?string $venue, ?string $organizer, ?string $approvalNumber): string
    {
        $rows = ['<p>JPBA承認イベントとして掲載された大会です。</p>'];
        if ($venue) {
            $rows[] = '<p><strong>会場：</strong>'.e($venue).'</p>';
        }
        if ($organizer) {
            $rows[] = '<p><strong>主催者：</strong>'.e($organizer).'</p>';
        }
        if ($approvalNumber) {
            $rows[] = '<p><strong>承認番号：</strong>'.e($approvalNumber).'</p>';
        }

        return implode("\n", $rows);
    }

    /** @return array<int,array<string,string>> */
    private function extractAssets(DOMElement $row, string $baseUrl): array
    {
        $xpath = new DOMXPath($row->ownerDocument);
        $assets = collect();
        foreach ($xpath->query('.//a[@href]', $row) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $url = $this->absoluteUrl($baseUrl, $link->getAttribute('href'));
            if (! $this->isArchivableUrl($url)) {
                continue;
            }
            $title = preg_replace('/\s*(?:PDF|JPEG?|PNG)\s*[\d,.]+\s*(?:KB|MB)?\s*$/iu', '', $this->cleanText($link->textContent)) ?? '';
            $assets->push([
                'source_url' => $url,
                'type' => $this->assetType($url, $title),
                'title' => $title ?: $this->assetTitle($url),
            ]);
        }

        $assets = $assets->unique('source_url')->values();

        return $assets->where('type', '!=', 'image')
            ->concat($assets->where('type', 'image')->take(12))
            ->values()
            ->all();
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<string,int>,2:array<int,array<string,string>>} */
    private function archiveAssets(array $archives, bool $download, ?callable $progress): array
    {
        $assets = collect($archives)
            ->flatMap(fn (array $row): array => $row['assets'])
            ->unique('source_url')
            ->sortBy(fn (array $asset): int => $asset['type'] === 'image' ? 1 : 0)
            ->values();
        if (! $download) {
            foreach ($archives as &$archive) {
                $archive['assets'] = [];
            }
            unset($archive);

            return [$archives, ['asset_count' => 0, 'asset_bytes' => 0], []];
        }

        $progress && $progress("承認イベント資料・画像: {$assets->count()}点を保存します。");
        $saved = [];
        $failures = [];
        $bytes = 0;
        $processed = 0;

        foreach ($assets->chunk(12) as $chunk) {
            $pending = $chunk->filter(function (array $asset) use (&$saved, &$bytes): bool {
                $path = $this->assetPath($asset['source_url']);
                if ($this->isValidArchivedAsset(public_path($path))) {
                    $saved[$asset['source_url']] = $asset + ['path' => $path];
                    $bytes += File::size(public_path($path));

                    return false;
                }
                File::delete(public_path($path));

                return true;
            })->values();

            $pending = $pending->map(function (array $asset): array {
                $path = $this->assetPath($asset['source_url']);
                $temporaryPath = $path.'.part';
                File::ensureDirectoryExists(dirname(public_path($path)));
                File::delete(public_path($temporaryPath));

                return $asset + ['target_path' => $path, 'temporary_path' => $temporaryPath];
            });

            $responses = Http::pool(function (Pool $pool) use ($pending): array {
                return $pending->map(function (array $asset, int $index) use ($pool) {
                    $request = $pool->as((string) $index)->connectTimeout(12)->timeout(60)
                        ->retry(3, 750, null, false)
                        ->withHeaders(['User-Agent' => 'JPBA-New-Site-Approved-Event-Archive/1.0'])
                        ->withOptions(['sink' => public_path($asset['temporary_path'])]);
                    if (app()->environment('local')) {
                        $request = $request->withoutVerifying();
                    }

                    return $request->get($asset['source_url']);
                })->all();
            });

            foreach ($pending as $index => $asset) {
                $response = $responses[(string) $index] ?? null;
                $temporaryPath = public_path($asset['temporary_path']);
                if (! $response instanceof Response || ! $response->successful() || ! $this->isValidArchivedAsset($temporaryPath)) {
                    File::delete($temporaryPath);
                    $reason = ! $response instanceof Response
                        ? 'request_failed'
                        : ($response->successful() ? 'invalid_content' : 'HTTP '.$response->status());
                    $failures[] = ['url' => $asset['source_url'], 'type' => $asset['type'], 'reason' => $reason];

                    continue;
                }
                File::move($temporaryPath, public_path($asset['target_path']));
                $saved[$asset['source_url']] = [
                    'source_url' => $asset['source_url'],
                    'type' => $asset['type'],
                    'title' => $asset['title'],
                    'path' => $asset['target_path'],
                ];
                $bytes += File::size(public_path($asset['target_path']));
            }

            $processed += $chunk->count();
            if ($processed % 100 < 20 || $processed === $assets->count()) {
                $progress && $progress('承認イベント資料・画像: '.$processed.'/'.$assets->count().'点');
            }
            usleep(250000);
        }

        foreach ($archives as &$archive) {
            $archive['assets'] = collect($archive['assets'])->map(function (array $asset) use ($saved): ?array {
                $stored = $saved[$asset['source_url']] ?? null;

                return $stored ? ['path' => $stored['path'], 'type' => $stored['type'], 'title' => $stored['title']] : null;
            })->filter()->values()->all();
        }
        unset($archive);

        return [$archives, ['asset_count' => count($saved), 'asset_bytes' => $bytes], $failures];
    }

    private function matchingTournamentId(array $archive): ?int
    {
        return Tournament::query()
            ->where('year', $archive['year'])
            ->where('name', $archive['title'])
            ->value('id');
    }

    private function fingerprint(array $archive): string
    {
        return hash('sha256', json_encode([
            $archive['classification'], $archive['year'], $archive['title'],
            $archive['start_on'], $archive['end_on'], $archive['venue_name'],
            $archive['organizer_name'], $archive['approval_number'], $archive['status'],
            $archive['body_html'], $archive['assets'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function modelFingerprint(TournamentArchive $archive): string
    {
        return $this->fingerprint([
            'classification' => $archive->classification,
            'year' => $archive->year,
            'title' => $archive->title,
            'start_on' => $archive->start_on?->toDateString(),
            'end_on' => $archive->end_on?->toDateString(),
            'venue_name' => $archive->venue_name,
            'organizer_name' => $archive->organizer_name,
            'approval_number' => $archive->approval_number,
            'status' => $archive->status,
            'body_html' => $archive->body_html,
            'assets' => $archive->assets ?: [],
        ]);
    }

    private function firstDescendantId(DOMXPath $xpath, DOMElement $row): ?string
    {
        $node = $xpath->query('.//*[@id]', $row)?->item(0);
        $id = $node instanceof DOMElement ? trim($node->getAttribute('id')) : '';

        return $id !== '' ? $id : null;
    }

    private function absoluteUrl(string $baseUrl, string $relative): string
    {
        $relative = html_entity_decode(trim($relative), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($relative === '' || str_starts_with($relative, '#') || preg_match('/^(?:mailto|tel|javascript):/i', $relative)) {
            return '';
        }
        if (preg_match('#^https?://#i', $relative)) {
            return str_replace(' ', '%20', $relative);
        }
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        if (str_starts_with($relative, '//')) {
            return $scheme.':'.str_replace(' ', '%20', $relative);
        }
        $path = str_starts_with($relative, '/') ? $relative : rtrim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/').'/'.$relative;
        $segments = [];
        foreach (explode('/', (string) parse_url($path, PHP_URL_PATH)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segment === '..' ? array_pop($segments) : $segments[] = $segment;
        }

        return str_replace(' ', '%20', $scheme.'://'.($base['host'] ?? '').'/'.implode('/', $segments));
    }

    private function isArchivableUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return in_array($host, ['jpba.or.jp', 'www.jpba.or.jp'], true)
            && preg_match('/\.(?:pdf|docx?|xlsx?|csv|zip|jpe?g|png|gif|webp)$/', $path) === 1
            && ! preg_match('#/(?:img|tools)/(?:header|footer|common|global|top|adobe_)#', $path);
    }

    private function assetType(string $url, string $title): string
    {
        $haystack = mb_strtolower($url.' '.$title);
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(?:jpe?g|png|gif|webp)$/', $path)) {
            return 'image';
        }
        if (str_contains($haystack, 'oil') || str_contains($haystack, 'pattern') || str_contains($haystack, 'オイル') || str_contains($haystack, 'レーンコンディション')) {
            return 'oil_pattern';
        }
        if (str_contains($haystack, 'result') || str_contains($haystack, '成績') || str_contains($haystack, '結果')) {
            return 'result';
        }

        return 'document';
    }

    private function assetTitle(string $url): string
    {
        return Str::limit(rawurldecode(basename((string) parse_url($url, PHP_URL_PATH))) ?: '大会資料', 250, '');
    }

    private function assetPath(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];
        if (! in_array($extension, $allowed, true)) {
            $extension = 'bin';
        }
        $hash = sha1($url);

        return self::ASSET_ROOT.'/'.substr($hash, 0, 2).'/'.$hash.'.'.$extension;
    }

    private function isValidArchivedAsset(string $absolutePath): bool
    {
        if (! File::isFile($absolutePath) || File::size($absolutePath) === 0) {
            return false;
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = (string) fread($handle, 12);
        fclose($handle);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $hasImageSignature = str_starts_with($header, "\xFF\xD8\xFF")
            || str_starts_with($header, "\x89PNG\r\n\x1A\n")
            || str_starts_with($header, 'GIF87a')
            || str_starts_with($header, 'GIF89a')
            || (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP');

        return match ($extension) {
            'pdf' => str_starts_with($header, '%PDF-'),
            'jpg', 'jpeg', 'png', 'gif', 'webp' => $hasImageSignature,
            'zip', 'docx', 'xlsx' => str_starts_with($header, "PK\x03\x04"),
            'doc', 'xls' => str_starts_with($header, "\xD0\xCF\x11\xE0") || str_starts_with($header, "PK\x03\x04"),
            default => true,
        };
    }

    private function limitedText(string $value, int $limit = 255): ?string
    {
        $value = $this->cleanText($value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{00A0}", "\r", "\t"], [' ', '', ' '], $value);

        return trim(preg_replace('/[\s　]+/u', ' ', $value) ?? $value);
    }
}
