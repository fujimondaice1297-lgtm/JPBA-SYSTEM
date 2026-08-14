<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Normalizer;
use RuntimeException;

class UsbcApprovedBallSyncService
{
    public const PAGE_URL = 'https://bowl.com/approved-ball-list';

    public const API_URL = 'https://bowl.com/api/approvedballs';

    /**
     * @return array{
     *   official_updated_on:?string,
     *   source_page_url:string,
     *   source_pdf_url:?string,
     *   source_api_url:string,
     *   brand_count:int,
     *   entry_count:int,
     *   source_sha256:string,
     *   entries:array<int,array<string,mixed>>
     * }
     */
    public function fetchSnapshot(int $sleepMs = 100): array
    {
        $pageResponse = $this->client()->get(self::PAGE_URL);
        $pageResponse->throw();
        $html = $pageResponse->body();

        $brands = $this->parseBrands($html);
        if ($brands === []) {
            throw new RuntimeException('USBC公式ページからブランド一覧を取得できませんでした。');
        }

        $entries = [];
        foreach ($brands as $brand) {
            $response = $this->client()->get(self::API_URL, [
                'brandName' => base64_encode($brand),
            ]);
            $response->throw();

            $rows = $response->json();
            if (! is_array($rows)) {
                throw new RuntimeException("USBC APIの応答形式が不正です: {$brand}");
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $entryBrand = trim((string) ($row['brandName'] ?? $brand));
                $name = trim((string) ($row['name'] ?? ''));
                if ($entryBrand === '' || $name === '') {
                    continue;
                }

                $approvedDateText = trim((string) ($row['dateApproved'] ?? ''));
                $fingerprint = hash('sha256', implode('|', [
                    $this->normalizeBrand($entryBrand),
                    $this->normalizeName($name),
                    $approvedDateText,
                ]));

                $entries[$fingerprint] = [
                    'brand' => $entryBrand,
                    'name' => $name,
                    'approved_date_text' => $approvedDateText !== ''
                        ? $approvedDateText
                        : null,
                    'approved_on' => $this->parseApprovedDate($approvedDateText),
                    'image_url' => $this->nullableString($row['image'] ?? null),
                    'normalized_brand' => $this->normalizeBrand($entryBrand),
                    'normalized_name' => $this->normalizeName($name),
                    'source_fingerprint' => $fingerprint,
                ];
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $entries = array_values($entries);
        usort($entries, fn (array $left, array $right): int => [
            $left['normalized_brand'],
            $left['normalized_name'],
            $left['approved_date_text'] ?? '',
        ] <=> [
            $right['normalized_brand'],
            $right['normalized_name'],
            $right['approved_date_text'] ?? '',
        ]);

        $sourceRows = array_map(
            static fn (array $entry): array => [
                'brand' => $entry['brand'],
                'name' => $entry['name'],
                'approved_date_text' => $entry['approved_date_text'],
                'image_url' => $entry['image_url'],
            ],
            $entries
        );

        return [
            'official_updated_on' => $this->parseOfficialUpdatedOn($html),
            'source_page_url' => self::PAGE_URL,
            'source_pdf_url' => $this->parsePdfUrl($html),
            'source_api_url' => self::API_URL,
            'brand_count' => count($brands),
            'entry_count' => count($entries),
            'source_sha256' => hash(
                'sha256',
                json_encode($sourceRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ?: ''
            ),
            'entries' => $entries,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,array<string,array<int,array<string,mixed>>>>
     */
    public function buildIndexes(array $entries): array
    {
        $byBrandName = [];
        $byBrandTokens = [];
        $byName = [];

        foreach ($entries as $entry) {
            $brand = $this->normalizeBrand((string) $entry['brand']);
            $name = $this->normalizeName((string) $entry['name']);
            $tokens = $this->tokenSignature((string) $entry['name']);
            $byBrandName[$brand][$name][] = $entry;
            if ($tokens !== '') {
                $byBrandTokens[$brand][$tokens][] = $entry;
            }
            $byName[$name][] = $entry;
        }

        return [
            'by_brand_name' => $byBrandName,
            'by_brand_tokens' => $byBrandTokens,
            'by_name' => $byName,
        ];
    }

    /**
     * @param array<string,mixed> $catalogBall
     * @param array<string,array<string,array<int,array<string,mixed>>>> $indexes
     * @return array{
     *   status:string,
     *   method:?string,
     *   matched:?array,
     *   candidates:array<int,array<string,mixed>>
     * }
     */
    public function matchCatalogBall(array $catalogBall, array $indexes): array
    {
        $nameCandidates = $this->catalogNameCandidates(
            (string) ($catalogBall['name'] ?? ''),
            (string) ($catalogBall['source_url'] ?? '')
        );
        $brandCandidates = $this->catalogBrandCandidates(
            (string) ($catalogBall['brand'] ?? ''),
            (string) ($catalogBall['manufacturer'] ?? '')
        );

        foreach ($nameCandidates as $candidate) {
            foreach ($brandCandidates as $brand) {
                $matches = $indexes['by_brand_name'][$brand][$candidate['normalized']] ?? [];
                if ($matches !== []) {
                    return [
                        'status' => 'matched',
                        'method' => $candidate['method'],
                        'matched' => $matches[0],
                        'candidates' => array_slice($matches, 0, 5),
                    ];
                }
            }
        }

        foreach ($nameCandidates as $candidate) {
            $tokens = $this->tokenSignature($candidate['raw']);
            if ($tokens === '') {
                continue;
            }
            foreach ($brandCandidates as $brand) {
                $matches = $indexes['by_brand_tokens'][$brand][$tokens] ?? [];
                if ($matches !== []) {
                    return [
                        'status' => 'matched',
                        'method' => 'token_order_'.$candidate['method'],
                        'matched' => $matches[0],
                        'candidates' => array_slice($matches, 0, 5),
                    ];
                }
            }
        }

        $catalogReleaseYear = $this->yearFromDate(
            $catalogBall['release_date'] ?? null
        );
        $tokenSubsetMatches = [];
        foreach ($nameCandidates as $candidate) {
            $catalogTokens = $this->tokenParts($candidate['raw']);
            if (count($catalogTokens) < 2) {
                continue;
            }
            foreach ($brandCandidates as $brand) {
                foreach (
                    $indexes['by_brand_name'][$brand] ?? []
                    as $entries
                ) {
                    foreach ($entries as $entry) {
                        $officialTokens = $this->tokenParts(
                            (string) ($entry['name'] ?? '')
                        );
                        if (
                            array_diff($catalogTokens, $officialTokens) !== []
                            || ! $this->yearsAligned(
                                $catalogReleaseYear,
                                $this->yearFromDate($entry['approved_on'] ?? null),
                                3
                            )
                        ) {
                            continue;
                        }

                        $tokenSubsetMatches[$entry['source_fingerprint']] = $entry;
                    }
                }
            }
        }
        if ($tokenSubsetMatches !== []) {
            $tokenSubsetMatches = array_values($tokenSubsetMatches);

            return [
                'status' => 'matched',
                'method' => 'official_variant_tokens',
                'matched' => $tokenSubsetMatches[0],
                'candidates' => array_slice($tokenSubsetMatches, 0, 5),
            ];
        }

        $variantMatches = [];
        foreach ($nameCandidates as $candidate) {
            $catalogName = $candidate['normalized'];
            if (strlen($catalogName) < 5) {
                continue;
            }
            foreach ($brandCandidates as $brand) {
                foreach (
                    $indexes['by_brand_name'][$brand] ?? []
                    as $officialName => $entries
                ) {
                    if (
                        ! str_starts_with($officialName, $catalogName)
                        && ! str_starts_with($catalogName, $officialName)
                    ) {
                        continue;
                    }

                    foreach ($entries as $entry) {
                        $allColors = preg_match(
                            '/\(\s*All\s+Colors\s*\)/i',
                            (string) ($entry['name'] ?? '')
                        ) === 1;
                        $officialYear = $this->yearFromDate(
                            $entry['approved_on'] ?? null
                        );
                        if (
                            ! $allColors
                            && ! $this->yearsAligned(
                                $catalogReleaseYear,
                                $officialYear,
                                3
                            )
                        ) {
                            continue;
                        }

                        $variantMatches[$entry['source_fingerprint']] = $entry;
                    }
                }
            }
        }
        if ($variantMatches !== []) {
            $variantMatches = array_values($variantMatches);

            return [
                'status' => 'matched',
                'method' => 'official_variant',
                'matched' => $variantMatches[0],
                'candidates' => array_slice($variantMatches, 0, 5),
            ];
        }

        foreach ($nameCandidates as $candidate) {
            $matches = $indexes['by_name'][$candidate['normalized']] ?? [];
            $brands = array_values(array_unique(array_column(
                $matches,
                'normalized_brand'
            )));
            if ($matches !== [] && count($brands) === 1) {
                return [
                    'status' => 'matched',
                    'method' => 'unique_name_'.$candidate['method'],
                    'matched' => $matches[0],
                    'candidates' => array_slice($matches, 0, 5),
                ];
            }
            if (count($brands) > 1) {
                return [
                    'status' => 'ambiguous',
                    'method' => 'duplicate_name_'.$candidate['method'],
                    'matched' => null,
                    'candidates' => array_slice($matches, 0, 5),
                ];
            }
        }

        $suggestions = $this->fuzzySuggestions(
            $nameCandidates,
            $brandCandidates,
            $indexes['by_brand_name'],
            $catalogReleaseYear
        );

        return [
            'status' => $suggestions === [] ? 'not_listed' : 'ambiguous',
            'method' => $suggestions === [] ? null : 'similar_name',
            'matched' => null,
            'candidates' => $suggestions,
        ];
    }

    public function normalizeBrand(string $value): string
    {
        return $this->normalizeText($value);
    }

    public function normalizeName(string $value): string
    {
        $value = preg_replace(
            '/^\s*(?:PRO[\s-]*am|Nanodesu)\s+/i',
            '',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/^\s*HB\s*\(\s*Honey\s+Badger\s*\)\s*/i',
            'Honey Badger ',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/^\s*RG\s+Jester\b/i',
            'Jester',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/\bBE\s*\(\s*Black\s+Edition\s*\)/i',
            'Black Edition',
            $value
        ) ?? $value;
        $value = preg_replace('/\bXtreme\b/i', 'Extreme', $value) ?? $value;
        $value = preg_replace(
            '/\bBLACK\s+AND\s+BLUE\b/i',
            'Black Blue',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/\(\s*All\s+Colors\s*\)\s*$/i',
            '',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/Π\s*\(\s*Pi\s*\)/iu',
            'Pi',
            $value
        ) ?? $value;
        $value = strtr($value, [
            'Ⅰ' => ' I ',
            'Ⅱ' => ' II ',
            'Ⅲ' => ' III ',
            'Ⅳ' => ' IV ',
            'Ⅴ' => ' V ',
            'Ⅵ' => ' VI ',
            'Ⅶ' => ' VII ',
            'Ⅷ' => ' VIII ',
            'Ⅸ' => ' IX ',
            'Ⅹ' => ' X ',
            '™' => '',
            '®' => '',
            '©' => '',
            '$' => 'S',
            'ν' => 'V',
            'Ν' => 'V',
        ]);
        $value = preg_replace_callback(
            '/\b(VIII|VII|VI|IV|V|III|II|IX|X)\b/i',
            static fn (array $match): string => (string) [
                'I' => 1,
                'II' => 2,
                'III' => 3,
                'IV' => 4,
                'V' => 5,
                'VI' => 6,
                'VII' => 7,
                'VIII' => 8,
                'IX' => 9,
                'X' => 10,
            ][strtoupper($match[1])],
            $value
        ) ?? $value;

        $normalized = $this->normalizeText($value);

        return strtr($normalized, [
            'ABUSOLUTE' => 'ABSOLUTE',
            'RURLES' => 'RULES',
            'DEFFIANT' => 'DEFIANT',
            'CRIPTO' => 'CRYPTO',
            'CLASSIS' => 'CLASSIC',
        ]);
    }

    /**
     * @return array<int,string>
     */
    public function parseBrands(string $html): array
    {
        if (! preg_match(
            '/<select[^>]+id=["\']ddlApprovedBallList["\'][^>]*>(.*?)<\/select>/is',
            $html,
            $select
        )) {
            return [];
        }

        preg_match_all(
            '/<option[^>]+value=["\']([^"\']+)["\'][^>]*>/i',
            $select[1],
            $matches
        );

        $brands = array_values(array_unique(array_filter(array_map(
            static fn (string $brand): string => trim(html_entity_decode(
                $brand,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )),
            $matches[1] ?? []
        ), static fn (string $brand): bool => $brand !== '' && $brand !== '-1')));
        sort($brands, SORT_NATURAL | SORT_FLAG_CASE);

        return $brands;
    }

    public function parseOfficialUpdatedOn(string $html): ?string
    {
        if (! preg_match(
            '/Updated\s+date\s*:(.{0,160})/is',
            $html,
            $section
        )) {
            return null;
        }

        $text = html_entity_decode(
            strip_tags($section[1]),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        if (! preg_match(
            '/([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/',
            $text,
            $match
        )) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!n/j/Y', $match[1]);

        return $date?->format('Y-m-d') ?: null;
    }

    public function parsePdfUrl(string $html): ?string
    {
        if (! preg_match(
            '/href=["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\'][^>]*>\s*PDF\s+Format/is',
            $html,
            $match
        )) {
            return null;
        }

        $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($url, '/')) {
            return 'https://bowl.com'.$url;
        }

        return $url;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent('JPBA Ball Catalog Sync/1.0')
            ->withoutVerifying()
            ->timeout(30)
            ->retry(3, 400);
    }

    private function parseApprovedDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['F j, Y', 'M j, Y', 'F d, Y', 'M d, Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        if (preg_match('/^([A-Za-z]{3,9})-\'?([0-9]{2})$/', $value, $match)) {
            $date = DateTimeImmutable::createFromFormat(
                '!M-y',
                substr($match[1], 0, 3).'-'.$match[2]
            );
            if ($date !== false) {
                return $date->format('Y-m-01');
            }
        }

        return null;
    }

    /**
     * @return array<int,array{raw:string,normalized:string,method:string}>
     */
    private function catalogNameCandidates(string $name, string $sourceUrl): array
    {
        $values = [];
        foreach ($this->expandCombinedName($name) as $expanded) {
            $values[] = ['value' => $expanded, 'method' => 'catalog_name'];
        }
        foreach ($this->catalogManualAliases($name) as $alias) {
            $values[] = ['value' => $alias, 'method' => 'verified_name_alias'];
        }

        $path = trim((string) parse_url($sourceUrl, PHP_URL_PATH), '/');
        $slug = $path !== '' ? rawurldecode((string) basename($path)) : '';
        if ($slug !== '') {
            $slugName = str_replace(['-', '_'], ' ', $slug);
            foreach ($this->expandCombinedName($slugName) as $expanded) {
                $values[] = [
                    'value' => $expanded,
                    'method' => 'source_url_slug',
                ];
            }
            $withoutTrailingCounter = preg_replace('/[-_][0-9]+$/', '', $slug);
            if ($withoutTrailingCounter && $withoutTrailingCounter !== $slug) {
                $values[] = [
                    'value' => str_replace(['-', '_'], ' ', $withoutTrailingCounter),
                    'method' => 'source_url_slug_without_counter',
                ];
            }
        }

        $result = [];
        foreach ($values as $value) {
            $normalized = $this->normalizeName($value['value']);
            if ($normalized === '' || isset($result[$normalized])) {
                continue;
            }
            $result[$normalized] = [
                'raw' => $value['value'],
                'normalized' => $normalized,
                'method' => $value['method'],
            ];
        }

        return array_values($result);
    }

    /**
     * @return array<int,string>
     */
    private function catalogBrandCandidates(string $brand, string $manufacturer): array
    {
        $brandKey = $this->normalizeBrand($brand);
        $aliases = [
            '900GLOBAL' => ['900 Global'],
            'ABS' => ['ABS'],
            'MOTIV' => ['Motiv'],
            'NANODESU' => ['ABS', 'High Sports'],
            'PROAM' => ['ABS', 'Pro Bowl', 'Pro-Bowls'],
            'HISP' => ['High Sports', 'Storm (High Score Products)', 'ABS'],
            'STORM' => ['Storm', 'Storm (High Score Products)'],
            'ROTOGRIP' => ['Roto Grip'],
            'BRUNSWICK' => ['Brunswick'],
            'DEXTER' => ['Dexter'],
            'DV8' => ['DV8'],
            'EBONITE' => ['Ebonite'],
            'HAMMER' => ['Hammer'],
            'RADICAL' => ['Radical'],
            'SUNBRIDGE' => ['Sunbridge Co., Ltd.'],
            'TRACKBOWLING' => ['Track Inc.'],
        ];

        $values = $aliases[$brandKey] ?? [$brand];
        $values[] = $brand;
        if ($manufacturer !== '') {
            $values[] = $manufacturer;
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $value): string => $this->normalizeBrand($value),
            $values
        ))));
    }

    /**
     * Manufacturer-page names verified against the corresponding USBC row.
     *
     * @return array<int,string>
     */
    private function catalogManualAliases(string $name): array
    {
        return [
            'ABS 60周年記念ボール' => [
                'ABS 60th Anniversary (Tricolor)',
            ],
            'アップビート・パール PA/R/BK' => [
                'Up Beat Passion/Red/Black Pearl',
            ],
            'UP BEAT PEARL BLUE PURPLE' => [
                'Up Beat Blue/Black/Silver Pearl',
                'Up Beat Purple/Pink/Silver Pearl',
            ],
            'UPBEAT PURPLE PINK BLACK' => [
                'Up Beat Purple/Red/Black',
            ],
            'HUSTLE POW' => [
                'Hustle Pink/Onyx/White',
            ],
            'MARVEL MAXX FORCE' => [
                'Marvel Force',
            ],
            'v VALKYRIE™' => [
                'N-Valkyrie',
            ],
            'BLACK WIDOW HYBRID BLACK SAPPHIRE™' => [
                'Black Widow 3.0 Black-Sapphire',
            ],
        ][$name] ?? [];
    }

    /**
     * @param array<int,array{raw:string,normalized:string,method:string}> $nameCandidates
     * @param array<int,string> $brandCandidates
     * @param array<string,array<string,array<int,array<string,mixed>>>> $byBrandName
     * @param int|null $catalogReleaseYear
     * @return array<int,array<string,mixed>>
     */
    private function fuzzySuggestions(
        array $nameCandidates,
        array $brandCandidates,
        array $byBrandName,
        ?int $catalogReleaseYear
    ): array {
        $suggestions = [];

        foreach ($brandCandidates as $brand) {
            foreach ($byBrandName[$brand] ?? [] as $officialName => $entries) {
                foreach ($nameCandidates as $candidate) {
                    $catalogName = $candidate['normalized'];
                    $longest = max(strlen($catalogName), strlen($officialName));
                    if ($longest < 6) {
                        continue;
                    }

                    $distance = levenshtein($catalogName, $officialName);
                    $similarity = 1 - ($distance / $longest);
                    if ($similarity < 0.88 || $distance > 4) {
                        continue;
                    }

                    foreach ($entries as $entry) {
                        $officialYear = $this->yearFromDate(
                            $entry['approved_on'] ?? null
                        );
                        if (
                            $catalogReleaseYear !== null
                            && $officialYear !== null
                            && abs($catalogReleaseYear - $officialYear) > 3
                        ) {
                            continue;
                        }

                        $key = $entry['source_fingerprint'];
                        $suggestions[$key] = $entry + [
                            'similarity' => round($similarity, 4),
                            'catalog_candidate' => $catalogName,
                        ];
                    }
                }
            }
        }

        uasort(
            $suggestions,
            static fn (array $left, array $right): int =>
                ($right['similarity'] ?? 0) <=> ($left['similarity'] ?? 0)
        );

        return array_slice(array_values($suggestions), 0, 5);
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = mb_strtoupper($value, 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    /**
     * @return array<int,string>
     */
    private function expandCombinedName(string $value): array
    {
        $value = trim($value);
        if ($value === '' || ! str_contains($value, '/')) {
            return $value === '' ? [] : [$value];
        }

        $parts = array_map('trim', explode('/', $value));
        $head = array_shift($parts);
        if (
            $head === null
            || ! preg_match('/^(.*\s)([^\s]+)$/u', $head, $match)
        ) {
            return [$value];
        }

        $prefix = $match[1];
        $variants = [$value, $head];
        foreach ($parts as $part) {
            if ($part !== '') {
                $variants[] = $prefix.$part;
            }
        }

        return array_values(array_unique($variants));
    }

    private function tokenSignature(string $value): string
    {
        $parts = $this->tokenParts($value);
        sort($parts, SORT_STRING);

        return implode('|', $parts);
    }

    /**
     * @return array<int,string>
     */
    private function tokenParts(string $value): array
    {
        $value = strtr($value, [
            '™' => ' ',
            '®' => ' ',
            '©' => ' ',
        ]);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = preg_replace(
            '/^\s*(?:PRO[\s-]*am|Nanodesu)\s+/i',
            '',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/^\s*HB\s*\(\s*Honey\s+Badger\s*\)\s*/i',
            'Honey Badger ',
            $value
        ) ?? $value;
        $value = preg_replace('/\bAND\b/i', ' ', $value) ?? $value;
        $value = preg_replace(
            '/\(\s*All\s+Colors\s*\)/i',
            ' ',
            $value
        ) ?? $value;
        $value = strtr($value, [
            'Π' => 'PI',
            'π' => 'PI',
            'ν' => 'V',
            'Ν' => 'V',
            '$' => 'S',
        ]);

        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtoupper($value, 'UTF-8'));
        $parts = array_values(array_filter(array_map(
            fn (string $part): string => $this->normalizeName($part),
            $parts ?: []
        )));
        $ignored = [
            'TM',
            'BOWLING',
            'BALL',
            'LTD',
            'SE',
            'ED',
            'PE',
            'MODEL',
            'BU',
        ];
        $parts = array_values(array_filter(array_map(
            static fn (string $part): string => match ($part) {
                'POLY' => 'POLYESTER',
                default => $part,
            },
            $parts
        ), static fn (string $part): bool => ! in_array($part, $ignored, true)));

        return array_values(array_unique($parts));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function yearFromDate(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if (! preg_match('/^([0-9]{4})-/', $value, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    private function yearsAligned(
        ?int $catalogYear,
        ?int $officialYear,
        int $maximumDifference
    ): bool {
        if ($catalogYear === null || $officialYear === null) {
            return false;
        }

        return abs($catalogYear - $officialYear) <= $maximumDifference;
    }
}
