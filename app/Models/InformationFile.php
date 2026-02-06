<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformationFile extends Model
{
    protected $table = 'information_files';

    protected $fillable = [
        'information_id',
        'type',
        'title',
        'file_path',
        'visibility',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public function information(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Information::class, 'information_id');
    }
}
