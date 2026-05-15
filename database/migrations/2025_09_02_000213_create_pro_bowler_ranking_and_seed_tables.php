<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_ranking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->integer('ranking_year');
            $table->string('gender', 10);
            $table->string('ranking_type', 50)->default('point');
            $table->string('ranking_scope', 50)->default('annual');
            $table->date('as_of_date')->nullable();
            $table->boolean('is_final')->default(false);
            $table->text('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ranking_year', 'gender', 'ranking_type', 'ranking_scope'], 'pbrs_year_gender_type_scope_idx');
            $table->index(['as_of_date', 'is_final'], 'pbrs_asof_final_idx');
        });

        Schema::create('pro_bowler_ranking_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_snapshot_id')->constrained('pro_bowler_ranking_snapshots')->cascadeOnDelete();
            $table->integer('ranking_rank');
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('license_no')->nullable();
            $table->string('name_kanji')->nullable();
            $table->string('name_kana')->nullable();
            $table->smallInteger('kibetsu')->nullable();
            $table->text('organization_name')->nullable();
            $table->text('equipment_contract')->nullable();
            $table->decimal('points', 12, 2)->nullable();
            $table->integer('games')->nullable();
            $table->integer('total_pin')->nullable();
            $table->decimal('average', 7, 2)->nullable();
            $table->bigInteger('prize_money')->nullable();
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['ranking_snapshot_id', 'ranking_rank'], 'pbrr_snapshot_rank_unique');
            $table->index('pro_bowler_id', 'pbrr_pro_bowler_idx');
            $table->index('license_no', 'pbrr_license_no_idx');
        });

        Schema::create('pro_bowler_seed_lists', function (Blueprint $table) {
            $table->id();
            $table->integer('seed_year');
            $table->string('gender', 10);
            $table->string('seed_list_type', 50)->default('tournament_seed');
            $table->foreignId('source_ranking_snapshot_id')->nullable()->constrained('pro_bowler_ranking_snapshots')->nullOnDelete();
            $table->integer('base_ranking_year')->nullable();
            $table->integer('base_top_count')->default(24);
            $table->date('as_of_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seed_year', 'gender', 'seed_list_type', 'is_active'], 'pbsl_year_gender_type_active_idx');
            $table->index('source_ranking_snapshot_id', 'pbsl_source_snapshot_idx');
        });

        Schema::create('pro_bowler_seed_list_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seed_list_id')->constrained('pro_bowler_seed_lists')->cascadeOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('license_no')->nullable();
            $table->string('seed_category', 50);
            $table->integer('seed_rank')->nullable();
            $table->foreignId('ranking_snapshot_id')->nullable()->constrained('pro_bowler_ranking_snapshots')->nullOnDelete();
            $table->integer('ranking_rank')->nullable();
            $table->foreignId('source_tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->foreignId('pro_bowler_title_id')->nullable()->constrained('pro_bowler_titles')->nullOnDelete();
            $table->integer('priority_order')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['seed_list_id', 'license_no', 'seed_category'], 'pbslp_list_license_category_unique');
            $table->index(['seed_list_id', 'priority_order'], 'pbslp_list_priority_idx');
            $table->index('pro_bowler_id', 'pbslp_pro_bowler_idx');
            $table->index('seed_category', 'pbslp_seed_category_idx');
        });

        Schema::create('tournament_seed_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('license_no')->nullable();
            $table->string('seed_source_type', 50);
            $table->foreignId('seed_list_player_id')->nullable()->constrained('pro_bowler_seed_list_players')->nullOnDelete();
            $table->foreignId('ranking_snapshot_id')->nullable()->constrained('pro_bowler_ranking_snapshots')->nullOnDelete();
            $table->integer('ranking_rank')->nullable();
            $table->foreignId('source_tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->foreignId('pro_bowler_title_id')->nullable()->constrained('pro_bowler_titles')->nullOnDelete();
            $table->integer('priority_order')->nullable();
            $table->string('display_label', 50)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tournament_id', 'license_no', 'seed_source_type'], 'tsp_tournament_license_source_unique');
            $table->index(['tournament_id', 'priority_order'], 'tsp_tournament_priority_idx');
            $table->index('pro_bowler_id', 'tsp_pro_bowler_idx');
            $table->index('seed_source_type', 'tsp_seed_source_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_seed_players');
        Schema::dropIfExists('pro_bowler_seed_list_players');
        Schema::dropIfExists('pro_bowler_seed_lists');
        Schema::dropIfExists('pro_bowler_ranking_rows');
        Schema::dropIfExists('pro_bowler_ranking_snapshots');
    }
};