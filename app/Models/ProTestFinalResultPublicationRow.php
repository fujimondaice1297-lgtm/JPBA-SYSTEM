<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestFinalResultPublicationRow extends Model
{
    protected $fillable = [
        'pro_test_final_result_publication_id',
        'pro_test_candidate_id',
        'gender',
        'exam_number',
        'license_no',
        'name',
        'name_kana',
        'pro_bowler_id',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'F' ? '女子' : '男子';
    }
}
