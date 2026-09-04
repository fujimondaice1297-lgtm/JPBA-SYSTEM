<?php

namespace App\Services;

use App\Models\Information;
use App\Models\InformationFile;
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

class JpbaPublicContentArchiveService
{
    public const INFORMATION_INDEX_URL = 'https://www.jpba1.jp/information/';

    public const TOPICS_INDEX_URL = 'https://www.jpba.or.jp/topics.html';

    public const SNAPSHOT_PATH = 'resources/data/jpba_public_content_archive.json';

    private const ASSET_ROOT = 'documents/jpba/content-archive/assets';

    /**
     * @param  array<int,string>  $sources
     * @param  null|callable(string):void  $progress
     * @return array<string,mixed>
     */
    public function refresh(array $sources = ['information', 'topics'], bool $downloadAssets = true, ?callable $progress = null): array
    {
        $sources = array_values(array_intersect(['information', 'topics'], $sources));
        if ($sources === []) {
            throw new RuntimeException('取得対象を information または topics から指定してください。');
        }

        $articles = [];
        $sourcePages = [];

        if (in_array('information', $sources, true)) {
            $ids = $this->discoverInformationIds();
            $progress && $progress('INFORMATION: '.$ids->count().'件を取得します。');

            foreach ($ids as $index => $id) {
                $url = self::INFORMATION_INDEX_URL.'detail.html?id='.$id;
                $article = $this->parseInformationHtml($this->fetchHtml($url), $url, $id);
                if ($article !== null) {
                    $articles[] = $article;
                }

                if (($index + 1) % 25 === 0 || $index + 1 === $ids->count()) {
                    $progress && $progress('INFORMATION: '.($index + 1).'/'.$ids->count().'件');
                }
            }

            $sourcePages['information'] = $ids->count();
        }

        if (in_array('topics', $sources, true)) {
            $pages = $this->discoverTopicPages();
            $progress && $progress('トピックス: '.$pages->count().'ページを取得します。');

            foreach ($pages as $index => $url) {
                array_push($articles, ...$this->parseTopicsHtml($this->fetchHtml($url), $url));

                if (($index + 1) % 10 === 0 || $index + 1 === $pages->count()) {
                    $progress && $progress('トピックス: '.($index + 1).'/'.$pages->count().'ページ');
                }
            }

            $sourcePages['topics'] = $pages->count();
        }

        $articles = collect($articles)
            ->unique('source_key')
            ->sortByDesc(fn (array $article): string => $article['published_on'].'|'.$article['source_key'])
            ->values()
            ->all();

        [$articles, $assetSummary, $assetFailures] = $this->archiveAssets(
            $articles,
            $downloadAssets,
            $progress,
        );

        $summary = [
            'article_count' => count($articles),
            'information_count' => count(array_filter($articles, fn (array $row): bool => $row['source_type'] === 'legacy_information')),
            'topic_count' => count(array_filter($articles, fn (array $row): bool => $row['source_type'] === 'legacy_topic')),
            'source_page_counts' => $sourcePages,
            'asset_count' => $assetSummary['asset_count'],
            'asset_bytes' => $assetSummary['asset_bytes'],
            'missing_asset_count' => count($assetFailures),
        ];

        $snapshot = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source_sites' => [self::INFORMATION_INDEX_URL, self::TOPICS_INDEX_URL],
            'summary' => $summary,
            'missing_assets' => $assetFailures,
            'articles' => $articles,
        ];

        File::ensureDirectoryExists(dirname(base_path(self::SNAPSHOT_PATH)));
        File::put(
            base_path(self::SNAPSHOT_PATH),
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        return $snapshot;
    }

