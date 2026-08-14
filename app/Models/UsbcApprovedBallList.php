<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsbcApprovedBallList extends Model
{
    protected $fillable = [
        'official_updated_on',
        'source_page_url',
        'source_pdf_url',
        'source_api_url',
        'source_sha256',
        'status',
        'fetched_at',
        'completed_at',
        'brand_count',
        'entry_count',
        'matched_catalog_count',
        'ambiguous_catalog_count',
        'unlisted_catalog_count',
        'report',
    ];

    protected $casts = [
        'official_updated_on' => 'date',
        'fetched_at' => 'datetime',
        'completed_at' => 'datetime',
        'report' => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(UsbcApprovedBallEntry::class, 'list_id');
    }
}
