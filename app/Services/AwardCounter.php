<?php

namespace App\Services;

use App\Models\RecordType;
use App\Models\ProBowler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AwardCounter
{
    /** 指定選手の褒章数を集計して返す（都度集計：軽量） */
    public static function countsForBowlerId(int $bowlerId): array
    {
        $map = RecordType::where('pro_bowler_id', $bowlerId)
            ->select('record_type', DB::raw('COUNT(*) as c'))
            ->groupBy('record_type')
            ->pluck('c', 'record_type')
            ->all();

        $counts = [
            'perfect'       => (int)($map['perfect'] ?? 0),
            'seven_ten'     => (int)($map['seven_ten'] ?? 0),
            'eight_hundred' => (int)($map['eight_hundred'] ?? 0),
        ];
        $counts['total'] = $counts['perfect'] + $counts['seven_ten'] + $counts['eight_hundred'];
        return $counts;
    }

    /**
     * 集計して ProBowler 側のカウンタ列へ同期（列がないなら何もしない）
     * 戻り値は集計結果（ビュー側などでも使える）
     */
    public static function syncToProBowler(int $bowlerId): array
    {
        $counts = self::countsForBowlerId($bowlerId);

        // カウンタ列を用意している時だけ更新（無くてもエラーにならない）
        $hasCols = Schema::hasColumn('pro_bowlers', 'perfect_count')
            && Schema::hasColumn('pro_bowlers', 'seven_ten_count')
            && Schema::hasColumn('pro_bowlers', 'eight_hundred_count')
            && Schema::hasColumn('pro_bowlers', 'award_total_count');

        if ($hasCols) {
            ProBowler::where('id', $bowlerId)->update([
                'perfect_count'       => $counts['perfect'],
                'seven_ten_count'     => $counts['seven_ten'],
                'eight_hundred_count' => $counts['eight_hundred'],
                'award_total_count'   => $counts['total'],
            ]);
        }

        return $counts;
    }
}
