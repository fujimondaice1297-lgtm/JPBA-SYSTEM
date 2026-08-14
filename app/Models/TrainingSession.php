<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    public const STATUS_PLANNING = 'planning';
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'training_id',
        'session_year',
        'name',
        'held_on',
        'venue',
        'status',
        'notes',
        'finalized_at',
        'finalized_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'session_year' => 'integer',
        'held_on' => 'date',
        'finalized_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function participants()
    {
        return $this->hasMany(TrainingSessionParticipant::class)
            ->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => '受講者受付中',
            self::STATUS_COMPLETED => '受講結果確定済み',
            default => '準備中',
        };
    }
}
