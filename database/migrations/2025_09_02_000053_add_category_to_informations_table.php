<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT_NAME = 'informations_category_check';

    public function up(): void
    {
        // 1) column + index（追加のみ）
        if (!Schema::hasColumn('informations', 'category')) {
            Schema::table('informations', function (Blueprint $table) {
                $table->string('category', 32)->nullable()->after('title');
            });

            // LaravelのdropIndex判定が面倒なので、Postgres側で安全に作る
            DB::statement("CREATE INDEX IF NOT EXISTS informations_category_index ON informations (category)");
        }

        // 2) 値制約（NULL許容、許容値のみ） ※既存データを壊さない
        // すでに同名制約があれば何もしない
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = '" . self::CONSTRAINT_NAME . "'
                ) THEN
                    ALTER TABLE informations
                    ADD CONSTRAINT " . self::CONSTRAINT_NAME . "
                    CHECK (
                        category IS NULL
                        OR category IN ('NEWS','イベント','大会','ｲﾝｽﾄﾗｸﾀｰ')
                    );
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        // 制約削除
        DB::statement("ALTER TABLE informations DROP CONSTRAINT IF EXISTS " . self::CONSTRAINT_NAME);

        // index削除
        DB::statement("DROP INDEX IF EXISTS informations_category_index");

        // column削除
        if (Schema::hasColumn('informations', 'category')) {
            Schema::table('informations', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};