<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_competitor_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('group_type', 20);
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->string('division', 80)->nullable();
            $table->unsignedSmallInteger('expected_member_count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'code'], 'tcg_tournament_code_unique');
            $table->index(['tournament_id', 'is_active'], 'tcg_tournament_active_idx');
        });

        Schema::create('tournament_competitor_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_group_id')
                ->constrained('tournament_competitor_groups')
                ->cascadeOnDelete();
            $table->foreignId('tournament_participant_id')
                ->constrained('tournament_participants')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('member_order')->default(1);
            $table->timestamps();

            $table->unique('tournament_participant_id', 'tcgm_participant_unique');
            $table->index(['competitor_group_id', 'member_order'], 'tcgm_group_order_idx');
        });

        Schema::create('tournament_aggregate_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('subject_type', 20)->default('individual');
            $table->string('gender', 1)->nullable();
            $table->boolean('require_all_sources')->default(true);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'code'], 'tad_tournament_code_unique');
            $table->index(['tournament_id', 'is_active'], 'tad_tournament_active_idx');
        });

        Schema::create('tournament_aggregate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aggregate_definition_id')
                ->constrained('tournament_aggregate_definitions')
                ->cascadeOnDelete();
            $table->foreignId('source_tournament_id')
                ->constrained('tournaments')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('stage')->nullable();
            $table->unsignedSmallInteger('game_from')->nullable();
            $table->unsignedSmallInteger('game_to')->nullable();
            $table->unsignedSmallInteger('expected_games_per_member')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['aggregate_definition_id', 'sort_order'],
                'tas_definition_order_idx'
            );
            $table->index(
                ['source_tournament_id', 'stage'],
                'tas_tournament_stage_idx'
            );
        });

        Schema::table('tournament_result_snapshots', function (Blueprint $table) {
            $table->foreignId('aggregate_definition_id')
                ->nullable()
                ->after('tournament_id')
                ->constrained('tournament_aggregate_definitions')
                ->nullOnDelete();
        });

        Schema::table('tournament_result_snapshot_rows', function (Blueprint $table) {
            $table->string('subject_type', 20)->default('individual')->after('ranking');
            $table->foreignId('competitor_group_id')
                ->nullable()
                ->after('subject_type')
                ->constrained('tournament_competitor_groups')
                ->nullOnDelete();
            $table->foreignId('amateur_bowler_id')
                ->nullable()
                ->after('pro_bowler_id')
                ->constrained('amateur_bowlers')
                ->nullOnDelete();
            $table->string('identity_key')->nullable()->after('entry_number');
            $table->unsignedSmallInteger('source_count')->default(0)->after('games');
            $table->boolean('is_complete')->default(true)->after('source_count');
            $table->json('breakdown')->nullable()->after('is_complete');

            $table->index(
                ['snapshot_id', 'competitor_group_id'],
                'trsr_snapshot_group_idx'
            );
            $table->index(
                ['snapshot_id', 'amateur_bowler_id'],
                'trsr_snapshot_amateur_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tournament_result_snapshot_rows', function (Blueprint $table) {
            $table->dropForeign(['competitor_group_id']);
            $table->dropForeign(['amateur_bowler_id']);
            $table->dropIndex('trsr_snapshot_group_idx');
            $table->dropIndex('trsr_snapshot_amateur_idx');
            $table->dropColumn([
                'subject_type',
                'competitor_group_id',
                'amateur_bowler_id',
                'identity_key',
                'source_count',
                'is_complete',
                'breakdown',
            ]);
        });

        Schema::table('tournament_result_snapshots', function (Blueprint $table) {
            $table->dropForeign(['aggregate_definition_id']);
            $table->dropColumn('aggregate_definition_id');
        });

        Schema::dropIfExists('tournament_aggregate_sources');
        Schema::dropIfExists('tournament_aggregate_definitions');
        Schema::dropIfExists('tournament_competitor_group_members');
        Schema::dropIfExists('tournament_competitor_groups');
    }
};
