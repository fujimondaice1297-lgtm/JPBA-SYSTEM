<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TournamentResultFormatVersion extends Model
{
    protected $fillable = [
        'tournament_result_format_id',
        'version_no',
        'template_disk',
        'template_path',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'version_no' => 'integer',
        'is_active' => 'boolean',
    ];

    public function format(): BelongsTo
    {
        return $this->belongsTo(TournamentResultFormat::class, 'tournament_result_format_id');
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function absoluteTemplatePath(): string
    {
        $path = $this->template_disk === 'resource'
            ? base_path($this->template_path)
            : Storage::disk($this->template_disk)->path($this->template_path);

        if (! is_file($path)) {
            throw new RuntimeException('最終成績Excelフォーマットが見つかりません: '.$path);
        }

        return $path;
    }
}
