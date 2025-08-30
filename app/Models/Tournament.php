<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Tournament extends Model
{
    protected $table = 'tournaments';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'venue_name',
        'venue_address',
        'venue_tel',
        'venue_fax',
        'host',
        'special_sponsor',
        'support',
        'sponsor',
        'supervisor',
        'authorized_by',
        'broadcast',
        'streaming',
        'prize',
        'audience',
        'entry_conditions',
        'materials',
        'previous_event',
        'image_path',
        'year',
        'gender',         
        'official_type', 
        'entry_start',
        'entry_end',
        'inspection_required',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'entry_start'=> 'date:Y-m-d',
        'entry_end'  => 'date:Y-m-d',
        'inspection_required' => 'boolean',
    ];

    public function prizeDistributions()
    {
        return $this->hasMany(PrizeDistribution::class);
    }

    public function pointDistributions()
    {
        return $this->hasMany(PointDistribution::class);
    }

    // 表示用ラベル
    public function getGenderLabelAttribute(): string
    {
        return match($this->gender) {
            'M' => '男子',
            'F' => '女子',
            default => '男女',
        };
    }

    public function getOfficialTypeLabelAttribute(): string
    {
        return match($this->official_type) {
            'approved' => '承認',
            'other'    => 'その他',
            default    => '公認',
        };
    }

    public function entries() {
        return $this->hasMany(\App\Models\TournamentEntry::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($tournament) {
            if ($tournament->start_date && !$tournament->year) {
                // casts が効くのでそのまま year が取れる
                $sd = $tournament->start_date instanceof \DateTimeInterface
                    ? $tournament->start_date
                    : Carbon::parse($tournament->start_date);
                $tournament->year = $sd->year;
            }
            // デフォルト保険
            if (!$tournament->gender) $tournament->gender = 'X';
            if (!$tournament->official_type) $tournament->official_type = 'official';
        });
    }
}
