<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerRankingRow extends Model
{
    protected $table = 'pro_bowler_ranking_rows';

    protected $fillable = [
        'ranking_snapshot_id',
        'ranking_rank',
        'pro_bowler_id',
        'license_no',
        'name_kanji',
        'name_kana',
        'kibetsu',
        'organization_name',
        'equipment_contract',
        'points',
        'games',
        'total_pin',
        'average',
        'prize_money',
        'sort_order',
    ];

    protected $casts = [
        'ranking_snapshot_id' => 'integer',
        'ranking_rank' => 'integer',
        'pro_bowler_id' => 'integer',
        'kibetsu' => 'integer',
        'points' => 'decimal:2',
        'games' => 'integer',
        'total_pin' => 'integer',
        'average' => 'decimal:2',
        'prize_money' => 'integer',
        'sort_order' => 'integer',
    ];

    public function snapshot()
    {
        return $this->belongsTo(ProBowlerRankingSnapshot::class, 'ranking_snapshot_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function scopeTop($query, int $count = 24)
    {
        return $query->where('ranking_rank', '<=', $count)
            ->orderBy('ranking_rank');
    }
}