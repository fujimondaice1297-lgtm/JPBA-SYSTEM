<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSessionParticipant extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXEMPT = 'exempt';

    protected $fillable = [
        'training_session_id',
        'pro_bowler_id',
        'attendance_status',
        'notes',
        'pro_bowler_training_id',
        'processed_at',
        'processed_by_user_id',
    ];

    protected $casts = ['processed_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function trainingRecord()
    {
        return $this->belongsTo(ProBowlerTraining::class, 'pro_bowler_training_id');
    }

    public function getAttendanceStatusLabelAttribute(): string
    {
        return match ($this->attendance_status) {
            self::STATUS_ATTENDED => '受講済み',
            self::STATUS_ABSENT => '未受講',
            self::STATUS_EXEMPT => '免除',
            default => '受講予定',
        };
    }
}
