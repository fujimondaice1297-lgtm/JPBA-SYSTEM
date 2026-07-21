<?php

namespace Tests\Feature;

use App\Models\InstructorRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PublicInstructorPrivacyTest extends TestCase
{
    public function test_public_list_hides_certified_source_key_but_keeps_pro_license_number(): void
    {
        $certified = new InstructorRegistry([
            'name' => '認定テスト',
            'cert_no' => 'X00009999',
            'source_key' => 'X00009999',
            'instructor_category' => 'certified',
            'grade' => '1級',
            'renewal_status' => 'renewed',
        ]);
        $certified->setRelation('district', null);

        $pro = new InstructorRegistry([
            'name' => 'プロテスト',
            'license_no' => 'M00009999',
            'instructor_category' => 'pro_bowler',
            'grade' => 'A級',
            'renewal_status' => 'renewed',
        ]);
        $pro->setRelation('district', null);

        $html = view('public.instructors.index', [
            'publicConfig' => [],
            'instructorConfig' => [
                'summary' => [],
                'feature_links' => [],
                'license_links' => [],
            ],
            'filters' => [],
            'instructors' => new LengthAwarePaginator([$certified, $pro], 2, 30),
            'districts' => collect(),
            'categoryOptions' => [
                'pro_bowler' => 'プロボウラー',
                'pro_instructor' => 'プロ・インストラクター',
                'certified' => '認定インストラクター',
            ],
            'gradeOptions' => ['A級', '1級'],
            'categoryCounts' => collect([
                'pro_bowler' => 1,
                'pro_instructor' => 0,
                'certified' => 1,
            ]),
            'instructorInformations' => collect(),
        ])->render();

        $this->assertStringNotContainsString('X00009999', $html);
        $this->assertStringContainsString('M00009999', $html);
        $this->assertStringContainsString('認定テスト', $html);
    }
}
