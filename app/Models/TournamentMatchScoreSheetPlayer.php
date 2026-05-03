<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatchScoreSheetPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_sheet_id',
        'sort_order',
        'player_slot',
        'pro_bowler_id',
        'pro_bowler_license_no',
        'display_name',
        'name_kana',
        'dominant_arm',
        'lane_label',
        'final_score',
        'is_winner',
        'score_summary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'final_score' => 'integer',
        'is_winner' => 'boolean',
        'score_summary' => 'array',
    ];

    public function scoreSheet(): BelongsTo
    {
        return $this->belongsTo(TournamentMatchScoreSheet::class, 'score_sheet_id');
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(TournamentMatchScoreFrame::class, 'score_sheet_player_id')
            ->orderBy('frame_no');
    }
}
