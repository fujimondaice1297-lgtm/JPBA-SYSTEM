<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tournaments', 'result_carry_preset')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('result_carry_preset', 64)->nullable();
            });
        }

        if (!Schema::hasColumn('tournaments', 'result_carry_settings')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->json('result_carry_settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'result_carry_preset',
            'result_carry_settings',
        ], fn (string $column) => Schema::hasColumn('tournaments', $column)));

        if (!empty($columns)) {
            Schema::table('tournaments', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};