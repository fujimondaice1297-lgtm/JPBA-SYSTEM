<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerSeedListPlayer extends Model
{
    protected $table = 'pro_bowler_seed_list_players';

    protected $fillable = [
        'seed_list_id',
        'pro_bowler_id',
        'license_no',
        'seed_category',
        'seed_rank',
        'ranking_snapshot_id',
        'ranking_rank',
        'source_tournament_id',
        'pro_bowler_title_id',
        'priority_order',
        'note',
        'is_active',
    ];

    protected $casts = [
        'seed_list_id' => 'integer',
        'pro_bowler_id' => 'integer',
        'seed_rank' => 'integer',
        'ranking_snapshot_id' => 'integer',
        'ranking_rank' => 'integer',
        'source_tournament_id' => 'integer',
        'pro_bowler_title_id' => 'integer',
        'priority_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function seedList()
    {
        return $this->belongsTo(ProBowlerSeedList::class, 'seed_list_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
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

    public function tournamentSeedPlayers()
    {
        return $this->hasMany(TournamentSeedPlayer::class, 'seed_list_player_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('seed_category', $category);
    }
}