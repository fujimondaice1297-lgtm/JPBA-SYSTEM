<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentAggregateSource extends Model
{
    protected $fillable = [
        'aggregate_definition_id',
        'source_tournament_id',
        'label',
        'stage',
        'game_from',
        'game_to',
        'expected_games_per_member',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'game_from' => 'integer',
        'game_to' => 'integer',
        'expected_games_per_member' => 'integer',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function definition()
    {
        return $this->belongsTo(TournamentAggregateDefinition::class, 'aggregate_definition_id');
    }

    public function sourceTournament()
    {
        return $this->belongsTo(Tournament::class, 'source_tournament_id');
    }
}
