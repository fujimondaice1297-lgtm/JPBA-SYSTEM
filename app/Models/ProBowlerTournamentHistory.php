<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTournamentHistory extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'season_year',
        'held_on',
        'tournament_name',
        'ranking_rank',
        'average',
        'prize_money',
        'source_type',
        'source_url',
        'source_fingerprint',
        'captured_at',
    ];

    protected $casts = [
        'pro_bowler_id' => 'integer',
        'season_year' => 'integer',
        'held_on' => 'date:Y-m-d',
        'ranking_rank' => 'integer',
        'average' => 'decimal:2',
        'prize_money' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
