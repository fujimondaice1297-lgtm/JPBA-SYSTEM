<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) カラム追加（既にあっても落ちないようにガード）
        if (!Schema::hasColumn('registered_balls', 'pro_bowler_id')) {
            Schema::table('registered_balls', function (Blueprint $table) {
                // FKは“今は壊さない”方針なので、まず nullable で追加
                $table->foreignId('pro_bowler_id')->nullable();
                $table->index('pro_bowler_id', 'registered_balls_pro_bowler_id_idx');
            });
        }

        // 2) 既存の license_no から backfill（一致する分だけ埋める）
        // ※0件でもOK。将来データが入ったときもこのマイグレーションは再実行されません。
        DB::statement("
            UPDATE registered_balls rb
            SET pro_bowler_id = pb.id
            FROM pro_bowlers pb
            WHERE rb.pro_bowler_id IS NULL
              AND pb.license_no = rb.license_no
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('registered_balls', 'pro_bowler_id')) {
            Schema::table('registered_balls', function (Blueprint $table) {
                // index -> column の順で落とす
                $table->dropIndex('registered_balls_pro_bowler_id_idx');
                $table->dropColumn('pro_bowler_id');
            });
        }
    }
};
