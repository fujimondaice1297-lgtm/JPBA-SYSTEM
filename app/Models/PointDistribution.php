<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointDistribution extends Model
{
    protected $fillable = ['tournament_id', 'rank', 'points', 'pattern_id'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function pattern()
    {
        return $this->belongsTo(DistributionPattern::class);
    }
}
