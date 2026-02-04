<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (!Schema::hasColumn('pro_bowlers', 'titles_count')) {
                $table->unsignedInteger('titles_count')->default(0)->comment('タイトル保有数（pro_bowler_titles 件数キャッシュ）');
                $table->index('titles_count');
            }
        });

        // 既存データを pro_bowler_titles から埋める
        if (Schema::hasTable('pro_bowler_titles') && Schema::hasColumn('pro_bowler_titles', 'pro_bowler_id')) {
            DB::statement("
                UPDATE pro_bowlers pb
                SET titles_count = sub.cnt
                FROM (
                    SELECT pro_bowler_id, COUNT(*)::int AS cnt
                    FROM pro_bowler_titles
                    GROUP BY pro_bowler_id
                ) sub
                WHERE pb.id = sub.pro_bowler_id
            ");
        }
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (Schema::hasColumn('pro_bowlers', 'titles_count')) {
                // Laravel の規約インデックス名
                try { $table->dropIndex('pro_bowlers_titles_count_index'); } catch (\Throwable $e) {}
                $table->dropColumn('titles_count');
            }
        });
    }
};