    /** @return array<string,mixed> */
    public function loadSnapshot(): array
    {
        $path = base_path(self::SNAPSHOT_PATH);
        if (! File::isFile($path)) {
            throw new RuntimeException('保存済みアーカイブがありません。先に --refresh を実行してください。');
        }

        $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot) || ! is_array($snapshot['articles'] ?? null)) {
            throw new RuntimeException('アーカイブJSONの形式が不正です。');
        }

        return $snapshot;
    }

    /** @return array<string,int> */
    public function applySnapshot(bool $dryRun = false): array
    {
        $snapshot = $this->loadSnapshot();
        $stats = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'preserved_manual_edits' => 0,
            'files' => 0,
        ];

        $apply = function () use ($snapshot, &$stats, $dryRun): void {
            foreach ($snapshot['articles'] as $article) {
                $existing = Information::query()->where('source_key', $article['source_key'])->first();
                $incomingFingerprint = $this->articleFingerprint($article);

                if ($existing && $existing->source_fingerprint) {
                    $currentFingerprint = $this->informationFingerprint($existing);
                    if (! hash_equals((string) $existing->source_fingerprint, $currentFingerprint)) {
                        $stats['preserved_manual_edits']++;

                        continue;
                    }
                }

                $values = [
                    'title' => $article['title'],
                    'body' => $article['body_html'],
                    'body_format' => 'html',
                    'category' => $article['category'],
                    'published_at' => $article['published_on'].' 00:00:00',
                    'starts_at' => $article['published_on'].' 00:00:00',
                    'ends_at' => null,
                    'audience' => 'public',
                    'is_public' => true,
                    'required_training_id' => null,
                    'source_type' => $article['source_type'],
                    'source_key' => $article['source_key'],
                    'source_url' => $article['source_url'],
                    'source_fingerprint' => $incomingFingerprint,
                    'source_synced_at' => now(),
                ];

                if (! $existing) {
                    $stats['created']++;
                } elseif ($this->informationFingerprint($existing) === $incomingFingerprint) {
                    $stats['unchanged']++;
                } else {
                    $stats['updated']++;
                }

                if ($dryRun) {
                    $stats['files'] += count($article['assets'] ?? []);

                    continue;
                }

                $information = $existing ?: new Information;
                $information->forceFill($values)->save();

                Information::query()->whereKey($information->id)->update([
                    'created_at' => $existing?->created_at ?: $article['published_on'].' 00:00:00',
                    'updated_at' => $article['published_on'].' 00:00:00',
                ]);

                $information->files()->delete();
                foreach ($article['assets'] ?? [] as $index => $asset) {
                    InformationFile::query()->create([
                        'information_id' => $information->id,
                        'type' => $asset['type'],
                        'title' => $asset['title'],
                        'file_path' => $asset['path'],
                        'visibility' => 'public',
                        'sort_order' => $index,
                    ]);
                    $stats['files']++;
                }
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return $stats;
    }

    /** @return null|array<string,mixed> */
    public function parseInformationHtml(string $html, string $url, int $id): ?array
    {
        $xpath = $this->xpath($html, false);
        $dateText = $this->firstText($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' info-list__item-date ')]");
        $title = $this->firstText($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' info-detail__ttl ')]");
        $detail = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' info-detail ')]")?->item(0);

        if (! $dateText || ! $title || ! $detail instanceof DOMElement) {
            return null;
        }

        preg_match('/(20\d{2})年(\d{1,2})月(\d{1,2})日/u', $dateText, $dateMatch);
        if ($dateMatch === []) {
            return null;
        }

        $category = $this->normalizeCategory(
            $this->firstText($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' info-list__item-category ')]"),
            $title,
        );
        $publishedOn = sprintf('%04d-%02d-%02d', $dateMatch[1], $dateMatch[2], $dateMatch[3]);

        return $this->article(
            sourceType: 'legacy_information',
            sourceKey: 'legacy-information-'.$id,
            sourceUrl: $url,
            title: $title,
            category: $category,
            publishedOn: $publishedOn,
            bodyHtml: $this->bodyHtml($detail, $url, [$dateText, $title]),
            assets: $this->extractAssets($detail, $url),
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function parseTopicsHtml(string $html, string $url): array
    {
        $xpath = $this->xpath($html, true);
        $nodes = $xpath->query("//table[contains(concat(' ', normalize-space(@class), ' '), ' tbl_border ')]");
        $articles = [];
        $identityOccurrences = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text = $this->cleanText($node->textContent);
            preg_match('/(20\d{2})\/(\d{1,2})\/(\d{1,2})/u', $text, $dateMatch);
            $titleNode = (new DOMXPath($node->ownerDocument))->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' bold ')]",
                $node,
            )?->item(0);
            $title = $titleNode ? $this->cleanText($titleNode->textContent) : '';

            if ($title === '') {
                foreach ((new DOMXPath($node->ownerDocument))->query('.//h5|.//h6', $node) ?: [] as $heading) {
                    $candidate = $this->cleanText($heading->textContent);
                    if (str_starts_with($candidate, $dateMatch[0])) {
                        $remainder = $this->cleanText(substr($candidate, strlen($dateMatch[0])));
                        if ($remainder === '') {
                            continue;
                        }
                        $title = $remainder;
                        break;
                    }
                }
            }

            if ($title === '') {
                foreach ((new DOMXPath($node->ownerDocument))->query('.//h5', $node) ?: [] as $heading) {
                    $candidate = $this->cleanText($heading->textContent);
                    if ($candidate !== '' && ! preg_match('/^20\d{2}\/\d{1,2}\/\d{1,2}$/', $candidate)) {
                        $title = $candidate;
                        break;
                    }
                }
            }

            if ($title === '') {
                foreach ((new DOMXPath($node->ownerDocument))->query('.//p', $node) ?: [] as $paragraph) {
                    $candidate = $this->cleanText($paragraph->textContent);
                    if (! str_starts_with($candidate, $dateMatch[0])) {
                        continue;
                    }
                    $title = $this->cleanText(substr($candidate, strlen($dateMatch[0])));
                    if ($title !== '') {
                        break;
                    }
                }
            }

            if ($dateMatch === [] || $title === '') {
                continue;
            }

            $publishedOn = sprintf('%04d-%02d-%02d', $dateMatch[1], $dateMatch[2], $dateMatch[3]);
            $identity = $publishedOn.'|'.$title;
            $identityOccurrences[$identity] = ($identityOccurrences[$identity] ?? 0) + 1;
            $sourceKey = 'legacy-topic-'.str_replace('-', '', $publishedOn).'-'.substr(sha1($title), 0, 20)
                .'-'.str_pad((string) $identityOccurrences[$identity], 2, '0', STR_PAD_LEFT);

            $articles[] = $this->article(
                sourceType: 'legacy_topic',
                sourceKey: $sourceKey,
                sourceUrl: $url,
                title: $title,
                category: $this->normalizeCategory(null, $title.' '.$text),
                publishedOn: $publishedOn,
                bodyHtml: $this->bodyHtml($node, $url, [$dateMatch[0], $title]),
                assets: $this->extractAssets($node, $url),
            );
        }

        return $articles;
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function discoverInformationIds()
    {
        $firstHtml = $this->fetchHtml(self::INFORMATION_INDEX_URL);
        preg_match_all('/[?&]p=(\d+)/', $firstHtml, $pageMatches);
        $lastPage = max(array_map('intval', $pageMatches[1] ?: [1]));
        $ids = collect();

        for ($page = 1; $page <= $lastPage; $page++) {
            $html = $page === 1
                ? $firstHtml
                : $this->fetchHtml(self::INFORMATION_INDEX_URL.'?p='.$page);
            preg_match_all('/detail\.html\?id=(\d+)/', $html, $matches);
            $ids->push(...array_map('intval', $matches[1]));
        }

        return $ids->unique()->sortDesc()->values();
    }

    /** @return \Illuminate\Support\Collection<int,string> */
    private function discoverTopicPages()
    {
        $html = $this->fetchHtml(self::TOPICS_INDEX_URL);
        // 元ページはShift_JISのため、URL抽出時点ではUTF-8専用の u 修飾子を付けない。
        preg_match_all('/href=["\']([^"\']*topics\/20\d{2}\/(?:topics)?\d{2}\.html)["\']/i', $html, $matches);

        return collect([self::TOPICS_INDEX_URL])
            ->concat(array_map(fn (string $href): string => $this->absoluteUrl(self::TOPICS_INDEX_URL, $href), $matches[1]))
            ->unique()
            ->values();
    }

    private function fetchHtml(string $url): string
    {
        $request = Http::connectTimeout(15)
            ->timeout(60)
            ->retry(3, 300)
            ->withHeaders(['User-Agent' => 'JPBA-New-Site-Archive/1.0']);
        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }
        $response = $request->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("取得できませんでした ({$response->status()}): {$url}");
        }

        return $response->body();
    }

    private function xpath(string $html, bool $legacyShiftJis): DOMXPath
    {
        if ($legacyShiftJis) {
            $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'SJIS'], true) ?: 'SJIS-win';
            if ($encoding !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            }
            $html = preg_replace('/charset\s*=\s*["\']?[^"\'\s;>]+/i', 'charset=UTF-8', $html) ?? $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)?->item(0);

        return $node ? $this->cleanText($node->textContent) : '';
    }

    /** @param array<int,string> $removeLines */
    private function bodyHtml(DOMElement $node, string $baseUrl, array $removeLines): string
    {
        $cloneDocument = new DOMDocument('1.0', 'UTF-8');
        $clone = $cloneDocument->importNode($node, true);
        $cloneDocument->appendChild($clone);
        $xpath = new DOMXPath($cloneDocument);

        foreach (['script', 'style', 'img'] as $tag) {
            $matches = $xpath->query('//'.$tag);
            if ($matches === false) {
                continue;
            }
            for ($index = $matches->length - 1; $index >= 0; $index--) {
                $matches->item($index)?->parentNode?->removeChild($matches->item($index));
            }
        }

        foreach ($xpath->query('//br') ?: [] as $br) {
            $br->parentNode?->insertBefore($cloneDocument->createTextNode("\n"), $br);
        }
        foreach ($xpath->query('//p|//div|//tr|//li|//h1|//h2|//h3|//h4') ?: [] as $block) {
            $block->parentNode?->insertBefore($cloneDocument->createTextNode("\n"), $block);
            $block->parentNode?->insertBefore($cloneDocument->createTextNode("\n"), $block->nextSibling);
        }
        foreach ($xpath->query('//td|//th') ?: [] as $cell) {
            $cell->appendChild($cloneDocument->createTextNode('　'));
        }

        $legacyLinkLabels = collect($xpath->query('//a[@href]') ?: [])
            ->filter(function (DOMNode $link) use ($baseUrl): bool {
                if (! $link instanceof DOMElement) {
                    return false;
                }

                $href = $this->absoluteUrl($baseUrl, trim($link->getAttribute('href')));
                $host = strtolower((string) parse_url($href, PHP_URL_HOST));

                return in_array($host, ['jpba.or.jp', 'www.jpba.or.jp', 'jpba1.jp', 'www.jpba1.jp'], true);
            })
            ->map(fn (DOMNode $link): string => $this->cleanText($link->textContent))
            ->filter()
            ->values()
            ->all();
        $removeLines = array_merge($removeLines, $legacyLinkLabels);

        $lines = preg_split('/\R/u', html_entity_decode(strip_tags($cloneDocument->saveHTML()), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [];
        $lines = collect($lines)
            ->map(fn (string $line): string => $this->cleanText($line))
            ->map(fn (string $line): string => $this->replaceLegacySiteUrls($line))
            ->filter()
            ->reject(function (string $line) use ($removeLines): bool {
                foreach ($removeLines as $remove) {
                    $remove = $this->cleanText($remove);
                    if ($line === $remove || ($remove !== '' && str_contains($line, $remove) && mb_strlen($line) <= mb_strlen($remove) + 15)) {
                        return true;
                    }
                }

                return false;
            })
            ->unique()
            ->values();

        $paragraphs = $lines
            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
            ->implode("\n");

        $externalLinks = collect($xpath->query('//a[@href]') ?: [])
            ->map(function (DOMNode $link) use ($baseUrl): ?array {
                if (! $link instanceof DOMElement) {
                    return null;
                }
                $href = $this->absoluteUrl($baseUrl, trim($link->getAttribute('href')));
                $label = $this->cleanText($link->textContent) ?: '関連ページ';
                $host = strtolower((string) parse_url($href, PHP_URL_HOST));
                if ($href === '' || in_array($host, ['jpba.or.jp', 'www.jpba.or.jp', 'jpba1.jp', 'www.jpba1.jp'], true)) {
                    return null;
                }

                return ['href' => $href, 'label' => $label];
            })
            ->filter()
            ->unique('href')
            ->values();

        if ($externalLinks->isNotEmpty()) {
            $paragraphs .= "\n<h3>関連リンク</h3>\n<ul>";
            foreach ($externalLinks as $link) {
                $paragraphs .= '<li><a href="'.e($link['href']).'" target="_blank" rel="noopener">'.e($link['label']).'</a></li>';
            }
            $paragraphs .= '</ul>';
        }

        return trim($paragraphs) ?: '<p>本文は添付資料をご確認ください。</p>';
    }

    private function replaceLegacySiteUrls(string $line): string
    {
        return trim((string) preg_replace(
            '#https?://(?:www\.)?(?:jpba\.or\.jp|jpba1\.jp)/[^\s　<]*#iu',
            '新サイト内の該当ページ',
            $line,
        ));
    }

    /** @return array<int,array<string,string>> */
    private function extractAssets(DOMElement $node, string $baseUrl): array
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $assets = collect();

        foreach ($xpath->query('.//img[@src]', $node) ?: [] as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }
            $url = $this->absoluteUrl($baseUrl, $image->getAttribute('src'));
            if ($this->isContentImage($url)) {
                $assets->push([
                    'url' => $url,
                    'type' => 'image',
                    'title' => $this->cleanText($image->getAttribute('alt')) ?: $this->assetTitle($url),
                ]);
            }
        }

        foreach ($xpath->query('.//a[@href]', $node) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $url = $this->absoluteUrl($baseUrl, $link->getAttribute('href'));
            if (! $this->isDocumentUrl($url)) {
                continue;
            }
            $assets->push([
                'url' => $url,
                'type' => $this->assetType($url),
                'title' => $this->cleanText($link->textContent) ?: $this->assetTitle($url),
            ]);
        }

        return $assets->filter(fn (array $asset): bool => str_starts_with($asset['url'], 'http'))
            ->unique('url')
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $articles
     * @param  null|callable(string):void  $progress
     * @return array{0:array<int,array<string,mixed>>,1:array<string,int>,2:array<int,array<string,string>>}
     */
    private function archiveAssets(array $articles, bool $download, ?callable $progress): array
    {
        $assetsByUrl = collect($articles)
            ->flatMap(fn (array $article): array => $article['assets'])
            ->unique('url')
            ->keyBy('url');

        if (! $download) {
            foreach ($articles as &$article) {
                $article['assets'] = [];
            }

            return [$articles, ['asset_count' => 0, 'asset_bytes' => 0], []];
        }

        $progress && $progress('画像・添付: '.$assetsByUrl->count().'点を新サイト内へ保存します。');
        $downloaded = [];
        $failures = [];
        $assetBytes = 0;
        $chunks = $assetsByUrl->values()->chunk(20);
        $processed = 0;

        foreach ($chunks as $chunk) {
            $pending = $chunk->filter(function (array $asset) use (&$downloaded, &$assetBytes): bool {
                $path = $this->assetPath($asset['url']);
                $absolute = public_path($path);
                if (File::isFile($absolute) && File::size($absolute) > 0) {
                    $downloaded[$asset['url']] = $asset + ['path' => $path];
                    $assetBytes += File::size($absolute);

                    return false;
                }

                return true;
            })->values();

            $responses = Http::pool(function (Pool $pool) use ($pending): array {
                $requests = [];
                foreach ($pending as $index => $asset) {
                    $request = $pool->as((string) $index)
                        ->withHeaders(['User-Agent' => 'JPBA-New-Site-Archive/1.0'])
                        ->connectTimeout(15)
                        ->timeout(90);
                    if (app()->environment('local')) {
                        $request = $request->withoutVerifying();
                    }
                    $requests[] = $request->get($asset['url']);
                }

                return $requests;
            });

            foreach ($pending as $index => $asset) {
                $response = $responses[(string) $index] ?? null;
                if (! $response instanceof Response || ! $response->successful() || $response->body() === '') {
                    $response = $this->retryAssetDownload($asset['url']);
                }

                if (! $response instanceof Response || ! $response->successful() || $response->body() === '') {
                    $failures[] = [
                        'url' => $asset['url'],
                        'reason' => $response instanceof Response ? 'HTTP '.$response->status() : 'request_failed',
                    ];

                    continue;
                }

                $path = $this->assetPath($asset['url'], $response->header('Content-Type'));
                File::ensureDirectoryExists(dirname(public_path($path)));
                File::put(public_path($path), $response->body());
                $downloaded[$asset['url']] = $asset + ['path' => $path];
                $assetBytes += strlen($response->body());
            }

            $processed += $chunk->count();
            if ($processed % 100 < 20 || $processed === $assetsByUrl->count()) {
                $progress && $progress('画像・添付: '.$processed.'/'.$assetsByUrl->count().'点');
            }
        }

        foreach ($articles as &$article) {
            $article['assets'] = collect($article['assets'])
                ->map(function (array $asset) use ($downloaded): ?array {
                    $stored = $downloaded[$asset['url']] ?? null;
                    if (! $stored) {
                        return null;
                    }

                    return [
                        'path' => $stored['path'],
                        'type' => $stored['type'],
                        'title' => $stored['title'],
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }
        unset($article);

        return [
            $articles,
            ['asset_count' => count($downloaded), 'asset_bytes' => $assetBytes],
            $failures,
        ];
    }

    private function retryAssetDownload(string $url): ?Response
    {
        $lastResponse = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $request = Http::withHeaders(['User-Agent' => 'JPBA-New-Site-Archive/1.0'])
                    ->connectTimeout(20)
                    ->timeout(120);
                if (app()->environment('local')) {
                    $request = $request->withoutVerifying();
                }

                $lastResponse = $request->get($url);
                if ($lastResponse->successful() && $lastResponse->body() !== '') {
                    return $lastResponse;
                }
            } catch (Throwable) {
                $lastResponse = null;
            }

            if ($attempt < 3) {
                usleep($attempt * 500000);
            }
        }

        return $lastResponse;
    }

    /** @return array<string,mixed> */
    private function article(
        string $sourceType,
        string $sourceKey,
        string $sourceUrl,
        string $title,
        string $category,
        string $publishedOn,
        string $bodyHtml,
        array $assets,
    ): array {
        CarbonImmutable::createFromFormat('Y-m-d', $publishedOn);

        return [
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
            'source_url' => $sourceUrl,
            'title' => $title,
            'category' => $category,
            'published_on' => $publishedOn,
            'body_html' => $bodyHtml,
            'assets' => $assets,
        ];
    }

    private function normalizeCategory(?string $category, string $context): string
    {
        $category = $this->cleanText((string) $category);
        $aliases = [
            'NEWS' => 'NEWS',
            '大会' => '大会',
            'TV情報' => 'TV情報',
            'ＴＶ情報' => 'TV情報',
            'インストラクター' => 'ｲﾝｽﾄﾗｸﾀｰ',
            'ｲﾝｽﾄﾗｸﾀｰ' => 'ｲﾝｽﾄﾗｸﾀｰ',
            'イベント' => 'イベント',
        ];
        if (isset($aliases[$category])) {
            return $aliases[$category];
        }

        if (preg_match('/インストラクター|講習会|講習情報/u', $context)) {
            return 'ｲﾝｽﾄﾗｸﾀｰ';
        }
        if (preg_match('/TV|テレビ|放送|WOWOW|CS放送|BS放送/ui', $context)) {
            return 'TV情報';
        }
        if (preg_match('/大会|トーナメント|シーズントライアル|優勝|パーフェクト|800シリーズ|7.?10/u', $context)) {
            return '大会';
        }
        if (preg_match('/イベント|チャリティ|社会貢献/u', $context)) {
            return 'イベント';
        }

        return 'NEWS';
    }

    private function articleFingerprint(array $article): string
    {
        return hash('sha256', json_encode([
            $article['title'],
            $article['body_html'],
            $article['category'],
            $article['published_on'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function informationFingerprint(Information $information): string
    {
        return hash('sha256', json_encode([
            $information->title,
            $information->body,
            $information->category,
            optional($information->published_at)->format('Y-m-d'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{00A0}", "\r", "\t"], [' ', '', ' '], $value);
        $value = preg_replace('/[ ]{2,}/u', ' ', $value) ?? $value;

        return trim($value);
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
        $host = $base['host'] ?? '';
        if (str_starts_with($relative, '//')) {
            return $scheme.':'.str_replace(' ', '%20', $relative);
        }

        $path = str_starts_with($relative, '/')
            ? $relative
            : rtrim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/').'/'.$relative;
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
            $query = '?'.$query;
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        return str_replace(' ', '%20', $scheme.'://'.$host.'/'.implode('/', $segments).$query);
    }

    private function isContentImage(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return preg_match('/\.(?:jpe?g|png|gif|webp)$/', $path) === 1
            && ! preg_match('#/(?:assets|img)/(?:header|footer|common|global|top)/#', $path);
    }

    private function isDocumentUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return preg_match('/\.(?:pdf|docx?|xlsx?|csv|zip|jpe?g|png|gif|webp)$/', $path) === 1;
    }

    private function assetType(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? 'image'
            : ($extension === 'pdf' ? 'pdf' : 'file');
    }

    private function assetTitle(string $url): string
    {
        $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));

        return Str::limit($name !== '' ? $name : '添付ファイル', 250, '');
    }

    private function assetPath(string $url, ?string $contentType = null): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];
        if (! in_array($extension, $allowed, true)) {
            $mime = strtolower(trim(explode(';', (string) $contentType)[0]));
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => 'bin',
            };
        }
        $hash = sha1($url);

        return self::ASSET_ROOT.'/'.substr($hash, 0, 2).'/'.$hash.'.'.$extension;
    }
}
