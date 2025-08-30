<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\District;

class DistrictSeeder extends Seeder
{
    public function run()
    {
    $districts = [
        ['name' => 'hokkaido', 'label' => '北海道'],
        ['name' => 'tohoku', 'label' => '東北'],
        ['name' => 'kitakanto', 'label' => '北関東'],
        ['name' => 'saitama', 'label' => '埼玉'],
        ['name' => 'chiba', 'label' => '千葉'],
        ['name' => 'joto', 'label' => '城東'],
        ['name' => 'jonan', 'label' => '城南'],
        ['name' => 'josai', 'label' => '城西'],
        ['name' => 'santama', 'label' => '三多摩'],
        ['name' => 'kanagawa_east', 'label' => '神奈川・東'],
        ['name' => 'kanagawa_west', 'label' => '神奈川・西'],
        ['name' => 'shizuoka', 'label' => '静岡'],
        ['name' => 'koshinetsu', 'label' => '甲信越'],
        ['name' => 'tokai', 'label' => '東海'],
        ['name' => 'hokuriku', 'label' => '北陸'],
        ['name' => 'kansai_east', 'label' => '関西・東'],
        ['name' => 'kansai_west', 'label' => '関西・西'],
        ['name' => 'kansai_south', 'label' => '関西・南'],
        ['name' => 'chugokushikoku', 'label' => '中国四国'],
        ['name' => 'kyushu_north', 'label' => '九州・北'],
        ['name' => 'kyushu_south', 'label' => '九州･南／沖縄'],
        ['name' => 'overseas', 'label' => '海外'],
    ];

    foreach ($districts as $district) {
        District::updateOrCreate(
            ['label' => $district['label']],
            ['name' => $district['name'], 'label' => $district['label']]
        );
    }
    }


}

