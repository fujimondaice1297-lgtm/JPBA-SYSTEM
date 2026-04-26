<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tournaments', 'single_elimination_qualifier_count')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->unsignedInteger('single_elimination_qualifier_count')->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'single_elimination_seed_source_result_code')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('single_elimination_seed_source_result_code', 64)->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'single_elimination_seed_policy')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('single_elimination_seed_policy', 32)->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'single_elimination_seed_settings')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->json('single_elimination_seed_settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'single_elimination_qualifier_count',
            'single_elimination_seed_source_result_code',
            'single_elimination_seed_policy',
            'single_elimination_seed_settings',
        ], fn (string $column) => Schema::hasColumn('tournaments', $column)));

        if (!empty($columns)) {
            Schema::table('tournaments', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};