<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = ['code','name','valid_for_months','mandatory'];

    protected $casts = [
        'valid_for_months' => 'integer',
        'mandatory' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(TrainingSession::class);
    }
}
