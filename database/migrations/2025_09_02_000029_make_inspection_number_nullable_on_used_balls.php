<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 方法A: doctrine/dbal が入ってるならこっち（推奨）
        // $table->change() を使うには doctrine/dbal が必要
        if (class_exists(\Doctrine\DBAL\Types\Type::class)) {
            Schema::table('used_balls', function (Blueprint $table) {
                $table->string('inspection_number')->nullable()->change();
                // 仮登録を許すなら念のため expires_at も nullable に（既にnullableなら問題なし）
                $table->date('expires_at')->nullable()->change();
            });
        } else {
            // 方法B: 生SQL（PostgreSQL）
            DB::statement('ALTER TABLE used_balls ALTER COLUMN inspection_number DROP NOT NULL;');
            // 念のため（既にNULL可なら何も起きない）
            DB::statement('ALTER TABLE used_balls ALTER COLUMN expires_at DROP NOT NULL;');
        }
    }

    public function down(): void
    {
        // 逆マイグレーション：NULLがあると NOT NULL に戻せないので安全側にIFで弾くか、生SQLで埋める必要あり
        // ここでは慎重に「NOT NULLには戻さない」実装にしておく
    }
};
