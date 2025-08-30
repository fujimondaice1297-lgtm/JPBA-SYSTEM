<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // 男女区分: M/F/X（X=男女混合 or 未設定）
            if (!Schema::hasColumn('tournaments', 'gender')) {
                $table->enum('gender', ['M','F','X'])->default('X')->after('name')->comment('M=男子, F=女子, X=混合/未設定');
            }
            // 公認区分: official=公認, approved=承認, other=その他
            if (!Schema::hasColumn('tournaments', 'official_type')) {
                $table->enum('official_type', ['official','approved','other'])->default('official')->after('authorized_by')->comment('大会区分');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('tournaments', 'official_type')) {
                $table->dropColumn('official_type');
            }
        });
    }
};
