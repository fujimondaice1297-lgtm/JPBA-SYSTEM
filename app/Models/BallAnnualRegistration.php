<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BallAnnualRegistration extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'pro_bowler_id',
        'registration_year',
        'revision',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'returned_at',
        'returned_by_user_id',
        'return_reason',
    ];

    protected $casts = [
        'registration_year' => 'integer',
        'revision' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function usedBalls()
    {
        return $this->belongsToMany(
            UsedBall::class,
            'ball_annual_registration_items',
            'registration_id',
            'used_ball_id'
        )->withTimestamps();
    }

    public function histories()
    {
        return $this->hasMany(BallAnnualRegistrationHistory::class, 'registration_id')
            ->latest('id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '下書き',
            self::STATUS_SUBMITTED => '承認待ち',
            self::STATUS_APPROVED => '承認済み',
            self::STATUS_RETURNED => '差戻し',
            self::STATUS_SUPERSEDED => '更新済み',
            default => (string) $this->status,
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_SUBMITTED => 'warning text-dark',
            self::STATUS_APPROVED => 'success',
            self::STATUS_RETURNED => 'danger',
            self::STATUS_SUPERSEDED => 'light text-dark',
            default => 'secondary',
        };
    }
}
