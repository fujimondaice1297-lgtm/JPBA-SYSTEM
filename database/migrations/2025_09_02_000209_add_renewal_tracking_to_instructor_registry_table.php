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
            $table->unsignedSmallInteger('renewal_year')
                ->nullable()
                ->after('is_visible')
                ->comment('更新対象年度');

            $table->date('renewal_due_on')
                ->nullable()
                ->after('renewal_year')
                ->comment('更新期限（原則 12/31）');

            $table->string('renewal_status', 16)
                ->nullable()
                ->after('renewal_due_on')
                ->comment('pending / renewed / expired');

            $table->date('renewed_at')
                ->nullable()
                ->after('renewal_status')
                ->comment('更新完了日');

            $table->text('renewal_note')
                ->nullable()
                ->after('renewed_at')
                ->comment('更新備考');
        });

        DB::statement(<<<'SQL'
ALTER TABLE instructor_registry
ADD CONSTRAINT instructor_registry_renewal_status_check
CHECK (
    renewal_status IS NULL
    OR renewal_status IN ('pending', 'renewed', 'expired')
)
SQL);

        Schema::table('instructor_registry', function (Blueprint $table) {
            $table->index(['renewal_year', 'renewal_status'], 'instructor_registry_renewal_year_status_idx');
            $table->index('renewal_due_on', 'instructor_registry_renewal_due_on_idx');
        });

        $currentYear = (int) now()->format('Y');
        $currentDueOn = sprintf('%04d-12-31', $currentYear);

        DB::table('instructor_registry')
            ->where('is_current', true)
            ->where('is_active', true)
            ->update([
                'renewal_year' => $currentYear,
                'renewal_due_on' => $currentDueOn,
                'renewal_status' => 'pending',
                'renewed_at' => null,
            ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE instructor_registry DROP CONSTRAINT IF EXISTS instructor_registry_renewal_status_check');

        Schema::table('instructor_registry', function (Blueprint $table) {
            $table->dropIndex('instructor_registry_renewal_year_status_idx');
            $table->dropIndex('instructor_registry_renewal_due_on_idx');
            $table->dropColumn([
                'renewal_year',
                'renewal_due_on',
                'renewal_status',
                'renewed_at',
                'renewal_note',
            ]);
        });
    }
};
