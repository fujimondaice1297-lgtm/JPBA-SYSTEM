<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BallCatalogImportRun extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'mode',
        'status',
        'started_at',
        'completed_at',
        'page_count',
        'item_count',
        'created_count',
        'updated_count',
        'unchanged_count',
        'image_downloaded_count',
        'image_reused_count',
        'image_failed_count',
        'error_count',
        'cursor_url',
        'report',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'report' => 'array',
    ];

    public function manufacturer()
    {
        return $this->belongsTo(BallManufacturer::class);
    }

    public function failures()
    {
        return $this->hasMany(BallCatalogImportFailure::class, 'import_run_id');
    }
}
