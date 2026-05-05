<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_results', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_results', 'affiliation_display')) {
                $table->text('affiliation_display')->nullable()->comment('PDF表示用の所属 / 用品契約スナップショット');
            }

            if (!Schema::hasColumn('tournament_results', 'award_points')) {
                $table->integer('award_points')->nullable()->comment('入賞ポイント');
            }

            if (!Schema::hasColumn('tournament_results', 'step_points')) {
                $table->integer('step_points')->nullable()->comment('ステップポイント');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_results', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_results', 'step_points')) {
                $table->dropColumn('step_points');
            }

            if (Schema::hasColumn('tournament_results', 'award_points')) {
                $table->dropColumn('award_points');
            }

            if (Schema::hasColumn('tournament_results', 'affiliation_display')) {
                $table->dropColumn('affiliation_display');
            }
        });
    }
};
