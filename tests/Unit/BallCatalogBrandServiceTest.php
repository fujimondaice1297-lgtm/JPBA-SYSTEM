<?php

namespace Tests\Unit;

use App\Services\BallCatalogBrandService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BallCatalogBrandServiceTest extends TestCase
{
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
