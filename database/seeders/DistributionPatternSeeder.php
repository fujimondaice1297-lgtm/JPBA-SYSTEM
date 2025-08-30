<?php

namespace Database\Seeders;

use App\Models\DistributionPattern;
use Illuminate\Database\Seeder;

class DistributionPatternSeeder extends Seeder
{
    public function run()
    {
        DistributionPattern::create([
            'name' => '賞金配分パターンA',
            'type' => 'prize', 
        ]);

        DistributionPattern::create([
            'name' => '賞金配分パターンB',
            'type' => 'prize', 
        ]);

        DistributionPattern::create([
            'name' => '通常ポイント配分',
            'type' => 'point',
        ]);

        DistributionPattern::create([
            'name' => 'ポイント配分A',
            'type' => 'point',
        ]);
    }
}

