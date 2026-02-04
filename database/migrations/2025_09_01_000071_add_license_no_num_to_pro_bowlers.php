<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // license_no から数字部分を取り出した “検索用の数値列”
        DB::statement(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name   = 'pro_bowlers'
       AND column_name  = 'license_no_num'
  ) THEN
    ALTER TABLE public.pro_bowlers
      ADD COLUMN license_no_num integer GENERATED ALWAYS AS (
        NULLIF(regexp_replace(license_no, '[^0-9]', '', 'g'), '')::integer
      ) STORED;
  END IF;
END $$;
SQL);

        DB::statement("CREATE INDEX IF NOT EXISTS idx_pro_bowlers_license_no_num ON public.pro_bowlers (license_no_num)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_pro_bowlers_license_no_num");
        DB::statement("ALTER TABLE public.pro_bowlers DROP COLUMN IF EXISTS license_no_num");
    }
};
