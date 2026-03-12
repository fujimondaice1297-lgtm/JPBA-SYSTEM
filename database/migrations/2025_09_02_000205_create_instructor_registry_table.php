<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_registry', function (Blueprint $table) {
            $table->id();

            $table->string('source_type', 64)->comment('取込元種別（legacy_instructors / pro_bowler / manual など）');
            $table->string('source_key', 255)->comment('source_type 内で一意なキー');

            $table->string('legacy_instructor_license_no')->nullable()->comment('旧 instructors.license_no の退避');
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();

            $table->string('license_no')->nullable()->comment('ライセンス番号');
            $table->string('cert_no')->nullable()->comment('認定番号');
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->boolean('sex')->nullable()->comment('男性=true / 女性=false / 不明=null');
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();

            $table->string('instructor_category', 32)->comment('pro_bowler / pro_instructor / certified');
            $table->string('grade')->nullable()->comment('C級 / 準B級 / B級 / 準A級 / A級 / 2級 / 1級');
            $table->boolean('coach_qualification')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);

            $table->timestamp('last_synced_at')->nullable()->comment('最終同期日時');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();

            $table->unique(['source_type', 'source_key'], 'instructor_registry_source_unique');

            $table->index('legacy_instructor_license_no', 'instructor_registry_legacy_license_idx');
            $table->index('pro_bowler_id', 'instructor_registry_pro_bowler_id_idx');
            $table->index('license_no', 'instructor_registry_license_no_idx');
            $table->index('cert_no', 'instructor_registry_cert_no_idx');
            $table->index('district_id', 'instructor_registry_district_id_idx');
            $table->index('instructor_category', 'instructor_registry_category_idx');
            $table->index('grade', 'instructor_registry_grade_idx');
            $table->index(['is_active', 'is_visible'], 'instructor_registry_active_visible_idx');
        });

        DB::statement(<<<'SQL'
ALTER TABLE instructor_registry
ADD CONSTRAINT instructor_registry_category_check
CHECK (instructor_category IN ('pro_bowler', 'pro_instructor', 'certified'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE instructor_registry
ADD CONSTRAINT instructor_registry_grade_check
CHECK (
    grade IS NULL
    OR grade IN ('C級', '準B級', 'B級', '準A級', 'A級', '2級', '1級')
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_registry');
    }
};