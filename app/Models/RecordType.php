<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordType extends Model
{
    protected $fillable = [
        'record_type',
        'pro_bowler_id',
        'tournament_name',
        'game_numbers',
        'frame_number',
        'awarded_on',
        'certification_number',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
