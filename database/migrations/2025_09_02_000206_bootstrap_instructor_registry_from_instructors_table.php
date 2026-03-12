<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instructor_registry') || !Schema::hasTable('instructors')) {
            return;
        }

        $now = now();

        DB::table('instructors')
            ->orderBy('license_no')
            ->chunk(200, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $category = $this->resolveCategory($row);

                    DB::table('instructor_registry')->updateOrInsert(
                        [
                            'source_type' => 'legacy_instructors',
                            'source_key'  => (string) $row->license_no,
                        ],
                        [
                            'legacy_instructor_license_no' => $row->license_no,
                            'pro_bowler_id'                => $row->pro_bowler_id,
                            'license_no'                   => $row->license_no,
                            'cert_no'                      => null,
                            'name'                         => $row->name,
                            'name_kana'                    => $row->name_kana,
                            'sex'                          => $row->sex,
                            'district_id'                  => $row->district_id,
                            'instructor_category'          => $category,
                            'grade'                        => $row->grade,
                            'coach_qualification'          => (bool) $row->coach_qualification,
                            'is_active'                    => (bool) $row->is_active,
                            'is_visible'                   => (bool) $row->is_visible,
                            'last_synced_at'               => $now,
                            'notes'                        => 'bootstrapped from legacy instructors table',
                            'created_at'                   => $row->created_at ?? $now,
                            'updated_at'                   => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('instructor_registry')) {
            return;
        }

        DB::table('instructor_registry')
            ->where('source_type', 'legacy_instructors')
            ->delete();
    }

    private function resolveCategory(object $row): string
    {
        if (($row->instructor_type ?? null) === 'certified') {
            return 'certified';
        }

        if (!empty($row->pro_bowler_id)) {
            return 'pro_bowler';
        }

        return 'pro_instructor';
    }
};