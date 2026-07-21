<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEdition extends Model
{
    protected $fillable = [
        'tournament_series_id',
        'year',
        'season_key',
        'name',
        'edition_no',
        'status',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'edition_no' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function series()
    {
        return $this->belongsTo(TournamentSeries::class, 'tournament_series_id');
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
