<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreImportOperationLog extends Model
{
    protected $fillable = [
        'tournament_id',
        'score_import_batch_id',
        'action',
        'status',
        'actor_user_id',
        'target_row_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'message',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'target_row_count' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function batch()
    {
        return $this->belongsTo(ScoreImportBatch::class, 'score_import_batch_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
