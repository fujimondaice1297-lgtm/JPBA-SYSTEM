<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (! Schema::hasColumn('pro_bowlers', 'official_win_count')) {
                $table->unsignedInteger('official_win_count')->nullable()->comment('Official JPBA profile win count');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_total_games')) {
                $table->unsignedInteger('official_total_games')->nullable()->comment('Official JPBA profile total games');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_total_pins')) {
                $table->unsignedBigInteger('official_total_pins')->nullable()->comment('Official JPBA profile total pins');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_total_prize_money')) {
                $table->unsignedBigInteger('official_total_prize_money')->nullable()->comment('Official JPBA profile total prize money');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_career_average')) {
                $table->decimal('official_career_average', 6, 2)->nullable()->comment('Official JPBA profile career average');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_profile_url')) {
                $table->string('official_profile_url')->nullable()->comment('Official JPBA profile URL');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_profile_imported_at')) {
                $table->timestamp('official_profile_imported_at')->nullable()->comment('Official JPBA profile import timestamp');
            }
            if (! Schema::hasColumn('pro_bowlers', 'official_profile_import_error')) {
                $table->text('official_profile_import_error')->nullable()->comment('Official JPBA profile import error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            foreach ([
                'official_profile_import_error',
                'official_profile_imported_at',
                'official_profile_sync_error',
                'official_profile_synced_at',
                'official_profile_url',
                'official_career_average',
                'official_total_prize_money',
                'official_total_pins',
                'official_total_games',
                'official_win_count',
            ] as $column) {
                if (Schema::hasColumn('pro_bowlers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
