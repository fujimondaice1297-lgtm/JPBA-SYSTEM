<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentArchive;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class JpbaTournamentArchiveService
{
    public const INDEX_URL = 'https://www.jpba.or.jp/information/tournament/tournament.html';

    public const SNAPSHOT_PATH = 'resources/data/jpba_tournament_archive.json';

    private const ASSET_ROOT = 'documents/jpba/tournament-archive/assets';

    /** @return array<string,mixed> */
    public function refresh(
        int $yearFrom = 2016,
        ?int $yearTo = null,
        bool $downloadAssets = true,
        ?callable $progress = null,
    ): array {
        $yearTo ??= (int) now()->year;
        $urls = $this->discoverTournamentPages($yearFrom, $yearTo);
        $progress && $progress("大会ページ: {$urls->count()}件を取得します。");

        $archives = [];
        $failures = [];
        foreach ($urls as $index => $row) {
            try {
                $archive = $this->parseTournamentHtml(
                    $this->fetchHtml($row['url']),
                    $row['url'],
                    (int) $row['year'],
                );
                if ($archive !== null) {
                    $archives[] = $archive;
                } else {
                    $failures[] = ['url' => $row['url'], 'reason' => '大会本文または大会名を解析できませんでした'];
                }
            } catch (Throwable $exception) {
                $failures[] = ['url' => $row['url'], 'reason' => $exception->getMessage()];
            }

            if (($index + 1) % 20 === 0 || $index + 1 === $urls->count()) {
                $progress && $progress('大会ページ: '.($index + 1).'/'.$urls->count().'件');
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
                'discovered_page_count' => $urls->count(),
                'archive_count' => count($archives),
                'page_failure_count' => count($failures),
                'asset_count' => $assetSummary['asset_count'],
                'asset_bytes' => $assetSummary['asset_bytes'],
                'asset_failure_count' => count($assetFailures),
            ],
            'page_failures' => $failures,
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
                    'year' => $archive['year'],
                    'title' => $archive['title'],
                    'start_on' => $archive['start_on'],
                    'end_on' => $archive['end_on'],
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
            throw new RuntimeException('大会アーカイブJSONがありません。先に --refresh を実行してください。');
        }

        $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot['archives'] ?? null)) {
            throw new RuntimeException('大会アーカイブJSONの形式が不正です。');
        }

        return $snapshot;
    }

    /** @return null|array<string,mixed> */
    public function parseTournamentHtml(string $html, string $url, int $year): ?array
    {
        $xpath = $this->xpath($html);
        $main = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' td_main ')]")?->item(0);
        if (! $main instanceof DOMElement) {
            return null;
        }

        $title = $this->firstText($xpath, '//title') ?: $this->tournamentTitle($xpath, $main);
        if ($title === '') {
            return null;
        }

        $text = $this->cleanText($main->textContent);
        [$startOn, $endOn] = $this->extractDates($text, $year);
        $assets = $this->extractAssets($main, $url);
        $status = match (true) {
            preg_match('/開催中止|中止とな/u', $text) === 1 => 'cancelled',
            preg_match('/開催延期|延期とな/u', $text) === 1 => 'postponed',
            $startOn !== null && CarbonImmutable::parse($startOn)->isFuture() => 'scheduled',
            default => 'completed',
        };

        return [
            'year' => $year,
            'title' => $title,
            'start_on' => $startOn,
            'end_on' => $endOn,
            'status' => $status,
            'body_html' => $this->bodyHtml($main, $title),
            'assets' => $assets,
            'source_key' => 'legacy-tournament-'.substr(sha1($url), 0, 32),
            'source_url' => $url,
        ];
    }

    /** @return \Illuminate\Support\Collection<int,array{url:string,year:int}> */
    private function discoverTournamentPages(int $yearFrom, int $yearTo)
    {
        $html = $this->fetchHtml(self::INDEX_URL);
        preg_match_all('/href=["\']([^"\']*tournament(20\d{2})\/[^"\']+\.html(?:\?[^"\']*)?)["\']/i', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match): array {
                return ['url' => $this->absoluteUrl(self::INDEX_URL, $match[1]), 'year' => (int) $match[2]];
            })
            ->filter(fn (array $row): bool => $row['year'] >= $yearFrom && $row['year'] <= $yearTo)
            ->unique('url')
            ->values();
    }

    private function fetchHtml(string $url): string
    {
        $request = Http::connectTimeout(15)->timeout(75)->retry(3, 400)
            ->withHeaders(['User-Agent' => 'JPBA-New-Site-Tournament-Archive/1.0']);
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

    private function firstText(DOMXPath $xpath, string $query, ?DOMNode $context = null): string
    {
        $node = $xpath->query($query, $context)?->item(0);

        return $node ? $this->cleanText($node->textContent) : '';
    }

    private function tournamentTitle(DOMXPath $xpath, DOMElement $main): string
    {
        $fallback = '';
        foreach ($xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $main) ?: [] as $heading) {
            $text = $this->cleanText($heading->textContent);
            if ($text === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $text;
            }
            if (preg_match('/^(?:開催要項|日程[＆・]成績|大会記録|資料)$/u', $text) !== 1) {
                return $text;
            }
        }

        return $fallback;
    }

    /** @return array{0:?string,1:?string} */
    private function extractDates(string $text, int $year): array
    {
        $text = preg_replace('/\b20(\d)\1(\d)年/u', '20$1$2年', $text) ?? $text;
        $rangePattern = '/(?:期\s*日\s*)?(20\d{2})年\s*(\d{1,2})月\s*(\d{1,2})日?[^\d]{0,20}[～〜~\-]\s*(?:(20\d{2})年)?\s*(?:(\d{1,2})月)?\s*(\d{1,2})日?/u';
        $singlePattern = '/(?:期\s*日\s*)?(20\d{2})年\s*(\d{1,2})月\s*(\d{1,2})日?/u';
        $hasRange = preg_match($rangePattern, $text, $match) === 1;
        if (! $hasRange && preg_match($singlePattern, $text, $match) !== 1) {
            return [null, null];
        }

        try {
            $start = CarbonImmutable::create((int) $match[1], (int) $match[2], (int) $match[3]);
            $end = null;
            if ($hasRange && ! empty($match[6])) {
                $endYear = ! empty($match[4]) ? (int) $match[4] : (int) $match[1];
                $endMonth = ! empty($match[5]) ? (int) $match[5] : (int) $match[2];
                if (empty($match[5]) && (int) $match[6] < (int) $match[3]) {
                    $nextMonth = $start->addMonthNoOverflow();
                    $endYear = $nextMonth->year;
                    $endMonth = $nextMonth->month;
                } elseif ($endMonth < (int) $match[2] && empty($match[4])) {
                    $endYear++;
                }
                $end = CarbonImmutable::create($endYear, $endMonth, (int) $match[6]);
            }

            return [$start->toDateString(), $end?->toDateString()];
        } catch (Throwable) {
            return [null, null];
        }
    }

    private function bodyHtml(DOMElement $main, string $title): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $clone = $document->importNode($main, true);
        $document->appendChild($clone);
        $xpath = new DOMXPath($document);
        foreach (['script', 'style', 'img'] as $tag) {
            $nodes = $xpath->query('//'.$tag);
            for ($index = ($nodes?->length ?? 0) - 1; $index >= 0; $index--) {
                $nodes?->item($index)?->parentNode?->removeChild($nodes->item($index));
            }
        }
        foreach ($xpath->query('//br|//tr|//p|//div|//h1|//h2|//h3|//h4|//h5|//li') ?: [] as $node) {
            $node->parentNode?->insertBefore($document->createTextNode("\n"), $node);
            $node->parentNode?->insertBefore($document->createTextNode("\n"), $node->nextSibling);
        }
        foreach ($xpath->query('//td|//th') ?: [] as $cell) {
            $cell->appendChild($document->createTextNode('　'));
        }

        $lines = preg_split('/\R/u', html_entity_decode(strip_tags($document->saveHTML()), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [];
        $paragraphs = collect($lines)
            ->map(fn (string $line): string => $this->cleanText($line))
            ->map(fn (string $line): string => trim(preg_replace('#https?://(?:www\.)?jpba(?:1)?\.(?:or\.)?jp/\S*#iu', '', $line) ?? $line))
            ->filter()
            ->reject(fn (string $line): bool => $line === $title || preg_match('/^PAGE TOP$/i', $line) === 1)
            ->unique()
            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
            ->implode("\n");

        return trim($paragraphs) ?: '<p>大会資料は下記の保存ファイルから確認できます。</p>';
    }

    /** @return array<int,array<string,string>> */
    private function extractAssets(DOMElement $main, string $baseUrl): array
    {
        $xpath = new DOMXPath($main->ownerDocument);
        $assets = collect();
        foreach ($xpath->query('.//img[@src]', $main) ?: [] as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }
            $url = $this->absoluteUrl($baseUrl, $image->getAttribute('src'));
            if ($this->isArchivableUrl($url)) {
                $assets->push(['source_url' => $url, 'type' => 'image', 'title' => $this->cleanText($image->getAttribute('alt')) ?: $this->assetTitle($url)]);
            }
        }
        foreach ($xpath->query('.//a[@href]', $main) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $url = $this->absoluteUrl($baseUrl, $link->getAttribute('href'));
            if ($this->isArchivableUrl($url)) {
                $title = $this->cleanText($link->textContent);
                if ($title === '') {
                    $linkedImage = $xpath->query('.//img', $link)?->item(0);
                    if ($linkedImage instanceof DOMElement) {
                        $title = $this->cleanText($linkedImage->getAttribute('alt'))
                            ?: $this->cleanText($linkedImage->getAttribute('title'));
                    }
                }
                $assets->push(['source_url' => $url, 'type' => $this->assetType($url), 'title' => $title ?: $this->assetTitle($url)]);
            }
        }

        $assets = $assets->unique('source_url')->values();

        // 大会資料・成績は全件保存する。写真ギャラリーはページ肥大化を避け、各大会の代表12点まで保存する。
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

        $progress && $progress("大会資料・画像: {$assets->count()}点を保存します。");
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
                        ->withHeaders(['User-Agent' => 'JPBA-New-Site-Tournament-Archive/1.0'])
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
                $progress && $progress('大会資料・画像: '.$processed.'/'.$assets->count().'点');
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
            $archive['year'], $archive['title'], $archive['start_on'], $archive['end_on'],
            $archive['status'], $archive['body_html'], $archive['assets'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function modelFingerprint(TournamentArchive $archive): string
    {
        return $this->fingerprint([
            'year' => $archive->year,
            'title' => $archive->title,
            'start_on' => $archive->start_on?->toDateString(),
            'end_on' => $archive->end_on?->toDateString(),
            'status' => $archive->status,
            'body_html' => $archive->body_html,
            'assets' => $archive->assets ?: [],
        ]);
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

    private function assetType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(?:jpe?g|png|gif|webp)$/', $path)) {
            return 'image';
        }
        if (str_contains($path, '/result/')) {
            return 'result';
        }
        if (str_contains($path, 'oil') || str_contains($path, 'pattern')) {
            return 'oil_pattern';
        }

        return 'document';
    }

    private function assetTitle(string $url): string
    {
        return Str::limit(rawurldecode(basename((string) parse_url($url, PHP_URL_PATH))) ?: '大会資料', 250, '');
    }

    private function assetPath(string $url, ?string $contentType = null): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];
        if (! in_array($extension, $allowed, true)) {
            $extension = str_contains(strtolower((string) $contentType), 'pdf') ? 'pdf' : 'bin';
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

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{00A0}", "\r", "\t"], [' ', '', ' '], $value);

        return trim(preg_replace('/[ ]{2,}/u', ' ', $value) ?? $value);
    }
}
