<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('districts')) {
            return;
        }

        $canonical = DB::table('districts')
            ->where('name', 'not_applicable')
            ->first(['id', 'name', 'label']);

        $legacy = DB::table('districts')
            ->where('name', '該当なし')
            ->first(['id', 'name', 'label']);

        if ($canonical && $legacy && (int) $canonical->id !== (int) $legacy->id) {
            DB::transaction(function () use ($canonical, $legacy) {
                DB::table('districts')
                    ->where('id', $canonical->id)
                    ->update(['label' => '該当なし']);

                if (Schema::hasTable('pro_bowlers') && Schema::hasColumn('pro_bowlers', 'district_id')) {
                    DB::table('pro_bowlers')
                        ->where('district_id', $legacy->id)
                        ->update(['district_id' => $canonical->id]);
                }

                if (Schema::hasTable('instructors') && Schema::hasColumn('instructors', 'district_id')) {
                    DB::table('instructors')
                        ->where('district_id', $legacy->id)
                        ->update(['district_id' => $canonical->id]);
                }

                $proBowlerRefs = Schema::hasTable('pro_bowlers') && Schema::hasColumn('pro_bowlers', 'district_id')
                    ? DB::table('pro_bowlers')->where('district_id', $legacy->id)->count()
                    : 0;

                $instructorRefs = Schema::hasTable('instructors') && Schema::hasColumn('instructors', 'district_id')
                    ? DB::table('instructors')->where('district_id', $legacy->id)->count()
                    : 0;

                if ($proBowlerRefs === 0 && $instructorRefs === 0) {
                    DB::table('districts')
                        ->where('id', $legacy->id)
                        ->delete();
                }
            });

            return;
        }

        if (!$canonical && $legacy) {
            DB::table('districts')
                ->where('id', $legacy->id)
                ->update([
                    'name'  => 'not_applicable',
                    'label' => '該当なし',
                ]);

            return;
        }

        if ($canonical) {
            DB::table('districts')
                ->where('id', $canonical->id)
                ->update(['label' => '該当なし']);
        }
    }

    public function down(): void
    {
        // 重複マスタを復元すると再発するため、down は no-op とする。
    }
};