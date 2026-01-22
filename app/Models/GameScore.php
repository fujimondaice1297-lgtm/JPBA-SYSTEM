<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameScore extends Model
{
    protected $fillable = [
        'tournament_id', 'stage', 'shift', 'gender',
        'license_number', 'name', 'entry_number',
        'game_number', 'score'
    ];
}
