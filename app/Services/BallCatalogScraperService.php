<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BallCatalogScraperService
{
    private const USER_AGENT = 'JPBA-Ball-Catalog/1.0 (+https://www.jpba.or.jp/)';

    /**
     * @return array<string,array{name:string,slug:string,base_url:string,catalog_url:string,sort_order:int}>
     */
    public function sourceDefinitions(): array
    {
        return [
            'abs' => [
                'name' => 'ABS',
                'slug' => 'abs',
                'base_url' => 'https://www.absbowling.co.jp/',
                'catalog_url' => 'https://www.absbowling.co.jp/product-category/cat01/',
                'sort_order' => 10,
            ],
            'hi-sp' => [
                'name' => 'HI-SP',
                'slug' => 'hi-sp',
                'base_url' => 'https://hi-sp.co.jp/',
                'catalog_url' => 'https://hi-sp.co.jp/product-category/ball/',
                'sort_order' => 20,
            ],
            'sunbridge' => [
                'name' => 'サンブリッジ',
                'slug' => 'sunbridge',
                'base_url' => 'https://www.sunbridge-group.com/',
                'catalog_url' => 'https://www.sunbridge-group.com/product/product_cat/ball/',
                'sort_order' => 30,
            ],
        ];
    }

    public function fetchHtml(string $url, int $maxAttempts = 4): string
    {
        $response = $this->request($url, $maxAttempts);
        $body = $response->body();

        if (trim($body) === '') {
            throw new RuntimeException("Empty HTML response: {$url}");
        }

        return $body;
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,next_url:?string}
     */
    public function parseListingPage(
        string $sourceSlug,
        string $html,
        string $pageUrl
    ): array {
        [$document, $xpath] = $this->document($html);

        $items = match ($sourceSlug) {
            'abs' => $this->parseAbs($xpath, $pageUrl),
            'hi-sp' => $this->parseHiSp($xpath, $pageUrl),
            'sunbridge' => $this->parseSunbridge($xpath, $pageUrl),
            default => throw new RuntimeException("Unknown ball catalog source: {$sourceSlug}"),
        };
        $nextUrl = $this->nextPageUrl($xpath, $pageUrl);

        unset($document);

        return [
            'items' => $this->uniqueItems($items),
            'next_url' => $nextUrl,
        ];
    }

    /**
     * @return array{
     *   release_date:?string,
     *   release_text:?string,
     *   release_date_basis:string,
     *   release_date_precision:string,
     *   published_date:?string
     * }
     */
    public function parseProductDetail(
        string $sourceSlug,
        string $html,
        string $pageUrl
    ): array {
        [$document, $xpath] = $this->document($html);
        $publishedDate = $this->publishedDate($html);
        $releaseText = match ($sourceSlug) {
            'abs' => $this->absReleaseText($xpath),
            'hi-sp' => $this->hiSpReleaseText($xpath),
            'sunbridge' => $this->sunbridgeReleaseText($xpath),
            default => throw new RuntimeException(
                "Unknown ball catalog source: {$sourceSlug}"
            ),
        };

        unset($document);

        $parsed = $this->releaseDateFromText($releaseText, $publishedDate);
        if ($parsed['date'] !== null) {
            return [
                'release_date' => $parsed['date'],
                'release_text' => $releaseText,
                'release_date_basis' => 'official_release_text',
                'release_date_precision' => $parsed['precision'],
                'published_date' => $publishedDate,
            ];
        }

        if ($publishedDate) {
            $published = new \DateTimeImmutable($publishedDate);

            return [
                'release_date' => $published->format('Y-m-01'),
                'release_text' => sprintf(
                    '%d年%d月発売',
                    (int) $published->format('Y'),
                    (int) $published->format('n')
                ),
                'release_date_basis' => 'official_publish_month',
                'release_date_precision' => 'month',
                'published_date' => $publishedDate,
            ];
        }

        return [
            'release_date' => null,
            'release_text' => null,
            'release_date_basis' => 'unavailable',
            'release_date_precision' => 'unknown',
            'published_date' => null,
        ];
    }

    /**
     * @return array{path:string,sha256:string,content_type:string,bytes:int}
     */
    public function downloadImage(
        string $sourceSlug,
        string $sourceKey,
        string $imageUrl,
        int $maxAttempts = 4
    ): array {
        $disk = Storage::disk('public');
        $temporaryPath = "ball_catalog/.tmp/{$sourceSlug}-{$sourceKey}.part";
        $disk->makeDirectory('ball_catalog/.tmp');
        $response = $this->requestToFile(
            $imageUrl,
            $disk->path($temporaryPath),
            $maxAttempts
        );
        $contentType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type')
        )[0]));
        $bytes = $disk->size($temporaryPath);

        if ($bytes <= 0) {
            $disk->delete($temporaryPath);
            throw new RuntimeException("Empty image response: {$imageUrl}");
        }
        if (! str_starts_with($contentType, 'image/')) {
            $disk->delete($temporaryPath);
            throw new RuntimeException(
                "Unexpected image content type {$contentType}: {$imageUrl}"
            );
        }
        if ($bytes > 15 * 1024 * 1024) {
            $disk->delete($temporaryPath);
            throw new RuntimeException("Image exceeds 15 MB: {$imageUrl}");
        }

        $extension = $this->imageExtension($imageUrl, $contentType);
        $path = "ball_catalog/{$sourceSlug}/{$sourceKey}.{$extension}";
        $sha256 = hash_file('sha256', $disk->path($temporaryPath));
        $disk->delete($path);
        if (! $disk->move($temporaryPath, $path)) {
            $disk->delete($temporaryPath);
            throw new RuntimeException("Unable to save image: {$path}");
        }

        return [
            'path' => $path,
            'sha256' => $sha256,
            'content_type' => $contentType,
            'bytes' => $bytes,
        ];
    }

    public function sortName(?string $nameKana, string $name): string
    {
        $value = trim((string) ($nameKana ?: $name));
        $value = mb_convert_kana($value, 'asKVC', 'UTF-8');
        $value = mb_strtoupper($value, 'UTF-8');

        return preg_replace('/[\p{Z}\s・･\-‐‑‒–—―_]+/u', '', $value) ?? $value;
    }

    /**
     * @return array{0:DOMDocument,1:DOMXPath}
     */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException('Unable to parse catalog HTML.');
        }

        return [$document, new DOMXPath($document)];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseAbs(DOMXPath $xpath, string $pageUrl): array
    {
        $items = [];
        $nodes = $xpath->query(
            "//li[contains(concat(' ', normalize-space(@class), ' '), ' list-products__item ')]"
        );

        foreach ($nodes ?: [] as $node) {
            $link = $this->first(
                $xpath,
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' list-products__link ')]",
                $node
            );
            $nameNode = $this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-products__ttl ') and not(contains(concat(' ', normalize-space(@class), ' '), ' list-products__ttl-jp '))]",
                $node
            );
            $name = $this->text($nameNode);
            $sourceUrl = $this->canonicalUrl(
                $this->absoluteUrl($this->attribute($link, 'href'), $pageUrl)
            );

            if ($name === '' || $sourceUrl === '') {
                continue;
            }

            $categories = [];
            $categoryNodes = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-grid-01__category-item ')]",
                $node
            );
            foreach ($categoryNodes ?: [] as $categoryNode) {
                $category = $this->text($categoryNode);
                if ($category !== '') {
                    $categories[] = $category;
                }
            }

            $brand = collect($categories)->first(
                fn (string $category): bool => in_array($category, [
                    'ABSボール',
                    'NANODESUボール',
                    'MOTIVボール',
                    '900GLOBALボール',
                    'PRO-amボール',
                ], true)
            );
            $brand = $brand ? preg_replace('/ボール$/u', '', $brand) : null;
            $price = $this->text($this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-products__price ')]",
                $node
            ));
            $image = $this->first($xpath, './/img[1]', $node);
            $imageUrl = $this->bestImageUrl($image, $pageUrl);
            $nameKana = $this->text($this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-products__ttl-jp ')]",
                $node
            ));

            $items[] = $this->item([
                'brand' => $brand,
                'name' => $name,
                'name_kana' => $nameKana ?: null,
                'release_date' => null,
                'source_url' => $sourceUrl,
                'source_image_url' => $imageUrl ?: null,
                'catalog_status' => in_array('アーカイブ', $categories, true)
                    || str_contains($price, '販売終了')
                        ? 'archive'
                        : 'listed',
                'source_payload' => [
                    'categories' => array_values(array_unique($categories)),
                    'price_text' => $price ?: null,
                ],
            ]);
        }

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseHiSp(DOMXPath $xpath, string $pageUrl): array
    {
        $items = [];
        $nodes = $xpath->query(
            "//li[contains(concat(' ', normalize-space(@class), ' '), ' product ') and contains(concat(' ', normalize-space(@class), ' '), ' product_cat-ball ')]"
        );

        foreach ($nodes ?: [] as $node) {
            $link = $this->first(
                $xpath,
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-LoopProduct-link ')]",
                $node
            );
            $name = $this->text($this->first(
                $xpath,
                ".//h2[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-loop-product__title ')]",
                $node
            ));
            $sourceUrl = $this->canonicalUrl(
                $this->absoluteUrl($this->attribute($link, 'href'), $pageUrl)
            );

            if ($name === '' || $sourceUrl === '') {
                continue;
            }

            $brandText = $this->text($this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' pwb-brands-in-loop ')]//a[1]",
                $node
            ));
            $brandParts = preg_split(
                '/\s*[-－](?=[\p{Hiragana}\p{Katakana}\p{Han}])\s*/u',
                $brandText,
                2
            );
            $brand = trim((string) ($brandParts[0] ?? $brandText));
            $image = $this->first(
                $xpath,
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-LoopProduct-link ')]//img[1]",
                $node
            );

            $items[] = $this->item([
                'brand' => $brand ?: null,
                'name' => $name,
                'name_kana' => null,
                'release_date' => null,
                'source_url' => $sourceUrl,
                'source_image_url' => $this->bestImageUrl($image, $pageUrl) ?: null,
                'catalog_status' => 'listed',
                'source_payload' => [
                    'brand_label' => $brandText ?: null,
                ],
            ]);
        }

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseSunbridge(DOMXPath $xpath, string $pageUrl): array
    {
        $items = [];
        $nodes = $xpath->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' post_con ')]"
        );

        foreach ($nodes ?: [] as $node) {
            $link = $this->first(
                $xpath,
                ".//a[contains(@href, '/product/ball/')]",
                $node
            );
            $sourceUrl = $this->canonicalUrl(
                $this->absoluteUrl($this->attribute($link, 'href'), $pageUrl)
            );
            $name = $this->text($this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' ttl_wrap ')]/*[contains(concat(' ', normalize-space(@class), ' '), ' ttl ')]",
                $node
            ));

            if ($name === '' || $sourceUrl === '') {
                continue;
            }

            $releaseText = $this->text($this->first(
                $xpath,
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' info_wrap ')]/*[contains(concat(' ', normalize-space(@class), ' '), ' date ')]",
                $node
            ));
            $image = $this->first(
                $xpath,
                ".//a[contains(@href, '/product/ball/')]//img[1]",
                $node
            );

            $items[] = $this->item([
                'brand' => $this->nullableText($this->first(
                    $xpath,
                    ".//*[contains(concat(' ', normalize-space(@class), ' '), ' ttl_wrap ')]/*[contains(concat(' ', normalize-space(@class), ' '), ' brand ')]",
                    $node
                )),
                'name' => $name,
                'name_kana' => $this->nullableText($this->first(
                    $xpath,
                    ".//*[contains(concat(' ', normalize-space(@class), ' '), ' sub_ttl ')]",
                    $node
                )),
                'release_date' => $this->releaseDate($releaseText),
                'source_url' => $sourceUrl,
                'source_image_url' => $this->bestImageUrl($image, $pageUrl) ?: null,
                'catalog_status' => 'listed',
                'source_payload' => [
                    'release_text' => $releaseText ?: null,
                    'release_date_basis' => 'official_release_text',
                    'release_date_precision' => 'month',
                ],
            ]);
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function item(array $values): array
    {
        $sourceUrl = (string) $values['source_url'];
        $sourceKey = hash('sha256', $sourceUrl);
        $fingerprintValues = [
            'brand' => $values['brand'] ?? null,
            'name' => $values['name'],
            'name_kana' => $values['name_kana'] ?? null,
            'release_date' => $values['release_date'] ?? null,
            'source_image_url' => $values['source_image_url'] ?? null,
            'catalog_status' => $values['catalog_status'],
        ];

        return $values + [
            'source_key' => $sourceKey,
            'sort_name' => $this->sortName(
                $values['name_kana'] ?? null,
                (string) $values['name']
            ),
            'source_fingerprint' => hash(
                'sha256',
                json_encode(
                    $fingerprintValues,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) ?: ''
            ),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function uniqueItems(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['source_key']] = $item;
        }

        return array_values($unique);
    }

    private function nextPageUrl(DOMXPath $xpath, string $pageUrl): ?string
    {
        $node = $this->first(
            $xpath,
            "//*[@rel='next' and (@href)] | //a[contains(concat(' ', normalize-space(@class), ' '), ' next ')]"
        );
        $href = $this->attribute($node, 'href');

        if ($href === '') {
            return null;
        }

        $absolute = $this->canonicalUrl($this->absoluteUrl($href, $pageUrl));
        $currentHost = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        $nextHost = strtolower((string) parse_url($absolute, PHP_URL_HOST));

        return $absolute !== '' && $currentHost === $nextHost ? $absolute : null;
    }

    private function first(
        DOMXPath $xpath,
        string $expression,
        ?DOMNode $context = null
    ): ?DOMNode {
        $nodes = $xpath->query($expression, $context);

        return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function text(?DOMNode $node): string
    {
        if (! $node) {
            return '';
        }

        return trim((string) preg_replace('/[\p{Z}\s]+/u', ' ', $node->textContent));
    }

    private function nullableText(?DOMNode $node): ?string
    {
        $value = $this->text($node);

        return $value !== '' ? $value : null;
    }

    private function attribute(?DOMNode $node, string $attribute): string
    {
        if (! $node instanceof DOMElement) {
            return '';
        }

        return trim($node->getAttribute($attribute));
    }

    private function bestImageUrl(?DOMNode $image, string $pageUrl): string
    {
        if (! $image instanceof DOMElement) {
            return '';
        }

        $candidates = [];
        foreach (['data-srcset', 'srcset'] as $attribute) {
            $srcset = trim($image->getAttribute($attribute));
            if ($srcset === '') {
                continue;
            }
            foreach (explode(',', $srcset) as $candidate) {
                if (preg_match('/^\s*(\S+)(?:\s+(\d+)w)?/u', $candidate, $match)) {
                    $candidates[] = [
                        'url' => $match[1],
                        'width' => isset($match[2]) ? (int) $match[2] : 0,
                    ];
                }
            }
        }

        foreach (['data-src', 'src'] as $attribute) {
            $url = trim($image->getAttribute($attribute));
            if ($url !== '' && ! str_starts_with($url, 'data:')) {
                $candidates[] = ['url' => $url, 'width' => 1];
            }
        }

        if ($candidates === []) {
            return '';
        }

        usort(
            $candidates,
            fn (array $left, array $right): int => $right['width'] <=> $left['width']
        );

        return $this->absoluteUrl((string) $candidates[0]['url'], $pageUrl);
    }

    private function releaseDate(string $text): ?string
    {
        if (! preg_match(
            '/(\d{4})年\s*(\d{1,2})月(?:\s*(\d{1,2})日)?/u',
            $text,
            $match
        )) {
            return null;
        }

        return sprintf(
            '%04d-%02d-%02d',
            (int) $match[1],
            (int) $match[2],
            isset($match[3]) ? (int) $match[3] : 1
        );
    }

    private function absReleaseText(DOMXPath $xpath): string
    {
        $headings = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-desc__ttl ')]"
        );

        foreach ($headings ?: [] as $heading) {
            $label = $this->text($heading);
            if (! str_contains($label, '発売')) {
                continue;
            }

            $value = $this->first($xpath, './following-sibling::*[1]', $heading);
            $text = $this->text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function hiSpReleaseText(DOMXPath $xpath): string
    {
        $nodes = $xpath->query(
            "//*[self::p or self::div or self::span][contains(normalize-space(.), '発売：') or contains(normalize-space(.), '発売:')]"
        );
        $candidates = [];

        foreach ($nodes ?: [] as $node) {
            $text = $this->text($node);
            if ($text === '' || mb_strlen($text) > 300) {
                continue;
            }
            $text = preg_replace('/^.*?発売[：:]\s*/u', '', $text) ?? $text;
            $text = trim($text);
            if ($text !== '') {
                $candidates[] = $text;
            }
        }

        if ($candidates === []) {
            return '';
        }

        usort(
            $candidates,
            fn (string $left, string $right): int => mb_strlen($left) <=> mb_strlen($right)
        );

        return $candidates[0];
    }

    private function sunbridgeReleaseText(DOMXPath $xpath): string
    {
        $node = $this->first(
            $xpath,
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' info_wrap ')]/*[contains(concat(' ', normalize-space(@class), ' '), ' date ')]"
        );

        return $this->text($node);
    }

    /**
     * @return array{date:?string,precision:string}
     */
    private function releaseDateFromText(
        string $text,
        ?string $publishedDate
    ): array {
        if (preg_match(
            '/((?:19|20)\d{2})\s*年\s*(\d{1,2})\s*月(?:\s*(\d{1,2})\s*日)?/u',
            $text,
            $match
        )) {
            return [
                'date' => sprintf(
                    '%04d-%02d-%02d',
                    (int) $match[1],
                    (int) $match[2],
                    isset($match[3]) && $match[3] !== ''
                        ? (int) $match[3]
                        : 1
                ),
                'precision' => isset($match[3]) && $match[3] !== ''
                    ? 'day'
                    : 'month',
            ];
        }

        if (
            $publishedDate
            && preg_match('/(\d{1,2})\s*月/u', $text, $match)
        ) {
            return [
                'date' => sprintf(
                    '%04d-%02d-01',
                    (int) substr($publishedDate, 0, 4),
                    (int) $match[1]
                ),
                'precision' => 'month',
            ];
        }

        return ['date' => null, 'precision' => 'unknown'];
    }

    private function publishedDate(string $html): ?string
    {
        if (preg_match(
            '/["\']datePublished["\']\s*:\s*["\']((?:19|20)\d{2}-\d{2}-\d{2})/i',
            $html,
            $match
        )) {
            return $match[1];
        }
        if (preg_match(
            '/property=["\']article:published_time["\'][^>]*content=["\']((?:19|20)\d{2}-\d{2}-\d{2})/i',
            $html,
            $match
        )) {
            return $match[1];
        }
        if (preg_match(
            '/公開日\s*[：:]\s*((?:19|20)\d{2})[\/.-](\d{1,2})[\/.-](\d{1,2})/u',
            strip_tags($html),
            $match
        )) {
            return sprintf(
                '%04d-%02d-%02d',
                (int) $match[1],
                (int) $match[2],
                (int) $match[3]
            );
        }

        return null;
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https').':'.$url;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $authority = $host.($port ? ':'.$port : '');

        if (str_starts_with($url, '/')) {
            return "{$scheme}://{$authority}{$url}";
        }

        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        $path = $directory.'/'.$url;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return "{$scheme}://{$authority}/".implode('/', $segments);
    }

    private function canonicalUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '/');
        if ($path !== '/') {
            $path = rtrim($path, '/').'/';
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function request(string $url, int $maxAttempts): Response
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < max(1, $maxAttempts)) {
            $attempt++;
            try {
                $response = Http::connectTimeout(15)
                    ->timeout(60)
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept-Language' => 'ja,en;q=0.8',
                        'Accept' => '*/*',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    return $response;
                }

                $lastError = new RuntimeException(
                    "HTTP {$response->status()}: {$url}"
                );
                if ($response->status() !== 429 && $response->status() < 500) {
                    break;
                }

                $retryAfter = max(
                    1,
                    (int) $response->header('Retry-After', (string) (2 ** $attempt))
                );
                usleep(min(30, $retryAfter) * 1_000_000);
            } catch (Throwable $error) {
                $lastError = $error;
                if ($attempt < $maxAttempts) {
                    usleep(min(8, 2 ** $attempt) * 1_000_000);
                }
            }
        }

        throw new RuntimeException(
            "Official catalog request failed after {$attempt} attempt(s): {$url}. "
                .($lastError?->getMessage() ?? 'Unknown error'),
            previous: $lastError
        );
    }

    private function requestToFile(
        string $url,
        string $destination,
        int $maxAttempts
    ): Response {
        $attempt = 0;
        $lastError = null;

        while ($attempt < max(1, $maxAttempts)) {
            $attempt++;
            @unlink($destination);

            try {
                $response = Http::connectTimeout(15)
                    ->timeout(60)
                    ->withoutVerifying()
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept-Language' => 'ja,en;q=0.8',
                        'Accept' => 'image/*',
                    ])
                    ->withOptions(['sink' => $destination])
                    ->get($url);

                if ($response->successful()) {
                    return $response;
                }

                $lastError = new RuntimeException(
                    "HTTP {$response->status()}: {$url}"
                );
                if ($response->status() !== 429 && $response->status() < 500) {
                    break;
                }

                $retryAfter = max(
                    1,
                    (int) $response->header('Retry-After', (string) (2 ** $attempt))
                );
                usleep(min(30, $retryAfter) * 1_000_000);
            } catch (Throwable $error) {
                $lastError = $error;
                if ($attempt < $maxAttempts) {
                    usleep(min(8, 2 ** $attempt) * 1_000_000);
                }
            }
        }

        @unlink($destination);

        throw new RuntimeException(
            "Official image request failed after {$attempt} attempt(s): {$url}. "
                .($lastError?->getMessage() ?? 'Unknown error'),
            previous: $lastError
        );
    }

    private function imageExtension(string $url, string $contentType): string
    {
        $extension = strtolower((string) pathinfo(
            (string) parse_url($url, PHP_URL_PATH),
            PATHINFO_EXTENSION
        ));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
        if (in_array($extension, $allowed, true)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }
}
