<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        if (!Schema::hasColumn('users', 'pro_bowler_id')) {
            return;
        }

        // 1) pro_bowler_id が NULL の users を、license列から埋める（非破壊）
        // 優先順位：pro_bowler_license_no -> license_no
        DB::statement(<<<'SQL'
UPDATE users u
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = u.pro_bowler_license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE u.pro_bowler_id IS NULL
  AND u.pro_bowler_license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = u.pro_bowler_license_no
  );
SQL);

        DB::statement(<<<'SQL'
UPDATE users u
SET pro_bowler_id = (
  SELECT pb.id
  FROM pro_bowlers pb
  WHERE pb.license_no = u.license_no
  ORDER BY pb.id
  LIMIT 1
)
WHERE u.pro_bowler_id IS NULL
  AND u.license_no IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM pro_bowlers pb
    WHERE pb.license_no = u.license_no
  );
SQL);

        // 2) index（存在しても落ちない）
        DB::statement('CREATE INDEX IF NOT EXISTS users_pro_bowler_id_idx ON users (pro_bowler_id)');
    }

    public function down(): void
    {
        // 非破壊方針：埋め戻しは戻さない。indexだけ落とす。
        DB::statement('DROP INDEX IF EXISTS users_pro_bowler_id_idx');
    }
};