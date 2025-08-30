<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 既に列があるなら何もしない
        if (Schema::hasColumn('users', 'pro_bowler_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // 列が無い環境のみ追加
            $table->unsignedBigInteger('pro_bowler_id')->nullable()->unique()->after('is_admin');
        });

        // 外部キーを張りたい場合だけ（無ければ省略OK）
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('pro_bowler_id')
                      ->references('id')->on('pro_bowlers')
                      ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // 既に外部キーがある／開発環境が違う等で失敗しても無視
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'pro_bowler_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // ある場合だけ落とす（名前が自動生成の場合があるため try-catch）
            try { $table->dropForeign(['pro_bowler_id']); } catch (\Throwable $e) {}
            try { $table->dropUnique('users_pro_bowler_id_unique'); } catch (\Throwable $e) {}
            $table->dropColumn('pro_bowler_id');
        });
    }
};
