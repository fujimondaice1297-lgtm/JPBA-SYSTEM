<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordType extends Model
{
    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_VOID = 'void';

    public const MODE_HISTORICAL = 'historical_backfill';
    public const MODE_NEW = 'new_achievement';

    protected $fillable = [
        'record_type',
        'pro_bowler_id',
        'tournament_id',
        'source_game_score_id',
        'score_series_definition_id',
        'tournament_name',
        'game_numbers',
        'frame_number',
        'awarded_on',
        'certification_number',
        'certification_number_value',
        'stage',
        'shift',
        'gender',
        'series_label',
        'series_start_game',
        'series_end_game',
        'series_total',
        'series_scores',
        'status',
        'registration_mode',
        'detection_key',
        'source_type',
        'source_url',
        'source_label',
        'evidence_text',
        'warning',
        'detected_at',
        'confirmed_at',
        'confirmed_by',
        'count_applied_at',
        'notes',
    ];

    protected $casts = [
        'awarded_on' => 'date',
        'series_start_game' => 'integer',
        'series_end_game' => 'integer',
        'series_total' => 'integer',
        'series_scores' => 'array',
        'certification_number_value' => 'integer',
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'count_applied_at' => 'datetime',
    ];

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceGameScore()
    {
        return $this->belongsTo(GameScore::class, 'source_game_score_id');
    }

    public function scoreSeriesDefinition()
    {
        return $this->belongsTo(ScoreSeriesDefinition::class);
    }

    public function confirmedByUser()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCandidates($query)
    {
        return $query->where('status', self::STATUS_CANDIDATE);
    }

    public function getRecordTypeLabelAttribute(): string
    {
        return match ($this->record_type) {
            'perfect' => '公認パーフェクト',
            'eight_hundred' => '公認800シリーズ',
            'seven_ten' => '公認7－10メイド',
            default => (string) $this->record_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CANDIDATE => '確認待ち',
            self::STATUS_CONFIRMED => '確認済み',
            self::STATUS_REJECTED => '却下',
            self::STATUS_VOID => '無効',
            default => (string) $this->status,
        };
    }
}
