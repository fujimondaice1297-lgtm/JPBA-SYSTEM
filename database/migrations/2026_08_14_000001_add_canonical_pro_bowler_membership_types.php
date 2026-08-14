<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['第１シード', '第２シード', '海外プロ'] as $name) {
            DB::table('kaiin_status')->insertOrIgnore([
                'name' => $name,
                'reg_date' => $now,
                'del_flg' => false,
                'update_date' => $now,
                'created_by' => 'system',
                'updated_by' => 'system',
                'is_retired' => false,
            ]);
        }

        DB::table('pro_bowlers')->where('membership_type', '①第１シード')->update(['membership_type' => '第１シード']);
        DB::table('pro_bowlers')->where('membership_type', '②第２シード')->update(['membership_type' => '第２シード']);
        DB::table('pro_bowlers')->where('membership_type', '第1シード')->update(['membership_type' => '第１シード']);
        DB::table('pro_bowlers')->where('membership_type', '第2シード')->update(['membership_type' => '第２シード']);

        DB::table('kaiin_status')
            ->whereIn('name', ['①第１シード', '②第２シード', '名誉プロ・海外プロ'])
            ->update([
                'del_flg' => true,
                'is_retired' => false,
                'update_date' => $now,
                'updated_by' => 'system',
            ]);
    }

    public function down(): void
    {
        DB::table('pro_bowlers')->where('membership_type', '第１シード')->update(['membership_type' => '①第１シード']);
        DB::table('pro_bowlers')->where('membership_type', '第２シード')->update(['membership_type' => '②第２シード']);
        DB::table('pro_bowlers')->where('membership_type', '海外プロ')->update(['membership_type' => '名誉プロ・海外プロ']);

        DB::table('kaiin_status')
            ->whereIn('name', ['①第１シード', '②第２シード', '名誉プロ・海外プロ'])
            ->update([
                'del_flg' => false,
                'is_retired' => false,
                'update_date' => now(),
                'updated_by' => 'system',
            ]);

        DB::table('kaiin_status')->whereIn('name', ['第１シード', '第２シード', '海外プロ'])->delete();
    }
};
