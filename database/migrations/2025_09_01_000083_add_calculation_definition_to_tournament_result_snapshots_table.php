<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_result_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_result_snapshots', 'calculation_definition')) {
                $table->json('calculation_definition')->nullable()->after('carry_stage_names');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_result_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_result_snapshots', 'calculation_definition')) {
                $table->dropColumn('calculation_definition');
            }
        });
    }
};
