<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ApprovedBall extends Model
{
    protected $table = 'approved_balls';

    protected $appends = [
        'release_year',
    ];

    protected $fillable = [
        'id',
        'name',
        'manufacturer',
        'manufacturer_id',
        'brand',
        'name_kana',
        'sort_name',
        'approved',
        'usbc_match_status',
        'usbc_match_method',
        'usbc_matched_brand',
        'usbc_matched_name',
        'usbc_match_candidates',
        'usbc_checked_at',
        'release_date',
        'source_key',
        'source_url',
        'source_image_url',
        'image_path',
        'image_sha256',
        'catalog_status',
        'source_payload',
        'source_fingerprint',
        'first_seen_at',
        'last_seen_at',
        'imported_at',
        'image_imported_at',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'usbc_match_candidates' => 'array',
        'usbc_checked_at' => 'datetime',
        'release_date' => 'date',
        'source_payload' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'imported_at' => 'datetime',
        'image_imported_at' => 'datetime',
    ];

    public function catalogManufacturer()
    {
        return $this->belongsTo(BallManufacturer::class, 'manufacturer_id');
    }

    public function proBowlers()
    {
        return $this->belongsToMany(
            User::class,
            'approved_ball_pro_bowler',
            'approved_ball_id',
            'pro_bowler_license_no',
            'id'
        )
            ->withPivot('year')
            ->withTimestamps();
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path && Storage::disk('public')->exists($this->image_path)) {
            return route('approved_balls.image', ['approved_ball' => $this->getKey()]);
        }

        $payload = (array) $this->source_payload;
        if (
            (($payload['source_type'] ?? null) === 'usbc_approved_list')
            && filter_var($this->source_image_url, FILTER_VALIDATE_URL)
        ) {
            return (string) $this->source_image_url;
        }

        return asset('images/ball-no-image.svg');
    }

    public function getRegistrationBrandAttribute(): string
    {
        $officialBrand = trim((string) $this->usbc_matched_brand);
        if ($this->usbc_match_status === 'matched' && $officialBrand !== '') {
            return $officialBrand;
        }

        return trim((string) ($this->brand ?: $this->manufacturer));
    }

    public function getRegistrationPeriodLabelAttribute(): string
    {
        if (! $this->release_date) {
            return '';
        }

        $payload = (array) $this->source_payload;
        if (($payload['release_date_basis'] ?? null) === 'usbc_approved_on') {
            return 'USBC承認 '.$this->release_date->format('Y-m-d');
        }

        return '発売 '.$this->release_date->format('Y').'年';
    }

    public function getReleaseDisplayAttribute(): string
    {
        $payload = (array) $this->source_payload;
        $releaseText = trim((string) ($payload['release_text'] ?? ''));
        if ($releaseText !== '') {
            return $releaseText;
        }

        if (! $this->release_date) {
            return '―';
        }

        if (($payload['release_date_basis'] ?? null) === 'official_publish_date') {
            return sprintf(
                '%d年%d月発売',
                (int) $this->release_date->format('Y'),
                (int) $this->release_date->format('n')
            );
        }

        if (($payload['release_date_basis'] ?? null) === 'usbc_approved_on') {
            return 'USBC承認 '.$this->release_date->format('Y-m-d');
        }

        return $this->release_date->format('Y-m-d');
    }

    public function getReleaseYearAttribute(): ?int
    {
        return $this->release_date
            ? (int) $this->release_date->format('Y')
            : null;
    }
}
