<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('approved_balls', function (Blueprint $t) {
            $t->date('release_date')->nullable()->after('id');
        });

        // 年→日付（1月1日）に寄せる
        DB::statement("
            UPDATE approved_balls
            SET release_date = make_date(release_year, 1, 1)
            WHERE release_year IS NOT NULL AND release_date IS NULL
        ");

        // もう年は不要（残したいならこの行はコメントアウト）
        Schema::table('approved_balls', function (Blueprint $t) {
            $t->dropColumn('release_year');
        });
    }

    public function down(): void
    {
        Schema::table('approved_balls', function (Blueprint $t) {
            $t->unsignedSmallInteger('release_year')->nullable()->after('id');
        });

        DB::statement("
            UPDATE approved_balls
            SET release_year = EXTRACT(YEAR FROM release_date)::int
            WHERE release_date IS NOT NULL AND release_year IS NULL
        ");

        Schema::table('approved_balls', function (Blueprint $t) {
            $t->dropColumn('release_date');
        });
    }
};
