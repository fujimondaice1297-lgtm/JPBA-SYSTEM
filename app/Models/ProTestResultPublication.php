<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestResultPublication extends Model
{
    protected $fillable = [
        'pro_test_session_id',
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

    public function session()
    {
        return $this->belongsTo(ProTestSession::class, 'pro_test_session_id');
    }

    public function rows()
    {
        return $this->hasMany(ProTestResultPublicationRow::class)
            ->orderBy('rank')
            ->orderBy('exam_number');
    }
}
