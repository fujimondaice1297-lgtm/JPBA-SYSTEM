<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ScoreImportImageStageService
{
    private const PARSER_VERSION = 'score_image_stage_v1';

    public function import(Tournament $tournament, UploadedFile $file, ?Authenticatable $user = null, array $options = []): ScoreImportBatch
    {
        $storedPath = $file->storeAs(
            'score-import-images/' . $tournament->id,
            now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $this->safeFilename($file->getClientOriginalName())
        );

        if (! $storedPath) {
            throw new InvalidArgumentException('スコア原本ファイルを保存できませんでした。');
        }

        $batch = ScoreImportBatch::create([
            'tournament_id' => $tournament->id,
            'import_type' => 'score_sheet_image',
            'source_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'draft',
            'parser_version' => self::PARSER_VERSION,
            'imported_by' => $user ? (int) $user->getAuthIdentifier() : null,
            'row_count' => 0,
            'accepted_row_count' => 0,
            'rejected_row_count' => 0,
            'notes' => $this->buildNotes($options),
        ]);

        app(ScoreImportOperationLogger::class)->log($batch, 'image_stage', [
            'status' => 'success',
            'target_row_count' => 1,
            'created_count' => 1,
            'message' => 'OCR解析待ちのスコア原本として保存しました。',
            'payload' => [
                'source_filename' => $batch->source_filename,
                'stored_path' => $batch->stored_path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'parser_version' => self::PARSER_VERSION,
                'options' => $this->loggableOptions($options),
            ],
        ], $user);

        return $batch;
    }

    private function safeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', $name);
        $name = trim((string) $name, '._-');

        return $name !== '' ? $name : 'score-sheet';
    }

    private function buildNotes(array $options): ?string
    {
        $notes = [];

        foreach (['default_stage', 'default_shift', 'default_gender'] as $key) {
            if (($options[$key] ?? '') !== '') {
                $notes[] = $key . '=' . $options[$key];
            }
        }

        if (($options['notes'] ?? '') !== '') {
            $notes[] = 'notes=' . $options['notes'];
        }

        return empty($notes) ? 'OCR解析待ち' : implode(' / ', $notes);
    }

    private function loggableOptions(array $options): array
    {
        return array_filter([
            'default_stage' => $options['default_stage'] ?? null,
            'default_shift' => $options['default_shift'] ?? null,
            'default_gender' => $options['default_gender'] ?? null,
            'notes' => $options['notes'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
