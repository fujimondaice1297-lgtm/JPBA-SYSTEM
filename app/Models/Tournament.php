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
        'title_logo_path',
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

        'shift_codes',
        'shift_draw_open_at',
        'shift_draw_close_at',
        'lane_draw_open_at',
        'lane_draw_close_at',
        'lane_from',
        'lane_to',
        'use_shift_draw',
        'use_lane_draw',
        'lane_assignment_mode',
        'box_player_count',
        'odd_lane_player_count',
        'even_lane_player_count',
        'accept_shift_preference',

        'sidebar_schedule',
        'award_highlights',
        'gallery_items',
        'simple_result_pdfs',
        'result_cards',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'entry_start'=> 'datetime:Y-m-d H:i:s',
        'entry_end'  => 'datetime:Y-m-d H:i:s',
        'shift_draw_open_at' => 'datetime:Y-m-d H:i:s',
        'shift_draw_close_at' => 'datetime:Y-m-d H:i:s',
        'lane_draw_open_at' => 'datetime:Y-m-d H:i:s',
        'lane_draw_close_at' => 'datetime:Y-m-d H:i:s',
        'inspection_required' => 'boolean',
        'use_shift_draw' => 'boolean',
        'use_lane_draw' => 'boolean',
        'accept_shift_preference' => 'boolean',
        'poster_images' => 'array',
        'extra_venues'  => 'array',
        'sidebar_schedule' => 'array',
        'award_highlights' => 'array',
        'gallery_items' => 'array',
        'simple_result_pdfs' => 'array',
        'result_cards' => 'array',
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

    public function entries()
    {
        return $this->hasMany(\App\Models\TournamentEntry::class);
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

            if (!$tournament->gender) {
                $tournament->gender = 'X';
            }
            if (!$tournament->official_type) {
                $tournament->official_type = 'official';
            }
            if (!$tournament->title_category) {
                $tournament->title_category = 'normal';
            }
            if (!$tournament->lane_assignment_mode) {
                $tournament->lane_assignment_mode = 'single_lane';
            }
        });
    }
}