<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualSchedule extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'year',
        'title',
        'source_updated_on',
        'source_url',
        'notice',
        'status',
        'published_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'source_updated_on' => 'date:Y-m-d',
        'published_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(AnnualScheduleRow::class)
            ->orderBy('month')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
