<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $table = 'venues';

    protected $fillable = [
        'name',
        'canonical_key',
        'aliases',
        'address',
        'postal_code',
        'city',
        'prefecture',
        'tel',
        'fax',
        'website_url',
        'note',
        'is_active',
        'source_url',
        'source_checked_at',
        'first_hosted_year',
        'last_hosted_year',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_active' => 'boolean',
        'source_checked_at' => 'date',
        'first_hosted_year' => 'integer',
        'last_hosted_year' => 'integer',
    ];

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
