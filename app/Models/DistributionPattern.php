<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionPattern extends Model
{
    protected $fillable = ['name']; // カラムに応じて変える

    public function pointDistributions()
    {
        return $this->hasMany(PointDistribution::class, 'pattern_id');
    }

    public function prizeDistributions()
    {
        return $this->hasMany(PrizeDistribution::class, 'pattern_id');
    }


}

