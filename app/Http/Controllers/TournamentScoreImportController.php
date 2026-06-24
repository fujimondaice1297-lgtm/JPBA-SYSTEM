<?php

namespace App\Http\Controllers;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportRow;
use App\Models\Tournament;
use App\Services\ScoreImportCommitService;
use App\Services\ScoreImportCsvStageService;
use App\Services\ScoreImportOperationLogger;
use Illuminate\Http\Request;
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
