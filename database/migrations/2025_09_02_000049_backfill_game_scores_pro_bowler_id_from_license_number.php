<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 非破壊：存在しない環境でも落ちないようにガード
        if (!Schema::hasTable('game_scores')) {
            return;
        }
        if (!Schema::hasColumn('game_scores', 'pro_bowler_id')) {
            return;
        }
        if (!Schema::hasColumn('game_scores', 'license_number')) {
            return;
        }

        // backfill: game_scores.license_number -> pro_bowlers.license_no -> id
        DB::statement(<<<'SQL'
UPDATE game_scores gs
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = gs.license_number
  ORDER BY pb.id
  LIMIT 1
)
WHERE gs.pro_bowler_id IS NULL
  AND gs.license_number IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = gs.license_number
  );
SQL);
    }

    public function down(): void
    {
        // 非破壊方針：埋め戻しは戻さない（既存データの意味を壊さないため）
    }
};