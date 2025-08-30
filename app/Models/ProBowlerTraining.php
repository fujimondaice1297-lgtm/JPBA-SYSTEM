<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTraining extends Model
{
    protected $table = 'pro_bowler_trainings';

    protected $fillable = [
        'pro_bowler_id', 'training_id', 'completed_at', 'expires_at',
        'proof_path', 'notes',
    ];

    protected $casts = [
        'completed_at' => 'date',
        'expires_at'   => 'date',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }
}
