<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingOfficialListEntry extends Model
{
    protected $fillable = [
        'training_official_list_id', 'pro_bowler_id', 'gender', 'license_no_num',
        'source_order', 'source_name', 'match_status', 'notes',
    ];

    public function officialList()
    {
        return $this->belongsTo(TrainingOfficialList::class, 'training_official_list_id');
    }

    public function bowler()
    {
        return $this->belongsTo(ProBowler::class, 'pro_bowler_id');
    }
}
