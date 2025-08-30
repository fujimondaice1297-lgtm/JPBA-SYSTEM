<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerProfile extends Model
{
    protected $table = 'pro_bowler_profiles';

    protected $fillable = [
        'license_no',
        'name_kanji',
        'name_kana',
        'sex',
        'district_id',
        'acquire_date',
        'is_active',
        'is_visible',
        'coach_qualification',
    ];

    protected $casts = [
        'acquire_date' => 'date',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public $timestamps = false;
}
