<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestCandidate extends Model
{
    protected $fillable = [
        'pro_test_event_id',
        'exam_number',
        'gender',
        'name',
        'name_kana',
        'resident_prefecture',
        'handedness',
        'entry_stage',
        'entry_reason',
        'previous_candidate_id',
        'exemption_approved_at',
        'exemption_approved_by',
        'exemption_note',
        'final_result',
        'license_no',
        'pro_bowler_id',
    ];

    protected $casts = [
        'exemption_approved_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(ProTestEvent::class, 'pro_test_event_id');
    }

    public function scores()
    {
        return $this->hasMany(ProTestScore::class);
    }

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function previousCandidate()
    {
        return $this->belongsTo(self::class, 'previous_candidate_id');
    }

    public function nextYearCandidate()
    {
        return $this->hasOne(self::class, 'previous_candidate_id');
    }

    public function stageResults()
    {
        return $this->hasMany(ProTestCandidateStageResult::class);
    }

    public function getEntryStageLabelAttribute(): string
    {
        return match ($this->entry_stage) {
            'second' => '第2次から',
            'third' => '第3次から',
            default => '第1次から',
        };
    }

    public function getEntryReasonLabelAttribute(): string
    {
        return match ($this->entry_reason) {
            'previous_year_second_fail' => '前年第2次不合格による第1次免除',
            'approved_amateur_performance' => 'アマチュア好成績・協会承認',
            'amateur_pro_event_champion' => 'プロ公式戦アマチュア優勝・実技免除',
            'other' => 'その他特例',
            default => '通常受験',
        };
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'F' ? '女子' : '男子';
    }
}
