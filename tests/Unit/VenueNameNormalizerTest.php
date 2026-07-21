<?php

namespace Tests\Unit;

use App\Services\VenueNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VenueNameNormalizerTest extends TestCase
{
    #[DataProvider('venueNames')]
    public function test_normalizes_known_venue_name_variants(string $left, string $right): void
    {
        $normalizer = app(VenueNameNormalizer::class);

        $this->assertSame($normalizer->normalize($left), $normalizer->normalize($right));
    }

    public static function venueNames(): array
    {
        return [
            ['ラウンドワン博多･半道橋店', 'ラウンドワン博多・半道橋店'],
            ['ＭＫボウル 上賀茂', 'MKボウル上賀茂'],
            ['伊勢原ボウリングセンター', '伊勢原ボウリングセンター'],
        ];
    }
}
