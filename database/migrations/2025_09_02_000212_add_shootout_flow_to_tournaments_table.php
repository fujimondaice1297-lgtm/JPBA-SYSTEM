<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tournaments', 'shootout_qualifier_count')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->unsignedInteger('shootout_qualifier_count')->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'shootout_seed_source_result_code')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('shootout_seed_source_result_code', 64)->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'shootout_format')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('shootout_format', 32)->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'shootout_settings')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->json('shootout_settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'shootout_qualifier_count',
            'shootout_seed_source_result_code',
            'shootout_format',
            'shootout_settings',
        ], fn (string $column) => Schema::hasColumn('tournaments', $column)));

        if (!empty($columns)) {
            Schema::table('tournaments', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};