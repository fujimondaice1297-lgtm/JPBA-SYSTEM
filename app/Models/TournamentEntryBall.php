<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntryBall extends Model
{
    protected $table = 'tournament_entry_balls';
    protected $fillable = ['tournament_entry_id','used_ball_id'];
    public $timestamps = true;

    public function entry()
    {
        return $this->belongsTo(TournamentEntry::class, 'tournament_entry_id');
    }

    public function usedBall()
    {
        return $this->belongsTo(UsedBall::class, 'used_ball_id');
    }
}
