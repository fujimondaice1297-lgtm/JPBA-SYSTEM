<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntry extends Model
{
    protected $fillable = [
        'tournament_id',
        'pro_bowler_id',
        'status',
        'is_paid',
        'shift_drawn',
        'lane_drawn',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function balls()
    {
        return $this->belongsToMany(
            \App\Models\UsedBall::class,
            'tournament_entry_balls',
            'tournament_entry_id',
            'used_ball_id'
        )->withTimestamps();
    }
}
