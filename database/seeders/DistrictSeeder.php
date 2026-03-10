<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run()
    {
        $districts = [
            ['name' => 'hokkaido',       'label' => '北海道'],
            ['name' => 'tohoku',         'label' => '東北'],
            ['name' => 'kitakanto',      'label' => '北関東'],
            ['name' => 'saitama',        'label' => '埼玉'],
            ['name' => 'chiba',          'label' => '千葉'],
            ['name' => 'joto',           'label' => '城東'],
            ['name' => 'jonan',          'label' => '城南'],
            ['name' => 'josai',          'label' => '城西'],
            ['name' => 'santama',        'label' => '三多摩'],
            ['name' => 'kanagawa_east',  'label' => '神奈川東'],
            ['name' => 'kanagawa_west',  'label' => '神奈川西'],
            ['name' => 'shizuoka',       'label' => '静岡'],
            ['name' => 'koshinetsu',     'label' => '甲信越'],
            ['name' => 'tokai',          'label' => '東海'],
            ['name' => 'hokuriku',       'label' => '北陸'],
            ['name' => 'kansai_east',    'label' => '関西東'],
            ['name' => 'kansai_west',    'label' => '関西西'],
            ['name' => 'kansai_south',   'label' => '関西南'],
            ['name' => 'chugokushikoku', 'label' => '中国四国'],
            ['name' => 'kyushu_north',   'label' => '九州北'],
            ['name' => 'kyushu_south',   'label' => '九州南'],
            ['name' => 'overseas',       'label' => '海外'],
            ['name' => 'not_applicable', 'label' => '該当なし'],
        ];

        foreach ($districts as $district) {
            District::updateOrCreate(
                ['name' => $district['name']],
                ['name' => $district['name'], 'label' => $district['label']]
            );
        }
    }
}