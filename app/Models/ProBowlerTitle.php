<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProBowlerTitle extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'tournament_id',
        'title_name',   // ← 大会名のスナップショット用に使う
        'year',
        'won_date',
        'source',
        'source_url',
        'source_label',
        'tournament_name',
    ];

    protected $casts = [
        'won_date' => 'date',
    ];

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function isSeasonTrialTitle(): bool
    {
        $titleName = (string) ($this->title_name ?? '');
        $tournamentName = (string) ($this->tournament_name ?? '');
        $source = (string) ($this->source ?? '');
        $category = (string) (optional($this->tournament)->title_category ?? '');

        return $category === 'season_trial'
            || $source === 'sync_from_results_season_trial'
            || str_contains($titleName, 'シーズントライアル')
            || str_contains($tournamentName, 'シーズントライアル');
    }

    public function isOfficialTitle(): bool
    {
        return ! $this->isSeasonTrialTitle();
    }

}
