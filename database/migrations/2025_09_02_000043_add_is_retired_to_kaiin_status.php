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

        // 既存データを正本でマーク（現役/退会ステータス判定用）
        // 死亡 / 除名 / 退会届 を退会扱いにする
        DB::table('kaiin_status')
            ->whereIn('name', ['死亡', '除名', '退会届'])
            ->update(['is_retired' => true]);

        // それ以外は false に統一
        DB::table('kaiin_status')
            ->whereNotIn('name', ['死亡', '除名', '退会届'])
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