<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreSeriesDefinition extends Model
{
    protected $fillable = [
        'tournament_id',
        'stage',
        'shift',
        'gender',
        'label',
        'start_game',
        'end_game',
        'is_800_eligible',
        'is_enabled',
        'source',
    ];

    protected $casts = [
        'start_game' => 'integer',
        'end_game' => 'integer',
        'is_800_eligible' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function records()
    {
        return $this->hasMany(RecordType::class);
    }

    public function getGameCountAttribute(): int
    {
        return max(0, (int) $this->end_game - (int) $this->start_game + 1);
    }
}
