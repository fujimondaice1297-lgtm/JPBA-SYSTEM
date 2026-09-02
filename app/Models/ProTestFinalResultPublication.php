<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestFinalResultPublication extends Model
{
    protected $fillable = [
        'pro_test_event_id',
        'revision',
        'row_count',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'revision' => 'integer',
        'row_count' => 'integer',
        'published_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(ProTestEvent::class, 'pro_test_event_id');
    }

    public function rows()
    {
        return $this->hasMany(ProTestFinalResultPublicationRow::class)
            ->orderBy('gender')
            ->orderBy('license_no')
            ->orderBy('exam_number');
    }
}
