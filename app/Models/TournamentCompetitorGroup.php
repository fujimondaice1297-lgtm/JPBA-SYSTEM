<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentCompetitorGroup extends Model
{
    protected $fillable = [
        'tournament_id',
        'group_type',
        'code',
        'name',
        'division',
        'expected_member_count',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'expected_member_count' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function members()
    {
        return $this->hasMany(TournamentCompetitorGroupMember::class, 'competitor_group_id')
            ->orderBy('member_order')
            ->orderBy('id');
    }
}
