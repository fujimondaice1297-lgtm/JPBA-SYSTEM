<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) column（既にあっても落ちない）
        DB::statement("ALTER TABLE approved_ball_pro_bowler ADD COLUMN IF NOT EXISTS pro_bowler_id bigint NULL");

        // 2) index（既にあっても落ちない）
        DB::statement("CREATE INDEX IF NOT EXISTS approved_ball_pro_bowler_pro_bowler_id_idx ON approved_ball_pro_bowler (pro_bowler_id)");

        // 3) backfill（license_no -> id）
        DB::statement(<<<'SQL'
UPDATE approved_ball_pro_bowler abpb
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = abpb.pro_bowler_license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE abpb.pro_bowler_id IS NULL
  AND abpb.pro_bowler_license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = abpb.pro_bowler_license_no
  );
SQL);

        // 4) FK（同じ列に pro_bowlers へのFKが無い場合のみ追加）
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    JOIN pg_class rt ON rt.oid = c.confrelid
    JOIN unnest(c.conkey) AS k(attnum) ON TRUE
    JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum
    WHERE c.contype = 'f'
      AND t.relname = 'approved_ball_pro_bowler'
      AND rt.relname = 'pro_bowlers'
      AND a.attname = 'pro_bowler_id'
  ) THEN
    ALTER TABLE approved_ball_pro_bowler
      ADD CONSTRAINT approved_ball_pro_bowler_pro_bowler_id_fk
      FOREIGN KEY (pro_bowler_id) REFERENCES pro_bowlers(id)
      ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        // 非破壊方針：列自体は落とさない（既存DBに元々あった可能性を排除できないため）
        DB::statement("ALTER TABLE approved_ball_pro_bowler DROP CONSTRAINT IF EXISTS approved_ball_pro_bowler_pro_bowler_id_fk");
        DB::statement("DROP INDEX IF EXISTS approved_ball_pro_bowler_pro_bowler_id_idx");
    }
};
