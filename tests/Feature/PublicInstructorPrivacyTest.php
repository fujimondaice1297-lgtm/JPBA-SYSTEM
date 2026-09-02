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

        $proInstructor = new InstructorRegistry([
            'name' => 'プロインストラクターテスト',
            'license_no' => 'M000T012',
            'instructor_category' => 'pro_instructor',
            'grade' => 'C級',
            'renewal_status' => 'renewed',
        ]);
        $proInstructor->setRelation('district', null);

        $html = view('public.instructors.index', [
            'publicConfig' => [],
            'instructorConfig' => [
                'summary' => [],
                'feature_links' => [],
                'license_links' => [],
            ],
            'filters' => ['gender' => '男性'],
            'hasRequiredFilters' => true,
            'instructors' => new LengthAwarePaginator([$certified, $pro, $proInstructor], 3, 30),
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
        $this->assertStringNotContainsString('M00009999', $html);
        $this->assertStringNotContainsString('M000T012', $html);
        $this->assertStringContainsString('9999', $html);
        $this->assertStringContainsString('T012', $html);
        $this->assertStringContainsString('認定テスト', $html);
    }

    public function test_public_search_stays_empty_until_gender_is_selected_and_separates_gender(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $maleName = '公開講師 男子テスト'.$suffix;
        $femaleName = '公開講師 女子テスト'.$suffix;

        InstructorRegistry::query()->create([
            'source_type' => 'manual',
            'source_key' => 'public-instructor-male-search-test-'.$suffix,
            'license_no' => 'M00009701',
            'name' => $maleName,
            'sex' => true,
            'instructor_category' => 'pro_bowler',
            'grade' => 'A級',
            'is_current' => true,
            'is_active' => true,
            'is_visible' => true,
        ]);
        InstructorRegistry::query()->create([
            'source_type' => 'manual',
            'source_key' => 'public-instructor-female-search-test-'.$suffix,
            'license_no' => 'F00009701',
            'name' => $femaleName,
            'sex' => false,
            'instructor_category' => 'pro_bowler',
            'grade' => 'A級',
            'is_current' => true,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $this->get(route('public.instructors.index'))
            ->assertOk()
            ->assertSee('性別は必須です')
            ->assertDontSee($maleName)
            ->assertDontSee($femaleName);

        $this->get(route('public.instructors.index', ['gender' => '男性']))
            ->assertOk()
            ->assertSee($maleName)
            ->assertSee('9701')
            ->assertDontSee('M00009701')
            ->assertDontSee($femaleName);

        $this->get(route('public.instructors.index', ['gender' => '女性']))
            ->assertOk()
            ->assertSee($femaleName)
            ->assertSee('9701')
            ->assertDontSee('F00009701')
            ->assertDontSee($maleName);
    }
}
