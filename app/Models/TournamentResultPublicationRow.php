<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentResultPublicationRow extends Model
{
    protected $fillable = [
        'publication_id',
        'source_snapshot_id',
        'source_snapshot_row_id',
        'source_result_code',
        'ranking',
        'pro_bowler_id',
        'amateur_bowler_id',
        'pro_bowler_license_no',
        'amateur_name',
        'display_name',
        'identity_key',
        'gender',
        'entry_number',
        'total_pin',
        'games',
        'average',
        'points',
        'award_points',
        'step_points',
        'prize_money',
        'affiliation_display',
        'breakdown',
    ];

    protected $casts = [
        'average' => 'decimal:3',
        'breakdown' => 'array',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(TournamentResultPublication::class, 'publication_id');
    }

    public function sourceSnapshot(): BelongsTo
    {
        return $this->belongsTo(TournamentResultSnapshot::class, 'source_snapshot_id');
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class);
    }
}
