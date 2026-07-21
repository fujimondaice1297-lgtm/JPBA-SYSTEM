<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentCompetitorGroupMember extends Model
{
    protected $fillable = [
        'competitor_group_id',
        'tournament_participant_id',
        'member_order',
    ];

    protected $casts = [
        'member_order' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(TournamentCompetitorGroup::class, 'competitor_group_id');
    }

    public function participant()
    {
        return $this->belongsTo(TournamentParticipant::class, 'tournament_participant_id');
    }
}
