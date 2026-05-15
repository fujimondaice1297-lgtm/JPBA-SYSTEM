<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentSeedPlayer extends Model
{
    protected $table = 'tournament_seed_players';

    protected $fillable = [
        'tournament_id',
        'pro_bowler_id',
        'license_no',
        'seed_source_type',
        'seed_list_player_id',
        'ranking_snapshot_id',
        'ranking_rank',
        'source_tournament_id',
        'pro_bowler_title_id',
        'priority_order',
        'display_label',
        'note',
        'is_active',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'pro_bowler_id' => 'integer',
        'seed_list_player_id' => 'integer',
        'ranking_snapshot_id' => 'integer',
        'ranking_rank' => 'integer',
        'source_tournament_id' => 'integer',
        'pro_bowler_title_id' => 'integer',
        'priority_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function seedListPlayer()
    {
        return $this->belongsTo(ProBowlerSeedListPlayer::class, 'seed_list_player_id');
    }

    public function rankingSnapshot()
    {
        return $this->belongsTo(ProBowlerRankingSnapshot::class, 'ranking_snapshot_id');
    }

    public function sourceTournament()
    {
        return $this->belongsTo(Tournament::class, 'source_tournament_id');
    }

    public function title()
    {
        return $this->belongsTo(ProBowlerTitle::class, 'pro_bowler_title_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTournament($query, int $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeSourceType($query, string $sourceType)
    {
        return $query->where('seed_source_type', $sourceType);
    }
}