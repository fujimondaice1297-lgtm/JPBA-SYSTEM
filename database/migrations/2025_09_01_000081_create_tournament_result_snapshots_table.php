<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_result_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('result_code', 64);
            $table->string('result_name');
            $table->string('result_type', 32)->default('total_pin');
            $table->string('stage_name')->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('shift', 16)->nullable();
            $table->unsignedInteger('games_count')->default(0);
            $table->unsignedInteger('carry_game_count')->default(0);
            $table->json('carry_stage_names')->nullable();
            $table->json('calculation_definition');
            $table->timestamp('reflected_at')->nullable();
            $table->foreignId('reflected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'result_code'], 'trs_tournament_result_code_idx');
            $table->index(['tournament_id', 'is_current'], 'trs_tournament_current_idx');
            $table->index(['tournament_id', 'is_final'], 'trs_tournament_final_idx');
            $table->index(['tournament_id', 'result_type'], 'trs_tournament_result_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_result_snapshots');
    }
};
