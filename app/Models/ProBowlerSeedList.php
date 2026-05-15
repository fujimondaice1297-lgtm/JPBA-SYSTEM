<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerSeedList extends Model
{
    protected $table = 'pro_bowler_seed_lists';

    protected $fillable = [
        'seed_year',
        'gender',
        'seed_list_type',
        'source_ranking_snapshot_id',
        'base_ranking_year',
        'base_top_count',
        'as_of_date',
        'is_active',
        'source_url',
        'notes',
    ];

    protected $casts = [
        'seed_year' => 'integer',
        'source_ranking_snapshot_id' => 'integer',
        'base_ranking_year' => 'integer',
        'base_top_count' => 'integer',
        'as_of_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function sourceRankingSnapshot()
    {
        return $this->belongsTo(ProBowlerRankingSnapshot::class, 'source_ranking_snapshot_id');
    }

    public function players()
    {
        return $this->hasMany(ProBowlerSeedListPlayer::class, 'seed_list_id')
            ->orderBy('priority_order')
            ->orderBy('seed_rank')
            ->orderBy('id');
    }

    public function activePlayers()
    {
        return $this->hasMany(ProBowlerSeedListPlayer::class, 'seed_list_id')
            ->where('is_active', true)
            ->orderBy('priority_order')
            ->orderBy('seed_rank')
            ->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeYear($query, int $year)
    {
        return $query->where('seed_year', $year);
    }
}