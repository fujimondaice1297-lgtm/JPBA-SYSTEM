<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (! Schema::hasColumn('pro_bowlers', 'season_trial_win_count')) {
                $table->unsignedInteger('season_trial_win_count')
                    ->nullable()
                    ->after('official_win_count')
                    ->comment('Official JPBA profile season trial win count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (Schema::hasColumn('pro_bowlers', 'season_trial_win_count')) {
                $table->dropColumn('season_trial_win_count');
            }
        });
    }
};
