<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pro_dsp')) {
            return;
        }

        // 1) column（既にあっても落ちない）
        DB::statement("ALTER TABLE pro_dsp ADD COLUMN IF NOT EXISTS pro_bowler_id bigint NULL");

        // 2) index（既にあっても落ちない）
        DB::statement("CREATE INDEX IF NOT EXISTS pro_dsp_pro_bowler_id_idx ON pro_dsp (pro_bowler_id)");

        // 3) backfill（license_no -> id）
        DB::statement(<<<'SQL'
UPDATE pro_dsp pd
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = pd.license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE pd.pro_bowler_id IS NULL
  AND pd.license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = pd.license_no
  );
SQL);

        // 4) FK（存在しなければ追加）
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE c.contype = 'f'
      AND t.relname = 'pro_dsp'
      AND c.conname = 'pro_dsp_pro_bowler_id_fk'
  ) THEN
    ALTER TABLE pro_dsp
      ADD CONSTRAINT pro_dsp_pro_bowler_id_fk
      FOREIGN KEY (pro_bowler_id) REFERENCES pro_bowlers(id)
      ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        // 非破壊方針：列自体は落とさない（既存DBに元々あった可能性を排除できないため）
        DB::statement("ALTER TABLE pro_dsp DROP CONSTRAINT IF EXISTS pro_dsp_pro_bowler_id_fk");
        DB::statement("DROP INDEX IF EXISTS pro_dsp_pro_bowler_id_idx");
    }
};