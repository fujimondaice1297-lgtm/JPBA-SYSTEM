<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_match_score_frames')) {
            return;
        }

        if (Schema::hasColumn('tournament_match_score_frames', 'remaining_pins')) {
            return;
        }

        Schema::table('tournament_match_score_frames', function (Blueprint $table): void {
            $table
                ->jsonb('remaining_pins')
                ->nullable()
                ->comment('残りピン番号の配列。例: [3,5,6]');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tournament_match_score_frames')) {
            return;
        }

        if (! Schema::hasColumn('tournament_match_score_frames', 'remaining_pins')) {
            return;
        }

        Schema::table('tournament_match_score_frames', function (Blueprint $table): void {
            $table->dropColumn('remaining_pins');
        });
    }
};