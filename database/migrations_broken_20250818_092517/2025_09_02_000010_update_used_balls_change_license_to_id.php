<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('used_balls', function (Blueprint $table) {
            // 不要なカラム削除（あれば）
            if (Schema::hasColumn('used_balls', 'pro_bowler_license_no')) {
                $table->dropColumn('pro_bowler_license_no');
            }

            // 外部キーとして pro_bowler_id を追加（なければ）
            if (!Schema::hasColumn('used_balls', 'pro_bowler_id')) {
                $table->foreignId('pro_bowler_id')
                    ->nullable() // ← 一旦 nullable にして
                    ->after('id')
                    ->constrained('pro_bowler_profiles')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('used_balls', function (Blueprint $table) {
            $table->dropForeign(['pro_bowler_id']);
            $table->dropColumn('pro_bowler_id');
            $table->string('pro_bowler_license_no');
        });
    }
};

