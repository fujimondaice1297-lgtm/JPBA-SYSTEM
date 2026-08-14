<?php

namespace Tests\Unit;

use App\Services\UsbcApprovedBallSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsbcApprovedBallSyncServiceTest extends TestCase
{
    public function test_it_fetches_every_brand_from_the_public_usbc_api(): void
    {
        $page = <<<'HTML'
        <html><body>
          <a href="/files/current.pdf">PDF Format</a>
          <p>Updated date: 7/28/2026</p>
          <select id="ddlApprovedBallList">
            <option value="-1">Select a Brand</option>
            <option value="Roto Grip">Roto Grip</option>
            <option value="Storm">Storm</option>
          </select>
        </body></html>
        HTML;

        Http::fake(function (Request $request) use ($page) {
            if ($request->url() === UsbcApprovedBallSyncService::PAGE_URL) {
                return Http::response($page);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $brand = base64_decode((string) ($query['brandName'] ?? ''), true);

            return Http::response([[
                'brandName' => $brand,
                'name' => $brand === 'Storm' ? 'Phaze II Pearl' : 'Hyped Up',
                'dateApproved' => 'February 04, 2025',
                'image' => 'https://images.example.test/ball.jpg',
            ]]);
        });

        $snapshot = app(UsbcApprovedBallSyncService::class)->fetchSnapshot(0);

        $this->assertSame('2026-07-28', $snapshot['official_updated_on']);
        $this->assertSame('https://bowl.com/files/current.pdf', $snapshot['source_pdf_url']);
        $this->assertSame(2, $snapshot['brand_count']);
        $this->assertSame(2, $snapshot['entry_count']);
        $this->assertSame('2025-02-04', $snapshot['entries'][0]['approved_on']);
    }

    public function test_it_matches_japanese_catalog_names_by_the_official_source_slug(): void
    {
        $service = app(UsbcApprovedBallSyncService::class);
        $entry = [
            'brand' => 'Storm',
            'name' => 'Phaze II Pearl',
            'approved_date_text' => 'Jan-25',
            'approved_on' => '2025-01-01',
            'image_url' => null,
            'normalized_brand' => $service->normalizeBrand('Storm'),
            'normalized_name' => $service->normalizeName('Phaze II Pearl'),
            'source_fingerprint' => hash('sha256', 'storm-phaze-ii-pearl'),
        ];
        $indexes = $service->buildIndexes([$entry]);

        $match = $service->matchCatalogBall([
            'manufacturer' => 'HI-SP',
            'brand' => 'STORM',
            'name' => 'フェイズII パール',
            'source_url' => 'https://hi-sp.co.jp/product/phaze_2-pearl/',
        ], $indexes);

        $this->assertSame('matched', $match['status']);
        $this->assertSame('source_url_slug', $match['method']);
        $this->assertSame('Phaze II Pearl', $match['matched']['name']);
    }

    public function test_it_does_not_auto_approve_a_similar_english_name(): void
    {
        $service = app(UsbcApprovedBallSyncService::class);
        $entry = [
            'brand' => 'Storm',
            'name' => 'Absolute Reign',
            'approved_date_text' => 'Jan-25',
            'approved_on' => '2025-01-01',
            'image_url' => null,
            'normalized_brand' => $service->normalizeBrand('Storm'),
            'normalized_name' => $service->normalizeName('Absolute Reign'),
            'source_fingerprint' => hash('sha256', 'storm-absolute-reign'),
        ];
        $indexes = $service->buildIndexes([$entry]);

        $match = $service->matchCatalogBall([
            'manufacturer' => 'HI-SP',
            'brand' => 'STORM',
            'name' => 'アブソリュート・レグン',
            'source_url' => 'https://hi-sp.co.jp/product/absolute_regn/',
        ], $indexes);

        $this->assertSame('ambiguous', $match['status']);
        $this->assertSame('similar_name', $match['method']);
    }

    public function test_it_matches_a_verified_manufacturer_name_alias(): void
    {
        $service = app(UsbcApprovedBallSyncService::class);
        $entry = [
            'brand' => 'ABS',
            'name' => 'ABS 60th Anniversary (Tricolor)',
            'approved_date_text' => 'December 03, 2024',
            'approved_on' => '2024-12-03',
            'image_url' => null,
            'normalized_brand' => $service->normalizeBrand('ABS'),
            'normalized_name' => $service->normalizeName(
                'ABS 60th Anniversary (Tricolor)'
            ),
            'source_fingerprint' => hash('sha256', 'abs-60th-tricolor'),
        ];
        $indexes = $service->buildIndexes([$entry]);

        $match = $service->matchCatalogBall([
            'manufacturer' => 'ABS',
            'brand' => 'ABS',
            'name' => 'ABS 60周年記念ボール',
            'release_date' => '2024-12-01',
            'source_url' => 'https://www.absbowling.co.jp/product/product-1044/',
        ], $indexes);

        $this->assertSame('matched', $match['status']);
        $this->assertSame('verified_name_alias', $match['method']);
        $this->assertSame(
            'ABS 60th Anniversary (Tricolor)',
            $match['matched']['name']
        );
    }

    public function test_it_does_not_match_a_different_generation_from_a_distant_year(): void
    {
        $service = app(UsbcApprovedBallSyncService::class);
        $entry = [
            'brand' => 'ABS',
            'name' => 'Nanodesu Blue',
            'approved_date_text' => 'June 01, 2004',
            'approved_on' => '2004-06-01',
            'image_url' => null,
            'normalized_brand' => $service->normalizeBrand('ABS'),
            'normalized_name' => $service->normalizeName('Nanodesu Blue'),
            'source_fingerprint' => hash('sha256', 'nanodesu-blue-2004'),
        ];
        $indexes = $service->buildIndexes([$entry]);

        $match = $service->matchCatalogBall([
            'manufacturer' => 'ABS',
            'brand' => 'NANODESU',
            'name' => 'NANODESU 9 BLUE',
            'release_date' => '2022-01-01',
            'source_url' => 'https://example.test/nanodesu-9-blue/',
        ], $indexes);

        $this->assertSame('not_listed', $match['status']);
        $this->assertNull($match['matched']);
    }

    public function test_it_matches_the_two_user_confirmed_special_cases(): void
    {
        $service = app(UsbcApprovedBallSyncService::class);
        $entries = [
            [
                'brand' => 'Brunswick',
                'name' => 'N-Valkyrie',
                'approved_date_text' => 'May 3, 2022',
                'approved_on' => '2022-05-03',
                'image_url' => null,
                'normalized_brand' => $service->normalizeBrand('Brunswick'),
                'normalized_name' => $service->normalizeName('N-Valkyrie'),
                'source_fingerprint' => hash('sha256', 'n-valkyrie'),
            ],
            [
                'brand' => 'Hammer',
                'name' => 'Black Widow 3.0 Black-Sapphire',
                'approved_date_text' => 'February 27, 2024',
                'approved_on' => '2024-02-27',
                'image_url' => null,
                'normalized_brand' => $service->normalizeBrand('Hammer'),
                'normalized_name' => $service->normalizeName(
                    'Black Widow 3.0 Black-Sapphire'
                ),
                'source_fingerprint' => hash(
                    'sha256',
                    'black-widow-3-black-sapphire'
                ),
            ],
        ];
        $indexes = $service->buildIndexes($entries);

        $cases = [
            [
                'catalog' => [
                    'manufacturer' => 'サンブリッジ',
                    'brand' => 'Brunswick',
                    'name' => 'v VALKYRIE™',
                    'release_date' => '2022-06-01',
                    'source_url' => 'https://example.test/v-valkyrie/',
                ],
                'official_name' => 'N-Valkyrie',
            ],
            [
                'catalog' => [
                    'manufacturer' => 'サンブリッジ',
                    'brand' => 'HAMMER',
                    'name' => 'BLACK WIDOW HYBRID BLACK SAPPHIRE™',
                    'release_date' => '2026-06-01',
                    'source_url' => 'https://example.test/black-widow/',
                ],
                'official_name' => 'Black Widow 3.0 Black-Sapphire',
            ],
        ];

        foreach ($cases as $case) {
            $match = $service->matchCatalogBall(
                $case['catalog'],
                $indexes
            );

            $this->assertSame('matched', $match['status']);
            $this->assertSame('verified_name_alias', $match['method']);
            $this->assertSame(
                $case['official_name'],
                $match['matched']['name']
            );
        }
    }
}
