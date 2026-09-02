<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestResultPublicationRow extends Model
{
    protected $fillable = [
        'pro_test_result_publication_id',
        'pro_test_candidate_id',
        'rank',
        'exam_number',
        'name',
        'name_kana',
        'resident_prefecture',
        'handedness',
        'games',
        'total_pin',
        'average',
        'result_label',
        'session_scores',
    ];

    protected $casts = [
        'rank' => 'integer',
        'games' => 'integer',
        'total_pin' => 'integer',
        'average' => 'decimal:2',
        'session_scores' => 'array',
    ];

    public function publication()
    {
        return $this->belongsTo(ProTestResultPublication::class, 'pro_test_result_publication_id');
    }
}
