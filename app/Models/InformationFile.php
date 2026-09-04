<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InformationFile extends Model
{
    protected $table = 'information_files';

    protected $fillable = [
        'information_id',
        'type',
        'title',
        'file_path',
        'visibility',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public function information(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Information::class, 'information_id');
    }

    public function normalizedPath(): string
    {
        $path = str_replace('\\', '/', trim((string) $this->file_path));
        $path = preg_replace('#^/?storage/#', '', $path) ?? $path;
        $path = preg_replace('#^/?public/#', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            return '';
        }

        return $path;
    }

    public function publicUrl(): ?string
    {
        $resolved = $this->resolvedFile();
        if ($resolved === null) {
            return null;
        }

        return $resolved['disk'] === 'public_path'
            ? asset($this->normalizedPath())
            : Storage::disk('public')->url($this->normalizedPath());
    }

    public function absolutePath(): ?string
    {
        return $this->resolvedFile()['path'] ?? null;
    }

    /** @return null|array{disk:string,path:string} */
    private function resolvedFile(): ?array
    {
        $path = $this->normalizedPath();
        if ($path === '') {
            return null;
        }

        $candidates = [
            ['disk' => 'public_path', 'root' => public_path(), 'path' => public_path($path)],
            ['disk' => 'storage', 'root' => Storage::disk('public')->path(''), 'path' => Storage::disk('public')->path($path)],
        ];

        foreach ($candidates as $candidate) {
            $root = realpath($candidate['root']);
            $file = realpath($candidate['path']);
            if ($root === false || $file === false || ! File::isFile($file)) {
                continue;
            }

            $rootPrefix = rtrim(str_replace('\\', '/', $root), '/').'/';
            $normalizedFile = str_replace('\\', '/', $file);
            if (! str_starts_with($normalizedFile, $rootPrefix)) {
                continue;
            }

            return ['disk' => $candidate['disk'], 'path' => $file];
        }

        return null;
    }
}
