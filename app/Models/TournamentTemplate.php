<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentTemplate extends Model
{
    protected $fillable = [
        'tournament_series_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function series()
    {
        return $this->belongsTo(TournamentSeries::class, 'tournament_series_id');
    }

    public function versions()
    {
        return $this->hasMany(TournamentTemplateVersion::class);
    }

    public function latestPublishedVersion()
    {
        return $this->hasOne(TournamentTemplateVersion::class)
            ->where('status', 'published')
            ->ofMany('version', 'max');
    }
}
