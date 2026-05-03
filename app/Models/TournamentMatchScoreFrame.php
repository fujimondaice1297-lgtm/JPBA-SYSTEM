<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatchScoreFrame extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_sheet_player_id',
        'frame_no',
        'throw1',
        'throw2',
        'throw3',
        'frame_score',
        'cumulative_score',
        'display_marks',
    ];

    protected $casts = [
        'frame_no' => 'integer',
        'frame_score' => 'integer',
        'cumulative_score' => 'integer',
        'display_marks' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(TournamentMatchScoreSheetPlayer::class, 'score_sheet_player_id');
    }
}
