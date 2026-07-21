<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentAggregateDefinition extends Model
{
    protected $fillable = [
        'tournament_id',
        'code',
        'name',
        'subject_type',
        'tie_break_policy',
        'gender',
        'require_all_sources',
        'is_published',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'require_all_sources' => 'boolean',
        'is_published' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sources()
    {
        return $this->hasMany(TournamentAggregateSource::class, 'aggregate_definition_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function snapshots()
    {
        return $this->hasMany(TournamentResultSnapshot::class, 'aggregate_definition_id');
    }
}
