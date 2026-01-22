<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageSetting extends Model
{
    protected $fillable = ['tournament_id', 'stage', 'total_games', 'enabled'];
}
