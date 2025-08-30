<?php

namespace App\Observers;

use App\Models\Tournament;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TournamentObserver
{
    public function saved(Tournament $t){ $this->bust($t); }
    public function deleted(Tournament $t){ $this->bust($t); }

    private function bust(Tournament $t): void
    {
        // 年間キー
        $years = [];
        if ($t->start_date) $years[] = Carbon::parse($t->start_date)->year;
        if ($t->end_date)   $years[] = Carbon::parse($t->end_date)->year;
        $years[] = now()->year;
        foreach (array_unique($years) as $y) {
            Cache::forget("calendar:annual:$y");
        }

        // 月間キー（大会期間にかすってる月を全部消す）
        if ($t->start_date && $t->end_date) {
            $d = Carbon::parse($t->start_date)->startOfMonth();
            $end = Carbon::parse($t->end_date)->endOfMonth();
            while ($d <= $end) {
                Cache::forget("calendar:monthly:{$d->year}:{$d->month}");
                $d->addMonth();
            }
        }
    }
}