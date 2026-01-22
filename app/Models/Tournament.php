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
        'broadcast_url',
        'streaming_url',
        'prize',
        'admission_fee',
        'spectator_policy',
        'entry_conditions',
        'materials',
        'previous_event',
        'previous_event_url',
        'image_path',
        'hero_image_path',
        'title_logo_path',     // ★ タイトル左ロゴ
        'poster_images',
        'year',
        'gender',
        'official_type',
        'entry_start',
        'entry_end',
        'inspection_required',
        'title_category',
        'venue_id',
        'extra_venues',

        // 右サイド・ギャラリー等
        'sidebar_schedule',     // JSON [{date,label,href}|{separator:true}]
        'award_highlights',     // JSON [{type,player,game,lane,note,title,photo}]
        'gallery_items',        // JSON [{photo,title}]
        'simple_result_pdfs',   // JSON [{file,title}]

        // ★ 新規：大会終了後の「優勝者・トーナメント」カード
        'result_cards',         // JSON [{title,player,balls,note,url,photo,file}]
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'entry_start'=> 'date:Y-m-d',
        'entry_end'  => 'date:Y-m-d',
        'inspection_required' => 'boolean',
        'poster_images' => 'array',
        'extra_venues'  => 'array',
        'sidebar_schedule' => 'array',
        'award_highlights' => 'array',
        'gallery_items' => 'array',
        'simple_result_pdfs' => 'array',
        'result_cards' => 'array',   // ★ 追加
    ];

    public function prizeDistributions()
    {
        return $this->hasMany(PrizeDistribution::class);
    }

    public function pointDistributions()
    {
        return $this->hasMany(PointDistribution::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function organizations()
    {
        return $this->hasMany(TournamentOrganization::class);
    }

    public function files()
    {
        return $this->hasMany(TournamentFile::class);
    }

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
                $sd = $tournament->start_date instanceof \DateTimeInterface
                    ? $tournament->start_date
                    : Carbon::parse($tournament->start_date);
                $tournament->year = $sd->year;
            }
            if (!$tournament->gender) $tournament->gender = 'X';
            if (!$tournament->official_type) $tournament->official_type = 'official';
            if (!$tournament->title_category) $tournament->title_category = 'normal';
        });
    }
}
