<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\ScoreImportCsvStageService;
use Illuminate\Http\Request;
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
