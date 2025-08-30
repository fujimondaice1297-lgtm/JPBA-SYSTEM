<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTitle extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'tournament_id',
        'title_name',   // ← 大会名のスナップショット用に使う
        'year',
        'won_date',
        'source',
    ];

    protected $casts = [
        'won_date' => 'date',
    ];

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
