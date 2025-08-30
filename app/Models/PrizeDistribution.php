<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeDistribution extends Model
{
    protected $fillable = ['tournament_id', 'rank', 'amount', 'pattern_id'];
    

}
