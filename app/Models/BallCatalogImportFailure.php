<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BallCatalogImportFailure extends Model
{
    protected $fillable = [
        'import_run_id',
        'manufacturer_id',
        'phase',
        'page_url',
        'product_url',
        'image_url',
        'error_message',
        'attempt_count',
        'resolved_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function importRun()
    {
        return $this->belongsTo(BallCatalogImportRun::class, 'import_run_id');
    }
}
