<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 目的：
         * - 既存DBに列が「すでにある」ケースでも落ちないようにする（非破壊）
         * - 可能なら backfill して、FK/INDEX も「存在しなければ」追加する
         */

        // 1) columns（既にあってもOK）
        DB::statement("ALTER TABLE tournament_participants ADD COLUMN IF NOT EXISTS pro_bowler_id bigint NULL");
        DB::statement("ALTER TABLE tournament_results      ADD COLUMN IF NOT EXISTS pro_bowler_id bigint NULL");

        // 2) indexes（既にあってもOK：名前ベースで作成）
        DB::statement("CREATE INDEX IF NOT EXISTS tournament_participants_pro_bowler_id_idx ON tournament_participants (pro_bowler_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS tournament_results_pro_bowler_id_idx      ON tournament_results (pro_bowler_id)");

        // 3) backfill（pro_bowler_license_no -> pro_bowlers.license_no -> id）
        DB::statement(<<<'SQL'
UPDATE tournament_participants tp
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = tp.pro_bowler_license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE tp.pro_bowler_id IS NULL
  AND tp.pro_bowler_license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = tp.pro_bowler_license_no
  );
SQL);

        DB::statement(<<<'SQL'
UPDATE tournament_results tr
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = tr.pro_bowler_license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE tr.pro_bowler_id IS NULL
  AND tr.pro_bowler_license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = tr.pro_bowler_license_no
  );
SQL);

        // 4) foreign keys（「同列に pro_bowlers へのFKが無い場合のみ」追加）
        // tournament_participants
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
      AND t.relname = 'tournament_participants'
      AND rt.relname = 'pro_bowlers'
      AND a.attname = 'pro_bowler_id'
  ) THEN
    ALTER TABLE tournament_participants
      ADD CONSTRAINT tournament_participants_pro_bowler_id_fk
      FOREIGN KEY (pro_bowler_id) REFERENCES pro_bowlers(id)
      ON DELETE SET NULL;
  END IF;
END $$;
SQL);

        // tournament_results
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
      AND t.relname = 'tournament_results'
      AND rt.relname = 'pro_bowlers'
      AND a.attname = 'pro_bowler_id'
  ) THEN
    ALTER TABLE tournament_results
      ADD CONSTRAINT tournament_results_pro_bowler_id_fk
      FOREIGN KEY (pro_bowler_id) REFERENCES pro_bowlers(id)
      ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        /**
         * 非破壊方針：
         * - すでに列が存在していた可能性があるため、down で列自体は落とさない
         * - 追加した可能性が高い index / fk（名前が一致するもの）だけ落とす
         */
        DB::statement("ALTER TABLE tournament_participants DROP CONSTRAINT IF EXISTS tournament_participants_pro_bowler_id_fk");
        DB::statement("ALTER TABLE tournament_results      DROP CONSTRAINT IF EXISTS tournament_results_pro_bowler_id_fk");

        DB::statement("DROP INDEX IF EXISTS tournament_participants_pro_bowler_id_idx");
        DB::statement("DROP INDEX IF EXISTS tournament_results_pro_bowler_id_idx");
    }
};
