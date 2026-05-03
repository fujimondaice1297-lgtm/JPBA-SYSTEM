<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_match_score_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('sheet_type', 50)->default('shootout');
            $table->string('stage_code', 80)->nullable();
            $table->string('match_code', 80)->nullable();
            $table->string('match_label')->nullable();
            $table->unsignedInteger('match_order')->default(0);
            $table->unsignedInteger('game_number')->default(1);
            $table->string('lane_label', 50)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'sheet_type', 'stage_code'], 'tmss_tournament_sheet_stage_idx');
            $table->index(['tournament_id', 'match_code'], 'tmss_tournament_match_code_idx');
        });

        Schema::create('tournament_match_score_sheet_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_id')->constrained('tournament_match_score_sheets')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('player_slot', 20)->nullable();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('pro_bowler_license_no')->nullable();
            $table->string('display_name');
            $table->string('name_kana')->nullable();
            $table->string('dominant_arm', 20)->nullable();
            $table->string('lane_label', 50)->nullable();
            $table->unsignedInteger('final_score')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->json('score_summary')->nullable();
            $table->timestamps();

            $table->index(['score_sheet_id', 'sort_order'], 'tmssp_sheet_sort_idx');
            $table->index(['pro_bowler_id'], 'tmssp_pro_bowler_idx');
            $table->index(['pro_bowler_license_no'], 'tmssp_license_idx');
        });

        Schema::create('tournament_match_score_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_player_id')->constrained('tournament_match_score_sheet_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('frame_no');
            $table->string('throw1', 10)->nullable();
            $table->string('throw2', 10)->nullable();
            $table->string('throw3', 10)->nullable();
            $table->unsignedInteger('frame_score')->nullable();
            $table->unsignedInteger('cumulative_score')->nullable();
            $table->json('display_marks')->nullable();
            $table->timestamps();

            $table->unique(['score_sheet_player_id', 'frame_no'], 'tmsf_player_frame_unique');
            $table->index(['score_sheet_player_id', 'frame_no'], 'tmsf_player_frame_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_match_score_frames');
        Schema::dropIfExists('tournament_match_score_sheet_players');
        Schema::dropIfExists('tournament_match_score_sheets');
    }
};
