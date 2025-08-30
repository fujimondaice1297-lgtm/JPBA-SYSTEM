<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentPoint extends Model
{
    protected $table = 'tournament_points';

    protected $fillable = [
        'tournament_id', // 追加しろよって言ってる
        'rank',
        'point',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}

