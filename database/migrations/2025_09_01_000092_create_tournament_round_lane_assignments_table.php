<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_round_lane_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')
                ->constrained('tournaments')
                ->cascadeOnDelete();

            $table->foreignId('source_result_snapshot_id')
                ->nullable()
                ->constrained('tournament_result_snapshots')
                ->nullOnDelete();

            $table->foreignId('tournament_participant_id')
                ->nullable()
                ->constrained('tournament_participants')
                ->nullOnDelete();

            $table->foreignId('pro_bowler_id')
                ->nullable()
                ->constrained('pro_bowlers')
                ->nullOnDelete();

            $table->string('stage', 64)->default('準決勝');
            $table->string('round_label', 128)->nullable();

            $table->unsignedSmallInteger('game_from')->nullable();
            $table->unsignedSmallInteger('game_to')->nullable();

            $table->unsignedInteger('seed_rank')->nullable();
            $table->string('pro_bowler_license_no', 32)->nullable();
            $table->string('display_license_no', 32)->nullable();
            $table->string('display_name', 255);
            $table->string('period_label', 32)->nullable();
            $table->string('dominant_arm', 32)->nullable();
            $table->text('affiliation_display')->nullable();

            $table->integer('source_total_pin')->nullable();
            $table->unsignedSmallInteger('source_games')->nullable();
            $table->decimal('source_average', 8, 3)->nullable();

            $table->unsignedSmallInteger('start_lane')->nullable();
            $table->unsignedSmallInteger('lane_slot')->nullable();
            $table->string('start_lane_label', 32)->nullable();
            $table->unsignedSmallInteger('box_no')->nullable();

            $table->string('movement_direction', 16)->default('left');
            $table->unsignedSmallInteger('movement_box_step')->default(1);
            $table->json('movement_boxes')->nullable();

            $table->time('game_start_time')->nullable();
            $table->unsignedSmallInteger('game_interval_minutes')->nullable();
            $table->unsignedSmallInteger('tv_lane_from')->nullable();
            $table->unsignedSmallInteger('tv_lane_to')->nullable();

            $table->integer('sort_order')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(
                ['tournament_id', 'stage', 'round_label', 'tournament_participant_id'],
                'trla_unique_participant_round'
            );
            $table->index(['tournament_id', 'stage', 'round_label'], 'trla_tournament_stage_round_idx');
            $table->index(['source_result_snapshot_id', 'seed_rank'], 'trla_snapshot_seed_idx');
            $table->index(['tournament_participant_id'], 'trla_participant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_round_lane_assignments');
    }
};
