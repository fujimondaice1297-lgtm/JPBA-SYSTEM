<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordCertificationSequence extends Model
{
    protected $fillable = [
        'record_type',
        'gender',
        'next_number',
        'prefix',
        'suffix',
        'is_enabled',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
