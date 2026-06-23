<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('import_type', 50)->default('csv');
            $table->string('source_filename')->nullable();
            $table->text('stored_path')->nullable();
            $table->string('status', 50)->default('draft');
            $table->string('parser_version', 50)->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('accepted_row_count')->default(0);
            $table->unsignedInteger('rejected_row_count')->default(0);
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status'], 'sib_tournament_status_idx');
            $table->index('imported_by', 'sib_imported_by_idx');
            $table->index('confirmed_by', 'sib_confirmed_by_idx');
            $table->index('parsed_at', 'sib_parsed_at_idx');
            $table->index('confirmed_at', 'sib_confirmed_at_idx');
        });

        Schema::create('score_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_payload')->nullable();
            $table->string('parse_status', 50)->default('pending');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->foreignId('tournament_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('license_number', 50)->nullable();
            $table->string('name')->nullable();
            $table->string('entry_number', 50)->nullable();
            $table->string('stage', 50)->nullable();
            $table->string('shift', 20)->nullable();
            $table->string('gender', 10)->nullable();
            $table->unsignedInteger('game_number')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('confirmed_game_score_id')->nullable()->constrained('game_scores')->nullOnDelete();
            $table->timestamps();

            $table->unique(['score_import_batch_id', 'row_number'], 'sir_batch_row_unique');
            $table->index(['score_import_batch_id', 'parse_status'], 'sir_batch_status_idx');
            $table->index('tournament_participant_id', 'sir_participant_idx');
            $table->index('pro_bowler_id', 'sir_bowler_idx');
            $table->index('confirmed_game_score_id', 'sir_confirmed_score_idx');
            $table->index(['stage', 'game_number'], 'sir_stage_game_idx');
            $table->index('reviewed_by', 'sir_reviewed_by_idx');
        });

        Schema::create('score_import_row_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_import_row_id')->constrained()->cascadeOnDelete();
            $table->string('candidate_type', 50);
            $table->string('candidate_value')->nullable();
            $table->foreignId('tournament_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();

            $table->index(['score_import_row_id', 'rank'], 'sirc_row_rank_idx');
            $table->index(['score_import_row_id', 'is_selected'], 'sirc_row_selected_idx');
            $table->index('tournament_participant_id', 'sirc_participant_idx');
            $table->index('pro_bowler_id', 'sirc_bowler_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_import_row_candidates');
        Schema::dropIfExists('score_import_rows');
        Schema::dropIfExists('score_import_batches');
    }
};
