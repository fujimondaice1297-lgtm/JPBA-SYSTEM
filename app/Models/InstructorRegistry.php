<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorRegistry extends Model
{
    protected $table = 'instructor_registry';

    protected $fillable = [
        'source_type',
        'source_key',
        'legacy_instructor_license_no',
        'pro_bowler_id',
        'license_no',
        'cert_no',
        'name',
        'name_kana',
        'sex',
        'district_id',
        'instructor_category',
        'grade',
        'coach_qualification',
        'source_registered_at',
        'is_current',
        'superseded_at',
        'supersede_reason',
        'is_active',
        'is_visible',
        'renewal_year',
        'renewal_due_on',
        'renewal_status',
        'renewed_at',
        'renewal_note',
        'last_synced_at',
        'notes',
    ];

    protected $casts = [
        'sex'                  => 'boolean',
        'coach_qualification'  => 'boolean',
        'source_registered_at' => 'datetime',
        'is_current'           => 'boolean',
        'superseded_at'        => 'datetime',
        'is_active'            => 'boolean',
        'is_visible'           => 'boolean',
        'renewal_year'         => 'integer',
        'renewal_due_on'       => 'date',
        'renewed_at'           => 'date',
        'last_synced_at'       => 'datetime',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function proBowler(): BelongsTo
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function getTypeLabelAttribute(): string
    {
        if ($this->isRetiredStatus()) {
            return '退会済み';
        }

        return match ($this->instructor_category) {
            'pro_bowler'     => 'プロボウラー',
            'pro_instructor' => 'プロインストラクター',
            'certified'      => '認定インストラクター',
            default          => '不明',
        };
    }

    public function getRenewalStatusLabelAttribute(): string
    {
        return match ($this->renewal_status) {
            'pending' => '未更新',
            'renewed' => '更新済み',
            'expired' => '期限切れ',
            default   => '-',
        };
    }

    public function isRetiredStatus(): bool
    {
        return !$this->is_current
            && in_array($this->supersede_reason, [
                'certified_not_renewed',
                'inactive_in_source',
                'qualification_removed',
            ], true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeProBowler($query)
    {
        return $query->where('instructor_category', 'pro_bowler');
    }

    public function scopeProInstructor($query)
    {
        return $query->where('instructor_category', 'pro_instructor');
    }

    public function scopeCertifiedInstructor($query)
    {
        return $query->where('instructor_category', 'certified');
    }

    public function scopeRetired($query)
    {
        return $query
            ->where('is_current', false)
            ->whereIn('supersede_reason', [
                'certified_not_renewed',
                'inactive_in_source',
                'qualification_removed',
            ]);
    }
}
