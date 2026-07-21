<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->nullable()->unique();
            $table->string('recurrence_type', 30)->default('annual');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tournament_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_series_id')
                ->nullable()
                ->constrained('tournament_series')
                ->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('season_key', 50)->default('annual');
            $table->string('name');
            $table->unsignedSmallInteger('edition_no')->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['tournament_series_id', 'year', 'season_key'],
                'te_series_year_season_unique'
            );
        });

        Schema::create('tournament_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_series_id')
                ->nullable()
                ->constrained('tournament_series')
                ->nullOnDelete();
            $table->string('name');
            $table->string('code', 100)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tournament_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_template_id')
                ->constrained('tournament_templates')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('published');
            $table->json('settings');
            $table->text('change_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tournament_template_id', 'version'],
                'ttv_template_version_unique'
            );
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('tournament_series_id')
                ->nullable()
                ->after('id')
                ->constrained('tournament_series')
                ->nullOnDelete();
            $table->foreignId('tournament_edition_id')
                ->nullable()
                ->after('tournament_series_id')
                ->constrained('tournament_editions')
                ->nullOnDelete();
            $table->foreignId('tournament_template_version_id')
                ->nullable()
                ->after('tournament_edition_id')
                ->constrained('tournament_template_versions')
                ->nullOnDelete();
            $table->string('setup_status', 30)->default('draft')->after('name');
            $table->string('competition_type', 30)->default('singles')->after('setup_status');
            $table->boolean('include_annual_seeds')->default(true)->after('title_category');
            $table->unsignedInteger('annual_seed_rank_limit')->nullable()->after('include_annual_seeds');
            $table->boolean('auto_sync_priority_rules')->default(true)->after('annual_seed_rank_limit');
            $table->boolean('counts_for_official_points')->default(true)->after('auto_sync_priority_rules');
            $table->boolean('counts_for_average')->default(true)->after('counts_for_official_points');
            $table->boolean('counts_for_prize')->default(true)->after('counts_for_average');
            $table->string('title_scope', 30)->default('official')->after('counts_for_prize');
            $table->json('template_snapshot')->nullable()->after('title_scope');

            $table->index(['tournament_series_id', 'year'], 'tournaments_series_year_idx');
            $table->index(['tournament_edition_id', 'competition_type'], 'tournaments_edition_comp_idx');
        });

        DB::table('tournaments')
            ->where('title_category', 'season_trial')
            ->update(['title_scope' => 'season_trial']);
        DB::table('tournaments')
            ->where('title_category', 'excluded')
            ->update(['title_scope' => 'none']);

        Schema::create('tournament_entry_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 50);
            $table->unsignedInteger('priority_order')->nullable();
            $table->unsignedInteger('max_count')->nullable();
            $table->foreignId('source_tournament_id')
                ->nullable()
                ->constrained('tournaments')
                ->nullOnDelete();
            $table->foreignId('source_series_id')
                ->nullable()
                ->constrained('tournament_series')
                ->nullOnDelete();
            $table->json('parameters')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tournament_id', 'is_active'], 'ter_tournament_active_idx');
            $table->index(['tournament_id', 'rule_type'], 'ter_tournament_type_idx');
        });

        Schema::create('tournament_result_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('output_type', 30);
            $table->string('output_scope', 50);
            $table->foreignId('distribution_pattern_id')
                ->nullable()
                ->constrained('distribution_patterns')
                ->nullOnDelete();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['tournament_id', 'output_type', 'output_scope'],
                'tro_tournament_type_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_result_outputs');
        Schema::dropIfExists('tournament_entry_rules');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_template_version_id']);
            $table->dropForeign(['tournament_edition_id']);
            $table->dropForeign(['tournament_series_id']);
            $table->dropIndex('tournaments_series_year_idx');
            $table->dropIndex('tournaments_edition_comp_idx');
            $table->dropColumn([
                'tournament_series_id',
                'tournament_edition_id',
                'tournament_template_version_id',
                'setup_status',
                'competition_type',
                'include_annual_seeds',
                'annual_seed_rank_limit',
                'auto_sync_priority_rules',
                'counts_for_official_points',
                'counts_for_average',
                'counts_for_prize',
                'title_scope',
                'template_snapshot',
            ]);
        });

        Schema::dropIfExists('tournament_template_versions');
        Schema::dropIfExists('tournament_templates');
        Schema::dropIfExists('tournament_editions');
        Schema::dropIfExists('tournament_series');
    }
};
