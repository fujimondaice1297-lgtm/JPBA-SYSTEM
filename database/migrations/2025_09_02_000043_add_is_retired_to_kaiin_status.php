<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaiin_status', function (Blueprint $table) {
            // 既存運用の del_flg とは別に、「退会扱い」を明示するフラグを追加
            if (!Schema::hasColumn('kaiin_status', 'is_retired')) {
                $table->boolean('is_retired')->default(false);
            }
        });

        // 既存データを一括でマーク（案B：マスタ側で判定を持つ）
        // 退会届 / 除名 / 死亡 を退会扱いにする
        DB::table('kaiin_status')
            ->whereIn('name', ['退会届', '除名', '死亡'])
            ->update(['is_retired' => true]);

        // 念のため、その他は false に寄せる（既にdefault falseだが明示）
        DB::table('kaiin_status')
            ->whereNotIn('name', ['退会届', '除名', '死亡'])
            ->update(['is_retired' => false]);
    }

    public function down(): void
    {
        Schema::table('kaiin_status', function (Blueprint $table) {
            if (Schema::hasColumn('kaiin_status', 'is_retired')) {
                $table->dropColumn('is_retired');
            }
        });
    }
};
