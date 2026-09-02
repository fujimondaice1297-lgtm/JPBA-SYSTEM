<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestScore extends Model
{
    protected $table = 'pro_test_scores_v2';

    protected $fillable = [
        'pro_test_session_id',
        'pro_test_candidate_id',
        'game_number',
        'score',
    ];

    protected $casts = [
        'game_number' => 'integer',
        'score' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(ProTestSession::class, 'pro_test_session_id');
    }

    public function candidate()
    {
        return $this->belongsTo(ProTestCandidate::class, 'pro_test_candidate_id');
    }
}
