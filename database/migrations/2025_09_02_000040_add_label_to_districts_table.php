<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('districts')) {
            return;
        }

        // 1) label が無ければ追加（表示名の正本）
        Schema::table('districts', function (Blueprint $table) {
            if (!Schema::hasColumn('districts', 'label')) {
                $table->string('label', 100)->nullable()->comment('表示用地区名（UIの正本）');
            }
        });

        // 2) name があるなら label を埋める（既存運用互換）
        $hasName  = Schema::hasColumn('districts', 'name');
        $hasLabel = Schema::hasColumn('districts', 'label');

        if ($hasName && $hasLabel) {
            DB::statement("
                UPDATE districts
                SET label = name
                WHERE (label IS NULL OR label = '')
                  AND (name IS NOT NULL AND name <> '')
            ");
        }

        // 3) label が全部埋まったなら NOT NULL に寄せたいが、
        //    既存データが不明なので、現時点では nullable のまま維持（事故防止）
    }

    public function down(): void
    {
        if (!Schema::hasTable('districts')) {
            return;
        }

        Schema::table('districts', function (Blueprint $table) {
            if (Schema::hasColumn('districts', 'label')) {
                $table->dropColumn('label');
            }
        });
    }
};
