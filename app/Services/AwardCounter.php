<?php

namespace App\Services;

use App\Models\RecordType;
use App\Models\ProBowler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AwardCounter
{
    /** 指定選手の確認済み明細数を集計して返す。 */
    public static function countsForBowlerId(int $bowlerId): array
    {
        $query = RecordType::where('pro_bowler_id', $bowlerId);
        if (Schema::hasColumn('record_types', 'status')) {
            $query->where('status', RecordType::STATUS_CONFIRMED);
        }

        $map = $query
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
     * 新JPBAシステムの公認総数と、確認済み・その他過去達成数を返す。
     */
    public static function summaryForBowlerId(int $bowlerId): array
    {
        $confirmed = self::countsForBowlerId($bowlerId);
        $bowler = ProBowler::query()->find($bowlerId);

        $authoritative = [
            'perfect' => (int) ($bowler?->perfect_count ?? 0),
            'seven_ten' => (int) ($bowler?->seven_ten_count ?? 0),
            'eight_hundred' => (int) ($bowler?->eight_hundred_count ?? 0),
        ];

        $total = [];
        $other = [];
        foreach (['perfect', 'seven_ten', 'eight_hundred'] as $type) {
            $total[$type] = max($authoritative[$type], $confirmed[$type]);
            $other[$type] = max(0, $total[$type] - $confirmed[$type]);
        }

        $total['total'] = array_sum($total);
        $confirmed['total'] = $confirmed['perfect']
            + $confirmed['seven_ten']
            + $confirmed['eight_hundred'];
        $other['total'] = $other['perfect']
            + $other['seven_ten']
            + $other['eight_hundred'];

        return compact('total', 'confirmed', 'other');
    }

    /**
     * 確認済み明細が公認総数を上回った場合だけ総数を引き上げる。
     * 明細が少なくても、移行済みの公認総数は絶対に減らさない。
     */
    public static function syncToProBowler(int $bowlerId): array
    {
        $confirmed = self::countsForBowlerId($bowlerId);
        $hasCols = Schema::hasColumn('pro_bowlers', 'perfect_count')
            && Schema::hasColumn('pro_bowlers', 'seven_ten_count')
            && Schema::hasColumn('pro_bowlers', 'eight_hundred_count')
            && Schema::hasColumn('pro_bowlers', 'award_total_count');

        if ($hasCols) {
            DB::transaction(function () use ($bowlerId, $confirmed): void {
                $bowler = ProBowler::query()->lockForUpdate()->find($bowlerId);
                if (! $bowler) {
                    return;
                }

                $bowler->perfect_count = max(
                    (int) $bowler->perfect_count,
                    $confirmed['perfect']
                );
                $bowler->seven_ten_count = max(
                    (int) $bowler->seven_ten_count,
                    $confirmed['seven_ten']
                );
                $bowler->eight_hundred_count = max(
                    (int) $bowler->eight_hundred_count,
                    $confirmed['eight_hundred']
                );
                $bowler->award_total_count = (int) $bowler->perfect_count
                    + (int) $bowler->seven_ten_count
                    + (int) $bowler->eight_hundred_count;
                $bowler->save();
            });
        }

        return self::summaryForBowlerId($bowlerId);
    }
}
