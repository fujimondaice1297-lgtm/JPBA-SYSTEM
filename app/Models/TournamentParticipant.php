<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentParticipant extends Model
{
    protected $table = 'tournament_participants';

    protected $fillable = [
        'tournament_id',
        'pro_bowler_license_no',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_license_no', 'license_no');
    }
}
