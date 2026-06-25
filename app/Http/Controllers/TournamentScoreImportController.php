<?php

namespace App\Http\Controllers;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportRow;
use App\Models\Tournament;
use App\Services\ScoreImportCommitService;
use App\Services\ScoreImportCsvStageService;
use App\Services\ScoreImportImageStageService;
use App\Services\ScoreImportOperationLogger;
use App\Services\ScoreImportOcrEngineBoundaryService;
use App\Services\ScoreImportOcrResultStageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class TournamentScoreImportController extends Controller
{
    public function storeCsv(Request $request, Tournament $tournament, ScoreImportCsvStageService $importer)
    {
        $this->authorizeEditorOrAdmin();

        $validated = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'default_stage' => ['nullable', 'string', 'max:50'],
            'default_shift' => ['nullable', 'string', 'max:20'],
            'default_gender' => ['nullable', 'string', 'max:10'],
            'default_game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $batch = $importer->import($tournament, $request->file('csv'), auth()->user(), [
                'default_stage' => $validated['default_stage'] ?? '',
                'default_shift' => $validated['default_shift'] ?? '',
                'default_gender' => $validated['default_gender'] ?? '',
                'default_game_number' => $validated['default_game_number'] ?? '',
            ]);
        } catch (Throwable $e) {
            return back()->withErrors([
                'csv' => 'スコアCSVの一時取込に失敗しました: ' . $e->getMessage(),
            ])->withInput();
        }

        $needsReviewCount = $batch->rows()->where('parse_status', 'needs_review')->count();

        return redirect()
            ->route('tournaments.operation_logs.index', $tournament->id)
            ->with('success', sprintf(
                'スコアCSVを一時取込しました。取込ID: %d / 取込行: %d / 要確認: %d',
                $batch->id,
                $batch->row_count,
                $needsReviewCount
            ));
    }

    public function storePaste(Request $request, Tournament $tournament, ScoreImportCsvStageService $importer)
    {
        $this->authorizeEditorOrAdmin();

        $validated = $request->validate([
            'score_paste' => ['required', 'string', 'max:200000'],
            'paste_default_stage' => ['nullable', 'string', 'max:50'],
            'paste_default_shift' => ['nullable', 'string', 'max:20'],
            'paste_default_gender' => ['nullable', 'string', 'max:10'],
            'paste_default_game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'score_paste_');
        if ($tempPath === false) {
            return back()->withErrors([
                'score_paste' => '貼り付けデータの一時ファイルを作成できませんでした。',
            ])->withInput();
        }

        try {
            $csv = $this->pastedTableToCsv($validated['score_paste']);
            file_put_contents($tempPath, mb_convert_encoding($csv, 'CP932', 'UTF-8'));

            $file = new UploadedFile($tempPath, 'pasted_scores.csv', 'text/csv', null, true);
            $batch = $importer->import($tournament, $file, auth()->user(), [
                'default_stage' => $validated['paste_default_stage'] ?? '',
                'default_shift' => $validated['paste_default_shift'] ?? '',
                'default_gender' => $validated['paste_default_gender'] ?? '',
                'default_game_number' => $validated['paste_default_game_number'] ?? '',
            ]);
        } catch (Throwable $e) {
            return back()->withErrors([
                'score_paste' => '貼り付けスコアの一時取込に失敗しました: ' . $e->getMessage(),
            ])->withInput();
        } finally {
            @unlink($tempPath);
        }

        $needsReviewCount = $batch->rows()->where('parse_status', 'needs_review')->count();

        return redirect()
            ->route('tournaments.operation_logs.index', $tournament->id)
            ->with('success', sprintf(
                '貼り付けスコアを一時取込しました。取込ID: %d / 取込行: %d / 要確認: %d',
                $batch->id,
                $batch->row_count,
                $needsReviewCount
            ));
    }

    public function storeImage(Request $request, Tournament $tournament, ScoreImportImageStageService $importer)
    {
        $this->authorizeEditorOrAdmin();

        $validated = $request->validate([
            'score_sheet' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
            'image_default_stage' => ['nullable', 'string', 'max:50'],
            'image_default_shift' => ['nullable', 'string', 'max:20'],
            'image_default_gender' => ['nullable', 'string', 'max:10'],
            'image_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $batch = $importer->import($tournament, $request->file('score_sheet'), auth()->user(), [
                'default_stage' => $validated['image_default_stage'] ?? '',
                'default_shift' => $validated['image_default_shift'] ?? '',
                'default_gender' => $validated['image_default_gender'] ?? '',
                'notes' => $validated['image_notes'] ?? '',
            ]);
        } catch (Throwable $e) {
            return back()->withErrors([
                'score_sheet' => 'スコア原本ファイルの一時保存に失敗しました: ' . $e->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('tournaments.operation_logs.index', $tournament->id)
            ->with('success', sprintf(
                'スコア原本ファイルをOCR解析待ちとして保存しました。取込ID: %d',
                $batch->id
            ));
    }

    public function show(Request $request, Tournament $tournament, ScoreImportBatch $scoreImport)
    {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $status = (string) $request->query('status', '');
        $rows = $scoreImport->rows()
            ->with([
                'candidates' => function ($query) {
                    $query->orderBy('rank')->orderByDesc('confidence')->orderBy('id');
                },
                'participant',
                'bowler',
                'confirmedGameScore',
            ])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('parse_status', $status);
            })
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => $scoreImport->rows()->count(),
            'parsed' => $scoreImport->rows()->where('parse_status', 'parsed')->count(),
            'accepted' => $scoreImport->rows()->where('parse_status', 'accepted')->count(),
            'needs_review' => $scoreImport->rows()->where('parse_status', 'needs_review')->count(),
            'rejected' => $scoreImport->rows()->where('parse_status', 'rejected')->count(),
            'confirmed' => $scoreImport->rows()->whereNotNull('confirmed_game_score_id')->count(),
        ];
        $operationLogs = $scoreImport->operationLogs()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('score_imports.show', compact(
            'tournament',
            'scoreImport',
            'rows',
            'status',
            'summary',
            'operationLogs',
        ));
    }

    public function storeOcrJson(
        Request $request,
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportOcrResultStageService $importer
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $validated = $request->validate([
            'ocr_json' => ['required', 'file', 'mimes:json,txt', 'max:10240'],
            'ocr_default_stage' => ['nullable', 'string', 'max:50'],
            'ocr_default_shift' => ['nullable', 'string', 'max:20'],
            'ocr_default_gender' => ['nullable', 'string', 'max:10'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $summary = $importer->importJson($tournament, $scoreImport, $request->file('ocr_json'), auth()->user(), [
                'default_stage' => $validated['ocr_default_stage'] ?? '',
                'default_shift' => $validated['ocr_default_shift'] ?? '',
                'default_gender' => $validated['ocr_default_gender'] ?? '',
                'replace_existing' => $request->boolean('replace_existing'),
            ]);
        } catch (Throwable $e) {
            return back()->withErrors([
                'ocr_json' => 'OCR解析結果JSONの取込に失敗しました: ' . $e->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id])
            ->with('success', sprintf(
                'OCR解析結果を確認用行へ変換しました。作成: %d / 要確認: %d',
                $summary['created'],
                $summary['needs_review']
            ));
    }

    public function storeOcrAdapter(
        Request $request,
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportOcrEngineBoundaryService $ocrBoundary
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $validated = $request->validate([
            'ocr_adapter_text' => ['required', 'string', 'max:300000'],
            'ocr_adapter_default_stage' => ['nullable', 'string', 'max:50'],
            'ocr_adapter_default_shift' => ['nullable', 'string', 'max:20'],
            'ocr_adapter_default_gender' => ['nullable', 'string', 'max:10'],
            'ocr_adapter_default_game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'ocr_adapter_replace_existing' => ['nullable', 'boolean'],
        ]);

        $defaults = [
            'default_stage' => $validated['ocr_adapter_default_stage'] ?? '',
            'default_shift' => $validated['ocr_adapter_default_shift'] ?? '',
            'default_gender' => $validated['ocr_adapter_default_gender'] ?? '',
            'default_game_number' => $validated['ocr_adapter_default_game_number'] ?? '',
        ];

        try {
            $result = $ocrBoundary->stageTextResult($tournament, $scoreImport, $validated['ocr_adapter_text'], auth()->user(), array_merge($defaults, [
                'replace_existing' => $request->boolean('ocr_adapter_replace_existing'),
                'source_filename' => 'ocr_ai_adapter_' . now()->format('Ymd_His') . '.json',
                'operation_action' => 'ocr_adapter_stage',
                'operation_message' => 'OCR/AI出力をJSON仕様へ変換して確認用行へ変換しました。',
            ]));
            $summary = $result['import_summary'];
        } catch (Throwable $e) {
            return back()->withErrors([
                'ocr_adapter_text' => 'OCR/AI出力の変換に失敗しました: ' . $e->getMessage(),
            ])->withInput();
        }

        $warningCount = (int) ($result['adapter_summary']['warning_count'] ?? 0);

        return redirect()
            ->route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id])
            ->with('success', sprintf(
                'OCR/AI出力を変換して確認用行へ反映しました。作成: %d / 要確認: %d / 変換警告: %d',
                $summary['created'],
                $summary['needs_review'],
                $warningCount
            ));
    }

    public function previewOcrAdapter(
        Request $request,
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportOcrEngineBoundaryService $ocrBoundary
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $validated = $request->validate([
            'ocr_adapter_text' => ['required', 'string', 'max:300000'],
            'ocr_adapter_default_stage' => ['nullable', 'string', 'max:50'],
            'ocr_adapter_default_shift' => ['nullable', 'string', 'max:20'],
            'ocr_adapter_default_gender' => ['nullable', 'string', 'max:10'],
            'ocr_adapter_default_game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $defaults = [
            'default_stage' => $validated['ocr_adapter_default_stage'] ?? '',
            'default_shift' => $validated['ocr_adapter_default_shift'] ?? '',
            'default_gender' => $validated['ocr_adapter_default_gender'] ?? '',
            'default_game_number' => $validated['ocr_adapter_default_game_number'] ?? '',
        ];

        $adapted = $ocrBoundary->previewTextResult($validated['ocr_adapter_text'], $defaults);

        return response()
            ->json([
                'summary' => $adapted['summary'],
                'boundary' => $ocrBoundary->buildEngineInput($tournament, $scoreImport, $defaults),
                'payload' => $adapted['payload'],
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'inline; filename="score_ocr_adapter_preview.json"');
    }

    public function ocrJsonSample(Tournament $tournament, ScoreImportBatch $scoreImport)
    {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        return response()
            ->json([
                'rows' => [
                    [
                        'license_number' => 'M00001297',
                        'name' => '山田 太郎',
                        'stage' => '予選',
                        'shift' => 'A',
                        'gender' => 'M',
                        'scores' => [
                            '1' => 210,
                            '2' => 225,
                            '3' => 198,
                        ],
                        'confidence' => 0.92,
                    ],
                    [
                        'ライセンス番号' => 'F00000001',
                        '氏名' => '鈴木 花子',
                        'ステージ' => '予選',
                        'games' => [
                            [
                                'ゲーム番号' => 1,
                                'スコア' => 200,
                                '信頼度' => 88,
                            ],
                            [
                                'ゲーム番号' => 2,
                                'スコア' => 216,
                                '信頼度' => 91,
                            ],
                        ],
                    ],
                ],
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename="score_ocr_result_sample.json"');
    }

    public function updateRow(
        Request $request,
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportRow $scoreImportRow,
        ScoreImportCommitService $committer,
        ScoreImportOperationLogger $operationLogger
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);
        $this->ensureRowBelongsToBatch($scoreImportRow, $scoreImport);

        if ($scoreImportRow->confirmed_game_score_id) {
            return back()->withErrors([
                'row' => '反映済みの行は編集できません。',
            ]);
        }

        $validated = $request->validate([
            'parse_status' => ['nullable', 'string', 'in:accepted,needs_review,rejected'],
            'selected_candidate_id' => ['nullable', 'integer'],
            'tournament_participant_id' => ['nullable', 'integer', 'exists:tournament_participants,id'],
            'pro_bowler_id' => ['nullable', 'integer', 'exists:pro_bowlers,id'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'entry_number' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:50'],
            'shift' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'max:10'],
            'game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'score' => ['nullable', 'integer', 'min:0', 'max:300'],
        ]);

        $participantId = $validated['tournament_participant_id'] ?? null;
        $proBowlerId = $validated['pro_bowler_id'] ?? null;
        $selectedCandidateId = $validated['selected_candidate_id'] ?? null;

        if ($selectedCandidateId) {
            $candidate = $scoreImportRow->candidates()->whereKey($selectedCandidateId)->first();
            if (! $candidate) {
                return back()->withErrors([
                    'selected_candidate_id' => '選択した候補が見つかりません。',
                ])->withInput();
            }

            $participantId = $candidate->tournament_participant_id;
            $proBowlerId = $candidate->pro_bowler_id;
        }

        if ($participantId) {
            $participant = DB::table('tournament_participants')
                ->where('id', $participantId)
                ->where('tournament_id', $tournament->id)
                ->first();

            if (! $participant) {
                return back()->withErrors([
                    'tournament_participant_id' => 'この大会の参加者ではありません。',
                ])->withInput();
            }

            $proBowlerId = $proBowlerId ?: $participant->pro_bowler_id;
        }

        $payload = [
            'tournament_participant_id' => $participantId ?: null,
            'pro_bowler_id' => $proBowlerId ?: null,
            'license_number' => $this->nullableString($validated['license_number'] ?? null),
            'name' => $this->nullableString($validated['name'] ?? null),
            'entry_number' => $this->nullableString($validated['entry_number'] ?? null),
            'stage' => $this->nullableString($validated['stage'] ?? null),
            'shift' => $this->nullableString($validated['shift'] ?? null),
            'gender' => $this->nullableString($validated['gender'] ?? null),
            'game_number' => $validated['game_number'] ?? null,
            'score' => $validated['score'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ];

        $issues = $this->rowIssues($payload);
        $requestedStatus = (string) ($validated['parse_status'] ?? '');

        $payload['parse_status'] = $requestedStatus === 'rejected'
            ? 'rejected'
            : (empty($issues) ? 'accepted' : 'needs_review');
        $payload['error_message'] = empty($issues) ? null : implode(', ', $issues);

        $scoreImportRow->update($payload);

        if ($selectedCandidateId) {
            $scoreImportRow->candidates()->update(['is_selected' => false]);
            $scoreImportRow->candidates()->whereKey($selectedCandidateId)->update(['is_selected' => true]);
        }

        $committer->refreshBatchStatus($scoreImport->fresh(), auth()->user());
        $operationLogger->log($scoreImport->fresh(), 'row_update', [
            'status' => 'success',
            'target_row_count' => 1,
            'updated_count' => 1,
            'payload' => [
                'row_id' => $scoreImportRow->id,
                'row_number' => $scoreImportRow->row_number,
                'parse_status' => $payload['parse_status'],
                'selected_candidate_id' => $selectedCandidateId,
                'error_message' => $payload['error_message'],
            ],
        ], auth()->user());

        return redirect()
            ->route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id])
            ->with('success', '取込行を更新しました。');
    }

    public function bulkUpdateRows(
        Request $request,
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportCommitService $committer,
        ScoreImportOperationLogger $operationLogger
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['integer'],
            'bulk_parse_status' => ['nullable', 'string', 'in:accepted,needs_review,rejected'],
            'bulk_stage' => ['nullable', 'string', 'max:50'],
            'bulk_shift' => ['nullable', 'string', 'max:20'],
            'bulk_gender' => ['nullable', 'string', 'max:10'],
            'bulk_game_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'apply_empty_only' => ['nullable', 'boolean'],
        ]);

        $applyEmptyOnly = $request->boolean('apply_empty_only');
        $requestedStatus = (string) ($validated['bulk_parse_status'] ?? '');
        $fieldValues = [
            'stage' => $request->filled('bulk_stage') ? $this->nullableString($validated['bulk_stage']) : null,
            'shift' => $request->filled('bulk_shift') ? $this->nullableString($validated['bulk_shift']) : null,
            'gender' => $request->filled('bulk_gender') ? $this->nullableString($validated['bulk_gender']) : null,
            'game_number' => $request->filled('bulk_game_number') ? (int) $validated['bulk_game_number'] : null,
        ];

        $rows = $scoreImport->rows()
            ->whereIn('id', $validated['row_ids'])
            ->whereNull('confirmed_game_score_id')
            ->orderBy('row_number')
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors([
                'row_ids' => '更新できる未反映行が選択されていません。',
            ]);
        }

        $updated = 0;
        foreach ($rows as $row) {
            $payload = [
                'tournament_participant_id' => $row->tournament_participant_id,
                'pro_bowler_id' => $row->pro_bowler_id,
                'license_number' => $row->license_number,
                'name' => $row->name,
                'entry_number' => $row->entry_number,
                'stage' => $row->stage,
                'shift' => $row->shift,
                'gender' => $row->gender,
                'game_number' => $row->game_number,
                'score' => $row->score,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ];

            foreach ($fieldValues as $field => $value) {
                if ($value === null) {
                    continue;
                }

                if (! $applyEmptyOnly || $this->isBlank($payload[$field] ?? null)) {
                    $payload[$field] = $value;
                }
            }

            $issues = $this->rowIssues($payload);
            $payload['parse_status'] = match ($requestedStatus) {
                'rejected' => 'rejected',
                'needs_review' => 'needs_review',
                'accepted' => empty($issues) ? 'accepted' : 'needs_review',
                default => $row->parse_status === 'rejected'
                    ? 'rejected'
                    : (empty($issues) ? 'accepted' : 'needs_review'),
            };
            $payload['error_message'] = empty($issues) || $payload['parse_status'] === 'rejected'
                ? null
                : implode(', ', $issues);

            $row->update($payload);
            $updated++;
        }

        $committer->refreshBatchStatus($scoreImport->fresh(), auth()->user());
        $operationLogger->log($scoreImport->fresh(), 'bulk_update', [
            'status' => 'success',
            'target_row_count' => $updated,
            'updated_count' => $updated,
            'payload' => [
                'row_ids' => array_values($validated['row_ids']),
                'field_values' => $fieldValues,
                'bulk_parse_status' => $requestedStatus,
                'apply_empty_only' => $applyEmptyOnly,
            ],
        ], auth()->user());

        return redirect()
            ->route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id])
            ->with('success', sprintf('%d 行を一括更新しました。', $updated));
    }

    public function commit(
        Tournament $tournament,
        ScoreImportBatch $scoreImport,
        ScoreImportCommitService $committer
    ) {
        $this->authorizeEditorOrAdmin();
        $this->ensureBatchBelongsToTournament($scoreImport, $tournament);

        $summary = $committer->commit($tournament, $scoreImport, auth()->user());

        return redirect()
            ->route('tournaments.score_imports.show', [$tournament->id, $scoreImport->id])
            ->with('success', sprintf(
                'スコアを確定反映しました。新規: %d / 更新: %d / 要確認へ戻した行: %d',
                $summary['created'],
                $summary['updated'],
                $summary['skipped']
            ));
    }

    private function ensureBatchBelongsToTournament(ScoreImportBatch $batch, Tournament $tournament): void
    {
        abort_unless((int) $batch->tournament_id === (int) $tournament->id, 404);
    }

    private function ensureRowBelongsToBatch(ScoreImportRow $row, ScoreImportBatch $batch): void
    {
        abort_unless((int) $row->score_import_batch_id === (int) $batch->id, 404);
    }

    private function rowIssues(array $row): array
    {
        $issues = [];

        if (($row['stage'] ?? '') === null || trim((string) ($row['stage'] ?? '')) === '') {
            $issues[] = 'stage_missing';
        }

        if (($row['game_number'] ?? null) === null) {
            $issues[] = 'game_number_missing';
        }

        if (($row['score'] ?? null) === null) {
            $issues[] = 'score_missing';
        }

        if (empty($row['tournament_participant_id']) && empty($row['pro_bowler_id'])) {
            $issues[] = 'player_unmatched';
        }

        return $issues;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function isBlank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }

    private function pastedTableToCsv(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = array_values(array_filter(explode("\n", $text), fn (string $line): bool => trim($line) !== ''));

        if (empty($lines)) {
            throw new \InvalidArgumentException('貼り付けデータに取込対象行がありません。');
        }

        $handle = fopen('php://temp', 'r+');
        if (! $handle) {
            throw new \InvalidArgumentException('貼り付けデータをCSVへ変換できませんでした。');
        }

        foreach ($lines as $line) {
            $delimiter = str_contains($line, "\t") ? "\t" : ",";
            $cells = str_getcsv($line, $delimiter, '"', '');
            fputcsv($handle, $cells, ',', '"', '', "\r\n");
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function authorizeEditorOrAdmin(): void
    {
        $user = auth()->user();

        $allowed = $user && (
            (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
            (method_exists($user, 'isEditor') && $user->isEditor()) ||
            in_array((string) ($user->role ?? ''), ['editor', 'admin'], true)
        );

        abort_unless($allowed, 403);
    }
}
