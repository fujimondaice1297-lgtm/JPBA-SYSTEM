<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('sidebar_schedule')->nullable();     // 右サイド：日程＆PDF/URL
            $table->json('award_highlights')->nullable();     // 右サイド：褒章フォト
            $table->json('gallery_items')->nullable();        // 終了後ギャラリー
            $table->json('simple_result_pdfs')->nullable();   // 簡易速報PDF（任意複数）
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['sidebar_schedule','award_highlights','gallery_items','simple_result_pdfs']);
        });
    }
};
