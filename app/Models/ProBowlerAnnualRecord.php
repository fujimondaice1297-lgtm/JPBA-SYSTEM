<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerAnnualRecord extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'season_key',
        'season_start_year',
        'season_end_year',
        'ranking_rank',
        'games',
        'total_pin',
        'points',
        'average',
        'prize_money',
        'source_type',
        'source_url',
        'captured_at',
    ];

    protected $casts = [
        'pro_bowler_id' => 'integer',
        'season_start_year' => 'integer',
        'season_end_year' => 'integer',
        'ranking_rank' => 'integer',
        'games' => 'integer',
        'total_pin' => 'integer',
        'points' => 'decimal:2',
        'average' => 'decimal:2',
        'prize_money' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
