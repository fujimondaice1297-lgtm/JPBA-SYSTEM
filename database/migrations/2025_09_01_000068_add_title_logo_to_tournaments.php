<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // タイトル左に表示するロゴ（PNG/JPG等）
            if (!Schema::hasColumn('tournaments', 'title_logo_path')) {
                $table->string('title_logo_path')->nullable()->after('hero_image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'title_logo_path')) {
                $table->dropColumn('title_logo_path');
            }
        });
    }
};
