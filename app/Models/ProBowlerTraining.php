<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTraining extends Model
{
    protected $table = 'pro_bowler_trainings';

    protected $fillable = [
        'pro_bowler_id', 'training_id', 'training_session_id', 'completed_at', 'expires_at',
        'record_status', 'revoked_at', 'proof_path', 'notes', 'recorded_by_user_id',
    ];

    protected $casts = [
        'completed_at' => 'date',
        'expires_at'   => 'date',
        'revoked_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }
}
