<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportRow;
use App\Models\Tournament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScoreImportCommitService
{
    public function commit(Tournament $tournament, ScoreImportBatch $batch, ?Authenticatable $user = null): array
    {
        if ((int) $batch->tournament_id !== (int) $tournament->id) {
            throw new InvalidArgumentException('取込データと大会が一致しません。');
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($tournament, $batch, $user, &$summary): void {
            $rows = $batch->rows()
                ->whereNull('confirmed_game_score_id')
                ->whereIn('parse_status', ['parsed', 'accepted'])
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $issues = $this->commitIssues($row);
                if (! empty($issues)) {
                    $row->update([
                        'parse_status' => 'needs_review',
                        'error_message' => implode(', ', $issues),
                        'reviewed_by' => $user ? (int) $user->getAuthIdentifier() : $row->reviewed_by,
                        'reviewed_at' => now(),
                    ]);
                    $summary['skipped']++;
                    continue;
                }

                $scoreId = $this->findExistingGameScoreId($tournament, $row);
                $payload = $this->gameScorePayload($tournament, $row, $scoreId === null);

                if ($scoreId) {
                    DB::table('game_scores')->where('id', $scoreId)->update($payload);
                    $summary['updated']++;
                } else {
                    $scoreId = (int) DB::table('game_scores')->insertGetId($payload);
                    $summary['created']++;
                }

                $row->update([
                    'parse_status' => 'accepted',
                    'confirmed_game_score_id' => $scoreId,
                    'reviewed_by' => $user ? (int) $user->getAuthIdentifier() : $row->reviewed_by,
                    'reviewed_at' => now(),
                    'error_message' => null,
                ]);
            }

            $this->refreshBatchStatus($batch->fresh(), $user);
            app(ScoreImportOperationLogger::class)->log($batch->fresh(), 'commit', [
                'status' => 'success',
                'target_row_count' => $summary['created'] + $summary['updated'] + $summary['skipped'],
                'created_count' => $summary['created'],
                'updated_count' => $summary['updated'],
                'skipped_count' => $summary['skipped'],
                'payload' => $summary,
            ], $user);
        });

        return $summary;
    }

    public function refreshBatchStatus(ScoreImportBatch $batch, ?Authenticatable $user = null): void
    {
        $rowCount = $batch->rows()->count();
        $readyCount = $batch->rows()->whereIn('parse_status', ['parsed', 'accepted'])->count();
        $needsReviewCount = $batch->rows()->whereIn('parse_status', ['needs_review', 'rejected'])->count();
        $remainingActionCount = $batch->rows()
            ->whereNull('confirmed_game_score_id')
            ->whereIn('parse_status', ['parsed', 'accepted', 'needs_review'])
            ->count();

        $batch->update([
            'status' => $rowCount > 0 && $readyCount > 0 && $remainingActionCount === 0 ? 'confirmed' : ($needsReviewCount > 0 ? 'reviewing' : 'parsed'),
            'row_count' => $rowCount,
            'accepted_row_count' => $readyCount,
            'rejected_row_count' => $needsReviewCount,
            'confirmed_by' => $rowCount > 0 && $readyCount > 0 && $remainingActionCount === 0 && $user ? (int) $user->getAuthIdentifier() : $batch->confirmed_by,
            'confirmed_at' => $rowCount > 0 && $readyCount > 0 && $remainingActionCount === 0 ? ($batch->confirmed_at ?: now()) : $batch->confirmed_at,
        ]);
    }

    private function commitIssues(ScoreImportRow $row): array
    {
        $issues = [];

        if (trim((string) $row->stage) === '') {
            $issues[] = 'stage_missing';
        }

        if ($row->game_number === null) {
            $issues[] = 'game_number_missing';
        }

        if ($row->score === null) {
            $issues[] = 'score_missing';
        } elseif ((int) $row->score < 0 || (int) $row->score > 300) {
            $issues[] = 'score_out_of_range';
        }

        if (! $row->tournament_participant_id && ! $row->pro_bowler_id) {
            $issues[] = 'player_unmatched';
        }

        return $issues;
    }

    private function findExistingGameScoreId(Tournament $tournament, ScoreImportRow $row): ?int
    {
        $query = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', $row->stage)
            ->where('game_number', (int) $row->game_number);

        $shift = $this->nullableString($row->shift);
        $shift === null
            ? $query->where(function ($query) {
                $query->whereNull('shift')->orWhere('shift', '');
            })
            : $query->where('shift', $shift);

        if ($row->tournament_participant_id) {
            $query->where('tournament_participant_id', $row->tournament_participant_id);
        } else {
            $query->where('pro_bowler_id', $row->pro_bowler_id);
        }

        $id = $query->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    private function gameScorePayload(Tournament $tournament, ScoreImportRow $row, bool $forInsert): array
    {
        $payload = [
            'tournament_id' => $tournament->id,
            'stage' => trim((string) $row->stage),
            'shift' => $this->nullableString($row->shift),
            'gender' => $this->nullableString($row->gender),
            'license_number' => $this->nullableString($row->license_number),
            'name' => $this->nullableString($row->name),
            'entry_number' => $this->nullableString($row->entry_number),
            'game_number' => (int) $row->game_number,
            'score' => (int) $row->score,
            'pro_bowler_id' => $row->pro_bowler_id,
            'tournament_participant_id' => $row->tournament_participant_id,
            'updated_at' => now(),
        ];

        if ($forInsert) {
            $payload['created_at'] = now();
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
