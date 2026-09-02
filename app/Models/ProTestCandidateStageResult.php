<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestCandidateStageResult extends Model
{
    protected $fillable = [
        'pro_test_candidate_id',
        'stage_code',
        'result',
        'note',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(ProTestCandidate::class, 'pro_test_candidate_id');
    }
}
