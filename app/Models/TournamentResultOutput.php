<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentResultOutput extends Model
{
    protected $fillable = [
        'tournament_id',
        'output_type',
        'output_scope',
        'distribution_pattern_id',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
