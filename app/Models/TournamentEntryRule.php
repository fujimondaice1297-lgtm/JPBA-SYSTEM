<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntryRule extends Model
{
    public const PAST_CHAMPIONS = 'past_champions';

    public const CURRENT_YEAR_WINNERS = 'current_year_winners';

    public const PERMANENT_SEEDS = 'permanent_seeds';

    public const SOURCE_TOURNAMENT_TOP_N = 'source_tournament_top_n';

    protected $fillable = [
        'tournament_id',
        'rule_type',
        'priority_order',
        'max_count',
        'source_tournament_id',
        'source_series_id',
        'parameters',
        'auto_sync',
        'is_active',
    ];

    protected $casts = [
        'priority_order' => 'integer',
        'max_count' => 'integer',
        'parameters' => 'array',
        'auto_sync' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceTournament()
    {
        return $this->belongsTo(Tournament::class, 'source_tournament_id');
    }

    public function sourceSeries()
    {
        return $this->belongsTo(TournamentSeries::class, 'source_series_id');
    }
}
