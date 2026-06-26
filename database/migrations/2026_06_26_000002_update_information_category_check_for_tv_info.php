<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT_NAME = 'informations_category_check';

    public function up(): void
    {
        DB::statement('ALTER TABLE informations DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
        DB::statement("
            ALTER TABLE informations
            ADD CONSTRAINT " . self::CONSTRAINT_NAME . "
            CHECK (
                category IS NULL
                OR category IN ('NEWS','大会','TV情報','ｲﾝｽﾄﾗｸﾀｰ','イベント')
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE informations DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
        DB::statement("
            ALTER TABLE informations
            ADD CONSTRAINT " . self::CONSTRAINT_NAME . "
            CHECK (
                category IS NULL
                OR category IN ('NEWS','イベント','大会','ｲﾝｽﾄﾗｸﾀｰ')
            )
        ");
    }
};
