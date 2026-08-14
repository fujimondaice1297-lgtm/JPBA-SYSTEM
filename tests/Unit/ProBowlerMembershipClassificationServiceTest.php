<?php

namespace Tests\Unit;

use App\Models\ProBowler;
use App\Services\ProBowlerMembershipClassificationService;
use Tests\TestCase;

final class ProBowlerMembershipClassificationServiceTest extends TestCase
{
    private ProBowlerMembershipClassificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProBowlerMembershipClassificationService::class);
    }

    public function test_male_and_female_seed_names_follow_the_annual_seed_rank_rules(): void
    {
        $male = $this->bowler(1, 'M00001219', '川添奨太', 1);
        $femaleFirst = $this->bowler(2, 'F00000582', '女子第一', 2);
        $femaleSecond = $this->bowler(3, 'F00000583', '女子第二', 2);
        $context = $this->context([
            1 => ['gender' => 'M', 'rank' => 24],
            2 => ['gender' => 'F', 'rank' => 18],
            3 => ['gender' => 'F', 'rank' => 19],
        ]);

        $this->assertSame('第１シード', $this->service->decide($male, 2026, $context, ['training_allowed' => false])['membership_type']);
        $this->assertSame('第１シード', $this->service->decide($femaleFirst, 2026, $context, ['training_allowed' => false])['membership_type']);
        $this->assertSame('第２シード', $this->service->decide($femaleSecond, 2026, $context, ['training_allowed' => false])['membership_type']);
    }

    public function test_training_attendee_is_split_by_current_year_official_result(): void
    {
        $participant = $this->bowler(10, 'M00001000', '大会出場者', 1);
        $attendee = $this->bowler(11, 'F00001001', '講習出席者', 2);
        $context = $this->context([], [10 => true]);

        $this->assertSame('トーナメントプロ', $this->service->decide($participant, 2026, $context, ['training_allowed' => true])['membership_type']);
        $this->assertSame('講習会出席者', $this->service->decide($attendee, 2026, $context, ['training_allowed' => true])['membership_type']);
    }

    public function test_foreign_scripts_and_kp_licenses_are_overseas_and_japanese_fallback_is_other(): void
    {
        $english = $this->bowler(20, 'M00000932', 'ROBERT LEE', 1);
        $katakana = $this->bowler(21, 'M00009999', 'ヤンヒョンギュ', 1);
        $kLicense = $this->bowler(22, 'M0000K381', '朴庚信', 1);
        $honorary = $this->bowler(23, 'M00010001', '村田雄浩', 1);
        $context = $this->context();

        foreach ([$english, $katakana, $kLicense] as $bowler) {
            $this->assertSame('海外プロ', $this->service->decide($bowler, 2026, $context, ['training_allowed' => false])['membership_type']);
        }
        $otherDecision = $this->service->decide($honorary, 2026, $context, ['training_allowed' => false]);
        $this->assertSame('その他', $otherDecision['membership_type']);
        $this->assertSame('other', $otherDecision['member_class']);
    }

    public function test_instructor_and_inactive_types_take_precedence(): void
    {
        $instructor = $this->bowler(30, 'M0000T015', '指導太郎', 1);
        $inactive = $this->bowler(31, 'M00000031', '退会太郎', 1);
        $inactive->is_active = false;
        $inactive->membership_type = '退会員';

        $this->assertSame('プロインストラクター', $this->service->decide($instructor, 2026, $this->context(), ['training_allowed' => true])['membership_type']);
        $this->assertSame('退会届', $this->service->decide($inactive, 2026, $this->context(), ['training_allowed' => true])['membership_type']);
    }

    private function bowler(int $id, string $licenseNo, string $name, int $sex): ProBowler
    {
        $bowler = new ProBowler([
            'license_no' => $licenseNo,
            'name_kanji' => $name,
            'sex' => $sex,
            'membership_type' => 'その他',
            'member_class' => 'player',
            'can_enter_official_tournament' => true,
            'is_active' => true,
        ]);
        $bowler->id = $id;

        return $bowler;
    }

    /** @param array<int,array<string,mixed>> $seeds @param array<int,true> $participants */
    private function context(array $seeds = [], array $participants = []): array
    {
        return [
            'seed_by_id' => $seeds,
            'seed_by_license' => [],
            'participant_ids' => $participants,
            'participant_licenses' => [],
        ];
    }
}
