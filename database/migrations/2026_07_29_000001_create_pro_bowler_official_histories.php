<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_annual_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->string('season_key', 20);
            $table->unsignedSmallInteger('season_start_year');
            $table->unsignedSmallInteger('season_end_year');
            $table->unsignedInteger('ranking_rank')->nullable();
            $table->unsignedInteger('games')->nullable();
            $table->unsignedBigInteger('total_pin')->nullable();
            $table->decimal('points', 12, 2)->nullable();
            $table->decimal('average', 7, 2)->nullable();
            $table->unsignedBigInteger('prize_money')->nullable();
            $table->string('source_type', 50)->default('official_profile');
            $table->text('source_url')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['pro_bowler_id', 'season_key'],
                'bowler_annual_records_bowler_season_unique'
            );
            $table->index(
                ['pro_bowler_id', 'season_end_year'],
                'bowler_annual_records_bowler_year_idx'
            );
        });

        Schema::create('pro_bowler_tournament_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->date('held_on');
            $table->text('tournament_name');
            $table->unsignedInteger('ranking_rank')->nullable();
            $table->decimal('average', 7, 2)->nullable();
            $table->unsignedBigInteger('prize_money')->nullable();
            $table->string('source_type', 50)->default('official_profile');
            $table->text('source_url')->nullable();
            $table->char('source_fingerprint', 64);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(
                'source_fingerprint',
                'bowler_tournament_histories_fingerprint_unique'
            );
            $table->index(
                ['pro_bowler_id', 'season_year', 'held_on'],
                'bowler_tournament_histories_bowler_year_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_tournament_histories');
        Schema::dropIfExists('pro_bowler_annual_records');
    }
};
