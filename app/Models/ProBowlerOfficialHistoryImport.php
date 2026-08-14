<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerOfficialHistoryImport extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'annual_row_count',
        'participation_year_count',
        'tournament_row_count',
        'source_url',
        'completed_at',
    ];

    protected $casts = [
        'pro_bowler_id' => 'integer',
        'annual_row_count' => 'integer',
        'participation_year_count' => 'integer',
        'tournament_row_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
