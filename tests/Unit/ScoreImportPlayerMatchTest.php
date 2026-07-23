<?php

namespace Tests\Unit;

use App\Services\ScoreImportCsvStageService;
use App\Services\ScoreImportOcrResultStageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ScoreImportPlayerMatchTest extends TestCase
{
    public static function serviceClasses(): array
    {
        return [
            'OCR result staging' => [ScoreImportOcrResultStageService::class],
            'CSV staging' => [ScoreImportCsvStageService::class],
        ];
    }

    #[DataProvider('serviceClasses')]
    public function test_same_participant_is_not_ambiguous_when_two_license_columns_have_the_same_value(
        string $serviceClass
    ): void {
        $participant = (object) [
            'id' => 101,
            'pro_bowler_id' => 201,
            'pro_bowler_license_no' => 'M00001478',
            'display_license_no' => 'M00001478',
            'display_name' => '倉持 悠人',
        ];
        $pro = (object) [
            'id' => 201,
            'license_no' => 'M00001478',
            'name_kanji' => '倉持 悠人',
        ];
        $lookups = [
            'participants_by_license' => ['m00001478' => [$participant, $participant]],
            'participants_by_name' => [],
            'pros_by_license' => ['m00001478' => [$pro]],
            'pros_by_name' => [],
        ];

        $method = new ReflectionMethod($serviceClass, 'matchPlayer');
        $result = $method->invoke(new $serviceClass(), [
            'license_number' => 'M00001478',
            'name' => '倉持 悠人',
            'entry_number' => '',
        ], $lookups);

        self::assertFalse($result['ambiguous']);
        self::assertSame(101, $result['tournament_participant_id']);
        self::assertSame(201, $result['pro_bowler_id']);
        self::assertCount(2, $result['candidates']);
    }
}
