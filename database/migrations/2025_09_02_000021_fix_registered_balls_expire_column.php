<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) expired_at の値を expires_at にバックフィル
        if (Schema::hasColumn('registered_balls', 'expired_at')) {
            if (Schema::hasColumn('registered_balls', 'expires_at')) {
                DB::statement('UPDATE registered_balls SET expires_at = COALESCE(expires_at, expired_at)');
                // 2) expired_at を削除
                Schema::table('registered_balls', function (Blueprint $table) {
                    $table->dropColumn('expired_at');
                });
            } else {
                // expires_at が無い環境では rename する
                // renameColumn は doctrine/dbal が必要です。なければ下の raw SQL を使ってください。
                // Schema::table('registered_balls', function (Blueprint $table) {
                //     $table->renameColumn('expired_at', 'expires_at');
                // });

                DB::statement('ALTER TABLE registered_balls RENAME COLUMN expired_at TO expires_at');
            }
        }

        // 3) expires_at を NULL 許可に（NOT NULL を外す）
        if (Schema::hasColumn('registered_balls', 'expires_at')) {
            DB::statement('ALTER TABLE registered_balls ALTER COLUMN expires_at DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // 必要なら元に戻す処理を入れてください（多くの場合は不要）
    }
};
