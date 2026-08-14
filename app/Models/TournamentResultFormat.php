<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentResultFormat extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(TournamentResultFormatVersion::class)
            ->orderByDesc('version_no');
    }
}
