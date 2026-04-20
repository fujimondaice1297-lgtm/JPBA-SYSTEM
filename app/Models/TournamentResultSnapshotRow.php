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
        'pro_bowler_id',
        'pro_bowler_license_no',
        'amateur_name',
        'display_name',
        'gender',
        'shift',
        'entry_number',
        'scratch_pin',
        'carry_pin',
        'total_pin',
        'games',
        'average',
        'tie_break_value',
        'points',
        'prize_money',
    ];

    protected $casts = [
        'average' => 'decimal:3',
        'prize_money' => 'decimal:2',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TournamentResultSnapshot::class, 'snapshot_id');
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }
}
