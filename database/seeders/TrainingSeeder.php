<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // ← これが足りてなかった
use Illuminate\Support\Carbon;     // （now()使うなら無くても動くけど一応）

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('trainings')->updateOrInsert(
            ['code' => 'mandatory'],
            [
                'name' => 'トーナメントプレイヤー講習会',
                'valid_for_months' => 36,
                'mandatory' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
