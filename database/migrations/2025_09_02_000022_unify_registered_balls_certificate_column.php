<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // まず expires_at は NULL 許可に（前回の残りがある場合）
        if (Schema::hasColumn('registered_balls', 'expires_at')) {
            DB::statement('ALTER TABLE registered_balls ALTER COLUMN expires_at DROP NOT NULL');
        }

        // certificate_number → inspection_number に統一
        if (Schema::hasColumn('registered_balls', 'certificate_number')) {

            // inspection_number が無ければ作る（nullable）
            if (!Schema::hasColumn('registered_balls', 'inspection_number')) {
                Schema::table('registered_balls', function (Blueprint $table) {
                    $table->string('inspection_number')->nullable();
                });
            }

            // 既存値を移行
            DB::statement('UPDATE registered_balls
                           SET inspection_number = COALESCE(inspection_number, certificate_number)');

            // certificate_number の NOT NULL を先に外しておくと安全
            DB::statement('ALTER TABLE registered_balls ALTER COLUMN certificate_number DROP NOT NULL');

            // もう使わない列を削除
            // （DB によっては raw SQL の方が確実）
            DB::statement('ALTER TABLE registered_balls DROP COLUMN IF EXISTS certificate_number');
        }
    }

    public function down(): void
    {
        // 必要なら復元を書く（通常は不要）
    }
};
