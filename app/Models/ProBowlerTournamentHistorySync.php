<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTournamentHistorySync extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'season_year',
        'row_count',
        'source_url',
        'captured_at',
    ];

    protected $casts = [
        'pro_bowler_id' => 'integer',
        'season_year' => 'integer',
        'row_count' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
