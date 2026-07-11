<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialTitleImportCandidate extends Model
{
    protected $fillable = [
        'pro_bowler_id',
        'license_no',
        'license_no_num',
        'name_kanji',
        'title_name',
        'title_category',
        'year',
        'won_date',
        'venue_name',
        'source_url',
        'source_result_url',
        'source_label',
        'raw_text',
        'confidence',
        'status',
        'error',
        'candidate_hash',
        'promoted_pro_bowler_title_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'won_date' => 'date',
        'license_no_num' => 'integer',
        'confidence' => 'integer',
        'pro_bowler_id' => 'integer',
        'promoted_pro_bowler_title_id' => 'integer',
    ];

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }

    public function promotedTitle()
    {
        return $this->belongsTo(ProBowlerTitle::class, 'promoted_pro_bowler_title_id');
    }

    public function isSeasonTrial(): bool
    {
        return (string) $this->title_category === 'season_trial';
    }
}
