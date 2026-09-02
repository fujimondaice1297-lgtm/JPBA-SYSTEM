<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProTestEvent extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'year',
        'name',
        'start_date',
        'end_date',
        'application_start',
        'application_end',
        'male_generation',
        'female_generation',
        'status',
        'public_summary',
        'final_results_published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'application_start' => 'date',
        'application_end' => 'date',
        'final_results_published_at' => 'datetime',
    ];

    public function sessions()
    {
        return $this->hasMany(ProTestSession::class)->orderBy('sort_order')->orderBy('id');
    }

    public function candidates()
    {
        return $this->hasMany(ProTestCandidate::class)->orderBy('gender')->orderBy('exam_number');
    }

    public function finalResultPublications()
    {
        return $this->hasMany(ProTestFinalResultPublication::class)->orderByDesc('revision');
    }

    public function latestFinalResultPublication()
    {
        return $this->hasOne(ProTestFinalResultPublication::class)->latestOfMany('revision');
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where(function ($nested): void {
            $nested->whereNotNull('final_results_published_at')
                ->orWhereHas('sessions', fn ($session) => $session->whereNotNull('published_at'));
        });
    }
}
