<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreImportRow extends Model
{
    protected $fillable = [
        'score_import_batch_id',
        'row_number',
        'raw_payload',
        'parse_status',
        'confidence',
        'tournament_participant_id',
        'pro_bowler_id',
        'license_number',
        'name',
        'entry_number',
        'stage',
        'shift',
        'gender',
        'game_number',
        'score',
        'error_message',
        'reviewed_by',
        'reviewed_at',
        'confirmed_game_score_id',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'raw_payload' => 'array',
        'confidence' => 'decimal:2',
        'game_number' => 'integer',
        'score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ScoreImportBatch::class, 'score_import_batch_id');
    }

    public function participant()
    {
        return $this->belongsTo(TournamentParticipant::class, 'tournament_participant_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function confirmedGameScore()
    {
        return $this->belongsTo(GameScore::class, 'confirmed_game_score_id');
    }

    public function candidates()
    {
        return $this->hasMany(ScoreImportRowCandidate::class);
    }
}
