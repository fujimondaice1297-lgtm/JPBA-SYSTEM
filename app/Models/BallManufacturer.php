<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BallManufacturer extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'catalog_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function approvedBalls()
    {
        return $this->hasMany(ApprovedBall::class, 'manufacturer_id');
    }

    public function importRuns()
    {
        return $this->hasMany(BallCatalogImportRun::class, 'manufacturer_id');
    }
}
