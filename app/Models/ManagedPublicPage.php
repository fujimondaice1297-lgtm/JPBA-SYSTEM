<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManagedPublicPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'body_html',
        'source_url',
        'navigation_group',
        'sort_order',
        'is_published',
        'published_at',
        'source_checked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'source_checked_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
