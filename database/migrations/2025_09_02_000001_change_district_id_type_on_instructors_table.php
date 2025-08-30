<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Phase A で FK は貼ってない想定なので、"IF EXISTS" 付きで安全に落とす
        DB::statement('ALTER TABLE instructors DROP CONSTRAINT IF EXISTS instructors_district_id_foreign');
        DB::statement('DROP INDEX IF EXISTS instructors_district_id_index');

        // district_id を bigint -> varchar(255) に変える（PostgreSQL）
        DB::statement("ALTER TABLE instructors ALTER COLUMN district_id TYPE varchar(255) USING district_id::varchar");
        // 必要なら NOT NULL / NULL を調整（今は nullable 運用なのでこのまま）
        // DB::statement("ALTER TABLE instructors ALTER COLUMN district_id DROP NOT NULL");
    }

    public function down(): void
    {
        // 逆変換（文字列→bigint）
        // 文字列に数字以外が入ってたら失敗するから、運用上は注意
        DB::statement('ALTER TABLE instructors DROP CONSTRAINT IF EXISTS instructors_district_id_foreign');
        DB::statement('DROP INDEX IF EXISTS instructors_district_id_index');
        DB::statement("ALTER TABLE instructors ALTER COLUMN district_id TYPE bigint USING district_id::bigint");
        // DB::statement("ALTER TABLE instructors ALTER COLUMN district_id SET NOT NULL"); // 必要なら
    }
};
