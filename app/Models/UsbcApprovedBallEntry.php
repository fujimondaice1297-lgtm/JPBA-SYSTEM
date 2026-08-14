<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsbcApprovedBallEntry extends Model
{
    protected $fillable = [
        'list_id',
        'brand',
        'name',
        'approved_date_text',
        'approved_on',
        'image_url',
        'normalized_brand',
        'normalized_name',
        'source_fingerprint',
    ];

    protected $casts = [
        'approved_on' => 'date',
    ];

    public function approvedBallList()
    {
        return $this->belongsTo(UsbcApprovedBallList::class, 'list_id');
    }
}
