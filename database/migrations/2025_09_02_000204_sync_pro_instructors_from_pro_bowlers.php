<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pro_bowlers') || !Schema::hasTable('instructors')) {
            return;
        }

        $now = now();

        DB::table('pro_bowlers')
            ->select([
                'id',
                'license_no',
                'name_kanji',
                'name_kana',
                'sex',
                'district_id',
                'is_active',
                'a_class_status',
                'b_class_status',
                'c_class_status',
                'master_status',
                'school_license_status',
                'coach_4_status',
                'coach_3_status',
                'coach_1_status',
                'kenkou_status',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $bowler) {
                    if (empty($bowler->license_no)) {
                        continue;
                    }

                    $grade = $this->resolveGrade($bowler);
                    $hasProfile = $this->hasInstructorProfile($bowler, $grade);

                    if (!$hasProfile) {
                        continue;
                    }

                    $existing = DB::table('instructors')
                        ->where('license_no', $bowler->license_no)
                        ->first(['license_no', 'is_visible']);

                    $payload = [
                        'pro_bowler_id'       => $bowler->id,
                        'name'                => $bowler->name_kanji,
                        'name_kana'           => $bowler->name_kana,
                        'sex'                 => ((int) ($bowler->sex ?? 0)) === 1,
                        'district_id'         => $bowler->district_id,
                        'instructor_type'     => 'pro',
                        'grade'               => $grade,
                        'is_active'           => (bool) $bowler->is_active,
                        'is_visible'          => $existing?->is_visible ?? true,
                        'coach_qualification' => ($bowler->school_license_status ?? null) === '有',
                        'updated_at'          => $now,
                    ];

                    if ($existing) {
                        DB::table('instructors')
                            ->where('license_no', $bowler->license_no)
                            ->update($payload);
                    } else {
                        DB::table('instructors')->insert(array_merge([
                            'license_no' => $bowler->license_no,
                            'created_at' => $now,
                        ], $payload));
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        // 既存データの同期結果は安全に一意復元できないため no-op
    }

    private function resolveGrade(object $bowler): ?string
    {
        return match (true) {
            ($bowler->a_class_status ?? null) === '有' => 'A級',
            ($bowler->b_class_status ?? null) === '有' => 'B級',
            ($bowler->c_class_status ?? null) === '有' => 'C級',
            default => null,
        };
    }

    private function hasInstructorProfile(object $bowler, ?string $grade): bool
    {
        return $grade !== null
            || ($bowler->master_status ?? null) === '有'
            || ($bowler->school_license_status ?? null) === '有'
            || ($bowler->coach_4_status ?? null) === '有'
            || ($bowler->coach_3_status ?? null) === '有'
            || ($bowler->coach_1_status ?? null) === '有'
            || ($bowler->kenkou_status ?? null) === '有';
    }
};
