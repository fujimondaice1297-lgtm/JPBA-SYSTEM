<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_tournament_history_syncs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('row_count')->default(0);
            $table->text('source_url')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(
                ['pro_bowler_id', 'season_year'],
                'bowler_tournament_history_syncs_bowler_year_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_tournament_history_syncs');
    }
};
