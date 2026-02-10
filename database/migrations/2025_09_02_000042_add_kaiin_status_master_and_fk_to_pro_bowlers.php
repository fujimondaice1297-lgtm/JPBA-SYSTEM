<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) マスタ作成（CHECK制約は付けない）
        if (!Schema::hasTable('kaiin_status')) {
            Schema::create('kaiin_status', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name'); // 日本語/記号も許容する（CHECKで縛らない）
                $table->timestamp('reg_date')->nullable();
                $table->boolean('del_flg')->default(false);
                $table->timestamp('update_date')->nullable();
                $table->string('created_by', 20)->default('0');
                $table->string('updated_by', 20)->default('0');

                $table->unique('name', 'kaiin_status_name_unique');
            });
        }

        // 以前の試行で残っていた場合に備えて、CHECK制約があれば落とす
        DB::statement('ALTER TABLE kaiin_status DROP CONSTRAINT IF EXISTS kaiin_status_name_check');

        // unique が無い環境に備えて保険（PostgreSQL）
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS kaiin_status_name_unique ON kaiin_status (name)');

        // 2) 既存データの会員種別をマスタへ流し込み（2000件を手で直さない）
        $now = now();

        $names = [];
        if (Schema::hasTable('pro_bowlers') && Schema::hasColumn('pro_bowlers', 'membership_type')) {
            $names = DB::table('pro_bowlers')
                ->whereNotNull('membership_type')
                ->distinct()
                ->orderBy('membership_type')
                ->pluck('membership_type')
                ->all();
        }

        // 念のためフォールバック（pro_bowlers が空の環境など）
        if (empty($names)) {
            $names = [
                '①第１シード',
                '②第２シード',
                'その他',
                'トーナメントプロ',
                'プロインストラクター',
                '講習会出席者',
                '死亡',
                '除名',
                '退会届',
                '認定プロインストラクター',
                '名誉プロ・海外プロ',
            ];
        }

        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'reg_date' => $now,
                'del_flg' => false,
                'update_date' => $now,
                'created_by' => '0',
                'updated_by' => '0',
            ];
        }

        DB::table('kaiin_status')->insertOrIgnore($rows);

        // 3) FK（pro_bowlers.membership_type -> kaiin_status.name）
        // すでに同じ値をマスタに入れてあるので、既存データでも通る
        DB::statement('ALTER TABLE pro_bowlers DROP CONSTRAINT IF EXISTS pro_bowlers_membership_type_foreign');

        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->foreign('membership_type', 'pro_bowlers_membership_type_foreign')
                ->references('name')
                ->on('kaiin_status')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pro_bowlers DROP CONSTRAINT IF EXISTS pro_bowlers_membership_type_foreign');
        Schema::dropIfExists('kaiin_status');
    }
};
