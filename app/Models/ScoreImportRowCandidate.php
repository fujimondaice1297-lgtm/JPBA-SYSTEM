<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreImportRowCandidate extends Model
{
    protected $fillable = [
        'score_import_row_id',
        'candidate_type',
        'candidate_value',
        'tournament_participant_id',
        'pro_bowler_id',
        'confidence',
        'rank',
        'payload',
        'is_selected',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'rank' => 'integer',
        'payload' => 'array',
        'is_selected' => 'boolean',
    ];

    public function row()
    {
        return $this->belongsTo(ScoreImportRow::class, 'score_import_row_id');
    }

    public function participant()
    {
        return $this->belongsTo(TournamentParticipant::class, 'tournament_participant_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }
}
