<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instructor extends Model
{
    protected $table = 'instructors';
    protected $primaryKey = 'license_no';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'license_no',
        'name',
        'name_kana',
        'sex',
        'district_id',
        'instructor_type',
        'grade',
        'is_active',
        'is_visible',
        'coach_qualification',
        'pro_bowler_id',
    ];

    protected $casts = [
        'sex' => 'boolean',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'coach_qualification' => 'boolean',
    ];

    public function getTypeLabelAttribute()
    {
        if ($this->instructor_type === 'certified') {
            return '認定イントラ';
        }

        if ($this->instructor_type === 'pro') {
            return $this->pro_bowler_id ? 'プロボウラー' : 'プロイントラ';
        }

        return '不明';
    }

    public function scopeProBowler($query)
    {
        return $query->where('instructor_type', 'pro')->whereNotNull('pro_bowler_id');
    }

    public function scopeProInstructor($query)
    {
        return $query->where('instructor_type', 'pro')->whereNull('pro_bowler_id');
    }

    public function scopeCertifiedInstructor($query)
    {
        return $query->where('instructor_type', 'certified');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class);
    }
}
