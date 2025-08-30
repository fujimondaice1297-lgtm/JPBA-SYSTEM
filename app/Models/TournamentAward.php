<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentAward extends Model
{
    protected $table = 'tournament_awards';

    protected $fillable = [
        'tournament_id',
        'rank',
        'prize_money',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
