<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestSession extends Model
{
    protected $fillable = [
        'pro_test_event_id',
        'gender',
        'stage_code',
        'stage_label',
        'day_number',
        'test_date',
        'venue',
        'game_start',
        'game_end',
        'pass_average',
        'is_stage_final',
        'status',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'test_date' => 'date',
        'game_start' => 'integer',
        'game_end' => 'integer',
        'pass_average' => 'decimal:2',
        'is_stage_final' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(ProTestEvent::class, 'pro_test_event_id');
    }

    public function scores()
    {
        return $this->hasMany(ProTestScore::class);
    }

    public function publications()
    {
        return $this->hasMany(ProTestResultPublication::class)->orderByDesc('revision');
    }

    public function latestPublication()
    {
        return $this->hasOne(ProTestResultPublication::class)->latestOfMany('revision');
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'F' ? '女子' : '男子';
    }

    public function getGameCountAttribute(): int
    {
        return max(0, $this->game_end - $this->game_start + 1);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->gender_label} {$this->stage_label} {$this->day_number}日目";
    }
}
