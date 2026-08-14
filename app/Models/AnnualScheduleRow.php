<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualScheduleRow extends Model
{
    protected $fillable = [
        'annual_schedule_id',
        'tournament_id',
        'month',
        'sort_order',
        'start_date',
        'end_date',
        'date_label',
        'title',
        'eligibility',
        'region',
        'venue',
        'point_mark',
        'average_mark',
        'prize_mark',
        'title_mark',
        'note',
        'row_type',
        'source_type',
    ];

    protected $casts = [
        'month' => 'integer',
        'sort_order' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function schedule()
    {
        return $this->belongsTo(AnnualSchedule::class, 'annual_schedule_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
