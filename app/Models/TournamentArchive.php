<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentArchive extends Model
{
    protected $fillable = [
        'tournament_id',
        'classification',
        'year',
        'title',
        'start_on',
        'end_on',
        'venue_name',
        'organizer_name',
        'approval_number',
        'status',
        'body_html',
        'assets',
        'source_key',
        'source_url',
        'source_fingerprint',
        'source_synced_at',
        'is_public',
    ];

    protected $casts = [
        'year' => 'integer',
        'start_on' => 'date',
        'end_on' => 'date',
        'assets' => 'array',
        'source_synced_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function getClassificationLabelAttribute(): string
    {
        return match ($this->classification) {
            'approved_event' => '承認イベント',
            default => '公認トーナメント',
        };
    }
}
