<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instructors')) {
            return;
        }
        if (!Schema::hasColumn('instructors', 'pro_bowler_id')) {
            return;
        }

        // index（存在しても落ちない）
        DB::statement('CREATE INDEX IF NOT EXISTS instructors_pro_bowler_id_idx ON instructors (pro_bowler_id)');

        // FK（存在しても落ちない）
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE c.contype = 'f'
      AND t.relname = 'instructors'
      AND c.conname = 'instructors_pro_bowler_id_fk'
  ) THEN
    ALTER TABLE instructors
      ADD CONSTRAINT instructors_pro_bowler_id_fk
      FOREIGN KEY (pro_bowler_id) REFERENCES pro_bowlers(id)
      ON DELETE SET NULL;
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE instructors DROP CONSTRAINT IF EXISTS instructors_pro_bowler_id_fk');
        DB::statement('DROP INDEX IF EXISTS instructors_pro_bowler_id_idx');
    }
};