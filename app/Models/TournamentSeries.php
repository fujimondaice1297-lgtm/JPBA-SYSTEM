<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentSeries extends Model
{
    protected $table = 'tournament_series';

    protected $fillable = [
        'name',
        'code',
        'recurrence_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function editions()
    {
        return $this->hasMany(TournamentEdition::class);
    }

    public function templates()
    {
        return $this->hasMany(TournamentTemplate::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
