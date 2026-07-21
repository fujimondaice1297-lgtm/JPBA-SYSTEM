<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_aggregate_definitions', function (Blueprint $table) {
            $table->string('tie_break_policy', 30)
                ->default('shared_rank')
                ->after('subject_type');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_aggregate_definitions', function (Blueprint $table) {
            $table->dropColumn('tie_break_policy');
        });
    }
};
