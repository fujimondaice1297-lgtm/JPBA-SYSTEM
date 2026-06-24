<?php

namespace App\Services;

use App\Models\ScoreImportBatch;
use App\Models\ScoreImportOperationLog;
use Illuminate\Contracts\Auth\Authenticatable;

class ScoreImportOperationLogger
{
    public function log(ScoreImportBatch $batch, string $action, array $attributes = [], ?Authenticatable $user = null): ScoreImportOperationLog
    {
        return ScoreImportOperationLog::create([
            'tournament_id' => $batch->tournament_id,
            'score_import_batch_id' => $batch->id,
            'action' => $action,
            'status' => $attributes['status'] ?? 'success',
            'actor_user_id' => $user ? (int) $user->getAuthIdentifier() : ($attributes['actor_user_id'] ?? null),
            'target_row_count' => (int) ($attributes['target_row_count'] ?? 0),
            'created_count' => (int) ($attributes['created_count'] ?? 0),
            'updated_count' => (int) ($attributes['updated_count'] ?? 0),
            'skipped_count' => (int) ($attributes['skipped_count'] ?? 0),
            'message' => $attributes['message'] ?? null,
            'payload' => $attributes['payload'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }
}
