<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentResultSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'result_code',
        'result_name',
        'result_type',
        'stage_name',
        'gender',
        'shift',
        'games_count',
        'carry_game_count',
        'carry_stage_names',
        'calculation_definition',
        'reflected_at',
        'reflected_by',
        'is_final',
        'is_published',
        'is_current',
        'notes',
    ];

    protected $casts = [
        'carry_stage_names' => 'array',
        'calculation_definition' => 'array',
        'reflected_at' => 'datetime',
        'is_final' => 'boolean',
        'is_published' => 'boolean',
        'is_current' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function reflectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reflected_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TournamentResultSnapshotRow::class, 'snapshot_id');
    }
}
