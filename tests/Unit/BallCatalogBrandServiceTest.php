<?php

namespace Tests\Unit;

use App\Models\ApprovedBall;
use App\Services\BallCatalogBrandService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BallCatalogBrandServiceTest extends TestCase
{
    public function test_registration_brands_are_grouped_by_distributor_then_usbc_only(): void
    {
        $balls = collect([
            new ApprovedBall([
                'name' => 'Domestic Motiv',
                'manufacturer' => 'ABS',
                'brand' => 'MOTIV',
            ]),
            new ApprovedBall([
                'name' => 'Domestic Global',
                'manufacturer' => 'ABS',
                'brand' => '900GLOBAL',
            ]),
            new ApprovedBall([
                'name' => 'Official Global Duplicate',
                'manufacturer' => 'USBC',
                'brand' => '900GLOBAL',
                'source_payload' => ['source_type' => 'usbc_approved_list'],
            ]),
            new ApprovedBall([
                'name' => 'Official Track',
                'manufacturer' => 'USBC',
                'brand' => 'TRACK BOWLING',
                'source_payload' => ['source_type' => 'usbc_approved_list'],
            ]),
            new ApprovedBall([
                'name' => 'Official Brunswick',
                'manufacturer' => 'USBC',
                'brand' => 'Brunswick',
                'source_payload' => ['source_type' => 'usbc_approved_list'],
            ]),
        ]);

        [$distributorBrands, $usbcOnlyBrands] = app(BallCatalogBrandService::class)
            ->registrationBrandGroups($balls);

        $this->assertSame(['900GLOBAL', 'MOTIV'], $distributorBrands->all());
        $this->assertSame(['Brunswick', 'TRACK BOWLING'], $usbcOnlyBrands->all());
    }

    #[DataProvider('officialBrandCases')]
    public function test_official_brands_are_normalized_to_distributor_catalog_labels(
        string $officialBrand,
        string $ballName,
        string $expected
    ): void {
        $actual = app(BallCatalogBrandService::class)
            ->officialCatalogBrand($officialBrand, $ballName);

        $this->assertSame($expected, $actual);
    }

    public static function officialBrandCases(): array
    {
        return [
            'ABSのPRO-am製品' => ['ABS', 'PRO-am Speed Striker Hybrid', 'PRO-am'],
            'ABSのNANODESU製品' => ['ABS', 'Nanodesu Accu-Line Premium', 'NANODESU'],
            'ABSの自社ブランド' => ['ABS', 'Accu Line TOUR PREMIUM IX', 'ABS'],
            '900GLOBAL' => ['900 Global', 'Reality', '900GLOBAL'],
            'HI-SP' => ['High Sports', 'Up Beat', 'HI-SP'],
            'ROTOGRIP' => ['Roto Grip', 'Attention Star', 'ROTOGRIP'],
            'STORM' => ['Storm', 'Phaze II', 'STORM'],
            'サンブリッジTRACK' => ['Track Inc.', 'Theorem', 'TRACK BOWLING'],
        ];
    }
}
