<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarDay extends Model
{
    protected $primaryKey = 'date';
    public $incrementing = false;
    protected $keyType = 'date';

    protected $fillable = ['date','holiday_name','is_holiday','rokuyou'];

    protected $casts = [
        'date' => 'date',
        'is_holiday' => 'boolean',
    ];
}
