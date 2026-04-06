<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_registry', function (Blueprint $table) {
            $table->timestamp('source_registered_at')
                ->nullable()
                ->after('coach_qualification')
                ->comment('元データ上の登録日・交付日・開始日');

            $table->boolean('is_current')
                ->default(true)
                ->after('source_registered_at')
                ->comment('現在有効な所属状態か');

            $table->timestamp('superseded_at')
                ->nullable()
                ->after('is_current')
                ->comment('後続状態に置き換わった日時');

            $table->string('supersede_reason', 64)
                ->nullable()
                ->after('superseded_at')
                ->comment('promoted_to_pro_bowler / promoted_to_pro_instructor / downgraded_to_certified など');
        });

        DB::statement("UPDATE instructor_registry SET is_current = true WHERE is_current IS NULL");

        Schema::table('instructor_registry', function (Blueprint $table) {
            $table->index(['is_current', 'instructor_category'], 'instructor_registry_current_category_idx');
            $table->index('source_registered_at', 'instructor_registry_source_registered_at_idx');
        });

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX instructor_registry_current_pro_bowler_category_unique
ON instructor_registry (pro_bowler_id, instructor_category)
WHERE is_current = true AND pro_bowler_id IS NOT NULL
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX instructor_registry_current_license_category_unique
ON instructor_registry (license_no, instructor_category)
WHERE is_current = true AND license_no IS NOT NULL
SQL);

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX instructor_registry_current_cert_category_unique
ON instructor_registry (cert_no, instructor_category)
WHERE is_current = true AND cert_no IS NOT NULL
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS instructor_registry_current_pro_bowler_category_unique');
        DB::statement('DROP INDEX IF EXISTS instructor_registry_current_license_category_unique');
        DB::statement('DROP INDEX IF EXISTS instructor_registry_current_cert_category_unique');

        Schema::table('instructor_registry', function (Blueprint $table) {
            $table->dropIndex('instructor_registry_current_category_idx');
            $table->dropIndex('instructor_registry_source_registered_at_idx');
            $table->dropColumn([
                'source_registered_at',
                'is_current',
                'superseded_at',
                'supersede_reason',
            ]);
        });
    }
};
