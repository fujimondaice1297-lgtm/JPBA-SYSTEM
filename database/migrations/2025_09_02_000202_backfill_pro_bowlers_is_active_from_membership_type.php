<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pro_bowlers')) {
            return;
        }

        if (!Schema::hasColumn('pro_bowlers', 'membership_type') || !Schema::hasColumn('pro_bowlers', 'is_active')) {
            return;
        }

        $retiredNames = ['死亡', '除名', '退会届'];

        DB::transaction(function () use (&$retiredNames) {
            if (
                Schema::hasTable('kaiin_status') &&
                Schema::hasColumn('kaiin_status', 'name') &&
                Schema::hasColumn('kaiin_status', 'is_retired')
            ) {
                DB::table('kaiin_status')
                    ->whereIn('name', ['死亡', '除名', '退会届'])
                    ->update(['is_retired' => true]);

                DB::table('kaiin_status')
                    ->whereNotIn('name', ['死亡', '除名', '退会届'])
                    ->update(['is_retired' => false]);

                $retiredNames = DB::table('kaiin_status')
                    ->where('is_retired', true)
                    ->pluck('name')
                    ->filter()
                    ->map(fn ($name) => trim((string) $name))
                    ->values()
                    ->all();
            }

            if (empty($retiredNames)) {
                $retiredNames = ['死亡', '除名', '退会届'];
            }

            DB::table('pro_bowlers')
                ->whereIn('membership_type', $retiredNames)
                ->update(['is_active' => false]);

            DB::table('pro_bowlers')
                ->where(function ($q) use ($retiredNames) {
                    $q->whereNull('membership_type')
                      ->orWhereNotIn('membership_type', $retiredNames);
                })
                ->update(['is_active' => true]);
        });
    }

    public function down(): void
    {
        // 以前の is_active 状態を一意に復元できないため no-op
    }
};