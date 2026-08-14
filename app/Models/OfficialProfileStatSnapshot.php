<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialProfileStatSnapshot extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'license_no',
        'source_url',
        'captured_at',
        'perfect_count',
        'eight_hundred_count',
        'seven_ten_count',
        'payload',
        'payload_hash',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'perfect_count' => 'integer',
        'eight_hundred_count' => 'integer',
        'seven_ten_count' => 'integer',
        'payload' => 'array',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
