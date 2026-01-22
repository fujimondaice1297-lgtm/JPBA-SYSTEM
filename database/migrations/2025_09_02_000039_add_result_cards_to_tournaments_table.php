<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // PostgreSQL でも MySQL でも動くように json を使います
            if (!Schema::hasColumn('tournaments', 'result_cards')) {
                $table->json('result_cards')->nullable(); // 優勝者・トーナメント用
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'result_cards')) {
                $table->dropColumn('result_cards');
            }
        });
    }
};
