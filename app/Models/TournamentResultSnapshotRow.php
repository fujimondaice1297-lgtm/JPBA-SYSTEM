<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentResultSnapshotRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_id',
        'ranking',
        'subject_type',
        'competitor_group_id',
        'pro_bowler_id',
        'amateur_bowler_id',
        'pro_bowler_license_no',
        'amateur_name',
        'display_name',
        'gender',
        'shift',
        'entry_number',
        'identity_key',
        'scratch_pin',
        'carry_pin',
        'total_pin',
        'games',
        'source_count',
        'is_complete',
        'breakdown',
        'average',
        'tie_break_value',
        'points',
        'prize_money',
    ];

    protected $casts = [
        'source_count' => 'integer',
        'is_complete' => 'boolean',
        'breakdown' => 'array',
        'average' => 'decimal:3',
        'prize_money' => 'decimal:2',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TournamentResultSnapshot::class, 'snapshot_id');
    }

    public function competitorGroup(): BelongsTo
    {
        return $this->belongsTo(TournamentCompetitorGroup::class, 'competitor_group_id');
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function amateurBowler(): BelongsTo
    {
        return $this->belongsTo(AmateurBowler::class, 'amateur_bowler_id');
    }
}
