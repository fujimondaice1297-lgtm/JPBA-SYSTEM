<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instructors') || !Schema::hasColumn('instructors', 'grade')) {
            return;
        }

        DB::statement("ALTER TABLE instructors DROP CONSTRAINT IF EXISTS instructors_grade_check");

        DB::statement(<<<'SQL'
ALTER TABLE instructors
ADD CONSTRAINT instructors_grade_check
CHECK (
    grade IS NULL OR grade IN (
        'C級',
        '準B級',
        'B級',
        '準A級',
        'A級',
        '2級',
        '1級'
    )
)
SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('instructors')) {
            return;
        }

        DB::statement("ALTER TABLE instructors DROP CONSTRAINT IF EXISTS instructors_grade_check");
    }
};