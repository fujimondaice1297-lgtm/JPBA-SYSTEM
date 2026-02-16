<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) 追加（非破壊）：license_no 文字列は残したまま、pro_bowler_id（nullable）を追加
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->foreignId('pro_bowler_id')
                ->nullable()
                ->after('pro_bowler_license_no')
                ->constrained('pro_bowlers')
                ->nullOnDelete();
        });

        Schema::table('tournament_results', function (Blueprint $table) {
            $table->foreignId('pro_bowler_id')
                ->nullable()
                ->after('pro_bowler_license_no')
                ->constrained('pro_bowlers')
                ->nullOnDelete();
        });

        // 2) バックフィル：既存データを pro_bowlers.license_no と突合して pro_bowler_id を埋める（突合できない行は NULL のまま）
        DB::statement("
            UPDATE tournament_participants tp
            SET pro_bowler_id = pb.id
            FROM pro_bowlers pb
            WHERE tp.pro_bowler_id IS NULL
              AND tp.pro_bowler_license_no = pb.license_no
        ");

        DB::statement("
            UPDATE tournament_results tr
            SET pro_bowler_id = pb.id
            FROM pro_bowlers pb
            WHERE tr.pro_bowler_id IS NULL
              AND tr.pro_bowler_license_no = pb.license_no
        ");
    }

    public function down(): void
    {
        // 追加した列だけを戻す（既存の license_no 文字列列はそのまま）
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropForeign(['pro_bowler_id']);
            $table->dropColumn('pro_bowler_id');
        });

        Schema::table('tournament_results', function (Blueprint $table) {
            $table->dropForeign(['pro_bowler_id']);
            $table->dropColumn('pro_bowler_id');
        });
    }
};
