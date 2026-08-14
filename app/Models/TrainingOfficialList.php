<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingOfficialList extends Model
{
    protected $fillable = [
        'training_id', 'edition_number', 'title', 'valid_from', 'valid_through',
        'source_page_url', 'source_url', 'source_published_at', 'source_sha256',
        'is_current', 'sync_status', 'total_count', 'male_count', 'female_count',
        'matched_count', 'unmatched_count', 'inactive_count', 'imported_at',
        'imported_by_user_id', 'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_through' => 'date',
        'source_published_at' => 'datetime',
        'is_current' => 'boolean',
        'imported_at' => 'datetime',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    public function entries()
    {
        return $this->hasMany(TrainingOfficialListEntry::class, 'training_official_list_id');
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }
}
