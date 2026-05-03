<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatchScoreSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'sheet_type',
        'stage_code',
        'match_code',
        'match_label',
        'match_order',
        'game_number',
        'lane_label',
        'is_published',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'confirmed_at' => 'datetime',
        'match_order' => 'integer',
        'game_number' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentMatchScoreSheetPlayer::class, 'score_sheet_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
