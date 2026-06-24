<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreImportBatch extends Model
{
    protected $fillable = [
        'tournament_id',
        'import_type',
        'source_filename',
        'stored_path',
        'status',
        'parser_version',
        'imported_by',
        'confirmed_by',
        'row_count',
        'accepted_row_count',
        'rejected_row_count',
        'parsed_at',
        'confirmed_at',
        'error_message',
        'notes',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'accepted_row_count' => 'integer',
        'rejected_row_count' => 'integer',
        'parsed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rows()
    {
        return $this->hasMany(ScoreImportRow::class);
    }

    public function operationLogs()
    {
        return $this->hasMany(ScoreImportOperationLog::class);
    }
}
