<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CalendarDay;

class CalendarDaySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['date'=>'2025-01-01','holiday_name'=>'元日','is_holiday'=>1],
            ['date'=>'2025-01-13','holiday_name'=>'成人の日','is_holiday'=>1],
            ['date'=>'2025-02-11','holiday_name'=>'建国記念の日','is_holiday'=>1],
            ['date'=>'2025-02-23','holiday_name'=>'天皇誕生日','is_holiday'=>1],
            ['date'=>'2025-02-24','holiday_name'=>'天皇誕生日 振替休日','is_holiday'=>1],
            ['date'=>'2025-03-20','holiday_name'=>'春分の日','is_holiday'=>1],
            ['date'=>'2025-04-29','holiday_name'=>'昭和の日','is_holiday'=>1],
            ['date'=>'2025-05-03','holiday_name'=>'憲法記念日','is_holiday'=>1],
            ['date'=>'2025-05-04','holiday_name'=>'みどりの日','is_holiday'=>1],
            ['date'=>'2025-05-05','holiday_name'=>'こどもの日','is_holiday'=>1],
            ['date'=>'2025-05-06','holiday_name'=>'こどもの日 振替休日','is_holiday'=>1],
            ['date'=>'2025-07-21','holiday_name'=>'海の日','is_holiday'=>1],
            ['date'=>'2025-08-11','holiday_name'=>'山の日','is_holiday'=>1],
            ['date'=>'2025-09-15','holiday_name'=>'敬老の日','is_holiday'=>1],
            ['date'=>'2025-09-23','holiday_name'=>'秋分の日','is_holiday'=>1],
            ['date'=>'2025-10-13','holiday_name'=>'スポーツの日','is_holiday'=>1],
            ['date'=>'2025-11-03','holiday_name'=>'文化の日','is_holiday'=>1],
            ['date'=>'2025-11-23','holiday_name'=>'勤労感謝の日','is_holiday'=>1],
            ['date'=>'2025-11-24','holiday_name'=>'勤労感謝の日 振替休日','is_holiday'=>1],
        ];
        foreach ($rows as $r) {
            CalendarDay::updateOrCreate(['date'=>$r['date']], $r);
        }
    }
}
