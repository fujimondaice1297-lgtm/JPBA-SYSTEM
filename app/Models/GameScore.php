<?php

namespace App\Models;

use App\Services\AchievementDetectionService;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class GameScore extends Model
{
    protected $fillable = [
        'tournament_id', 'stage', 'shift', 'gender',
        'license_number', 'name', 'entry_number',
        'game_number', 'score', 'pro_bowler_id',
        'tournament_participant_id',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'game_number' => 'integer',
        'score' => 'integer',
        'pro_bowler_id' => 'integer',
        'tournament_participant_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (GameScore $score): void {
            try {
                app(AchievementDetectionService::class)->scanGameScore($score);
            } catch (Throwable $e) {
                report($e);
            }
        });

        static::deleted(function (GameScore $score): void {
            try {
                app(AchievementDetectionService::class)->handleDeletedGameScore($score);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function proBowler()
    {
        return $this->belongsTo(ProBowler::class);
    }
}
