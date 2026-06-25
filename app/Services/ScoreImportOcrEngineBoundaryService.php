<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\Tournament;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class ScoreImportOcrEngineBoundaryService
{
    public const BOUNDARY_VERSION = 'score_ocr_engine_boundary_v1';

    public function __construct(
        private readonly ScoreImportOcrTextAdapterService $adapter,
        private readonly ScoreImportOcrResultStageService $stager
    ) {
    }

    public function buildEngineInput(Tournament $tournament, ScoreImportBatch $batch, array $options = []): array
    {
        $this->ensureImageBatch($tournament, $batch);

        return [
            'boundary_version' => self::BOUNDARY_VERSION,
            'source' => 'score_import_batches',
            'tournament_id' => (int) $tournament->id,
            'score_import_batch_id' => (int) $batch->id,
            'import_type' => $batch->import_type,
            'source_filename' => $batch->source_filename,
            'stored_path' => $batch->stored_path,
            'defaults' => $this->defaults($options, $batch),
            'expected_engine_output' => [
                'accepted_formats' => [
                    'plain_text_table',
                    'markdown_table',
                    'json_rows',
                ],
                'next_step' => self::class . '::stageTextResult',
            ],
            'guardrails' => [
                'do_not_write_directly_to' => [
                    'game_scores',
                    'tournament_results',
                ],
                'stage_rows_table' => 'score_import_rows',
                'human_confirmation_required' => true,
            ],
        ];
    }

    public function previewTextResult(string $engineText, array $options = []): array
    {
        return $this->adapter->adapt($engineText, $this->defaults($options));
    }

    public function stageTextResult(
        Tournament $tournament,
        ScoreImportBatch $batch,
        string $engineText,
        ?Authenticatable $user = null,
        array $options = []
    ): array {
        $this->ensureImageBatch($tournament, $batch);

        $defaults = $this->defaults($options, $batch);
        $adapted = $this->adapter->adapt($engineText, $defaults);
        $adapterSummary = array_merge($adapted['summary'], [
            'boundary_version' => self::BOUNDARY_VERSION,
            'engine_name' => $options['engine_name'] ?? null,
        ]);

        $importSummary = $this->stager->importPayload($tournament, $batch, $adapted['payload'], $user, array_merge($defaults, [
            'replace_existing' => (bool) ($options['replace_existing'] ?? false),
            'source_filename' => (string) ($options['source_filename'] ?? ('ocr_engine_' . now()->format('Ymd_His') . '.json')),
            'operation_action' => (string) ($options['operation_action'] ?? 'ocr_engine_handoff'),
            'operation_message' => (string) ($options['operation_message'] ?? 'OCRエンジン出力をJSON仕様へ変換して確認用行へ変換しました。'),
            'adapter_summary' => $adapterSummary,
        ]));

        return [
            'import_summary' => $importSummary,
            'adapter_summary' => $adapterSummary,
            'payload' => $adapted['payload'],
            'engine_input' => $this->buildEngineInput($tournament, $batch, $defaults),
        ];
    }

    private function ensureImageBatch(Tournament $tournament, ScoreImportBatch $batch): void
    {
        if ((int) $batch->tournament_id !== (int) $tournament->id) {
            throw new InvalidArgumentException('この大会のスコア原本バッチではありません。');
        }

        if ($batch->import_type !== 'score_sheet_image') {
            throw new InvalidArgumentException('OCRエンジン接続は写真/PDF原本バッチにだけ使用できます。');
        }
    }

    private function defaults(array $options, ?ScoreImportBatch $batch = null): array
    {
        $batchDefaults = $batch ? $this->defaultsFromBatchNotes((string) $batch->notes) : [];

        return [
            'default_stage' => $this->stringOption($options, 'default_stage', $batchDefaults),
            'default_shift' => $this->stringOption($options, 'default_shift', $batchDefaults),
            'default_gender' => $this->stringOption($options, 'default_gender', $batchDefaults),
            'default_game_number' => $this->stringOption($options, 'default_game_number', $batchDefaults),
        ];
    }

    private function defaultsFromBatchNotes(string $notes): array
    {
        $defaults = [];
        foreach (explode('/', $notes) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $part, 2));
            if (in_array($key, ['default_stage', 'default_shift', 'default_gender', 'default_game_number'], true)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function stringOption(array $options, string $key, array $fallbacks): string
    {
        $value = array_key_exists($key, $options) && $options[$key] !== null && $options[$key] !== ''
            ? $options[$key]
            : ($fallbacks[$key] ?? '');

        return trim((string) $value);
    }
}
