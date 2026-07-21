<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentTemplateVersion extends Model
{
    protected $fillable = [
        'tournament_template_id',
        'version',
        'status',
        'settings',
        'change_note',
        'published_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'settings' => 'array',
        'published_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(TournamentTemplate::class, 'tournament_template_id');
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
