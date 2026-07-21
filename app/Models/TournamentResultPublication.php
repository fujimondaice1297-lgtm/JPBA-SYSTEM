<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentResultPublication extends Model
{
    public const STATUS_CURRENT = 'current';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'tournament_id',
        'snapshot_id',
        'revision',
        'status',
        'row_count',
        'pro_count',
        'amateur_count',
        'total_points',
        'total_prize_money',
        'result_checksum',
        'distribution_checksum',
        'source_snapshot_ids',
        'validation_summary',
        'title_sync_summary',
        'published_at',
        'published_by',
        'superseded_at',
        'notes',
    ];

    protected $casts = [
        'source_snapshot_ids' => 'array',
        'validation_summary' => 'array',
        'title_sync_summary' => 'array',
        'published_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TournamentResultSnapshot::class, 'snapshot_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TournamentResultPublicationRow::class, 'publication_id');
    }
}
