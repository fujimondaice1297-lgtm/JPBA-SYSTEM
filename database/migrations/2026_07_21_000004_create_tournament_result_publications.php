<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_result_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')
                ->nullable()
                ->constrained('tournament_result_snapshots')
                ->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status', 20)->default('current');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('pro_count')->default(0);
            $table->unsignedInteger('amateur_count')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->bigInteger('total_prize_money')->default(0);
            $table->char('result_checksum', 64);
            $table->char('distribution_checksum', 64);
            $table->json('source_snapshot_ids');
            $table->json('validation_summary')->nullable();
            $table->json('title_sync_summary')->nullable();
            $table->timestamp('published_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'revision'], 'trp_tournament_revision_unique');
            $table->index(['tournament_id', 'status'], 'trp_tournament_status_idx');
            $table->index(['snapshot_id', 'published_at'], 'trp_snapshot_published_idx');
        });

        Schema::create('tournament_result_publication_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')
                ->constrained('tournament_result_publications')
                ->cascadeOnDelete();
            $table->foreignId('source_snapshot_id')
                ->nullable()
                ->constrained('tournament_result_snapshots')
                ->nullOnDelete();
            $table->foreignId('source_snapshot_row_id')
                ->nullable()
                ->constrained('tournament_result_snapshot_rows')
                ->nullOnDelete();
            $table->string('source_result_code', 64)->nullable();
            $table->unsignedInteger('ranking');
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->foreignId('amateur_bowler_id')->nullable()->constrained('amateur_bowlers')->nullOnDelete();
            $table->string('pro_bowler_license_no')->nullable();
            $table->string('amateur_name')->nullable();
            $table->string('display_name');
            $table->string('identity_key');
            $table->string('gender', 1)->nullable();
            $table->string('entry_number', 32)->nullable();
            $table->unsignedInteger('total_pin')->default(0);
            $table->unsignedInteger('games')->default(0);
            $table->decimal('average', 7, 3)->nullable();
            $table->integer('points')->default(0);
            $table->integer('award_points')->default(0);
            $table->integer('step_points')->default(0);
            $table->bigInteger('prize_money')->default(0);
            $table->text('affiliation_display')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->index(['publication_id', 'ranking'], 'trpr_publication_ranking_idx');
            $table->index(['publication_id', 'pro_bowler_id'], 'trpr_publication_bowler_idx');
            $table->index(['publication_id', 'identity_key'], 'trpr_publication_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_result_publication_rows');
        Schema::dropIfExists('tournament_result_publications');
    }
};
