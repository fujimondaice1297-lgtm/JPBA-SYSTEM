<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerRankingSnapshot extends Model
{
    protected $table = 'pro_bowler_ranking_snapshots';

    protected $fillable = [
        'ranking_year',
        'gender',
        'ranking_type',
        'ranking_scope',
        'as_of_date',
        'is_final',
        'source_url',
        'notes',
    ];

    protected $casts = [
        'ranking_year' => 'integer',
        'as_of_date' => 'date:Y-m-d',
        'is_final' => 'boolean',
    ];

    public function rows()
    {
        return $this->hasMany(ProBowlerRankingRow::class, 'ranking_snapshot_id')
            ->orderBy('ranking_rank');
    }

    public function seedLists()
    {
        return $this->hasMany(ProBowlerSeedList::class, 'source_ranking_snapshot_id');
    }

    public function seedListPlayers()
    {
        return $this->hasMany(ProBowlerSeedListPlayer::class, 'ranking_snapshot_id');
    }

    public function tournamentSeedPlayers()
    {
        return $this->hasMany(TournamentSeedPlayer::class, 'ranking_snapshot_id');
    }

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function scopeGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeYear($query, int $year)
    {
        return $query->where('ranking_year', $year);
    }
}