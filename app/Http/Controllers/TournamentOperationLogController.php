<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentOperationLogController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $this->authorizeEditorOrAdmin();

        $reminderKind = (string) $request->query('reminder_kind', '');
        $reminderStatus = (string) $request->query('reminder_status', '');
        $autoTarget = (string) $request->query('auto_target', '');

        $reminderBase = DB::table('tournament_draw_reminder_logs')
            ->where('tournament_id', $tournament->id);

        $autoDrawBase = DB::table('tournament_auto_draw_logs')
            ->where('tournament_id', $tournament->id);

        $reminderSummary = [
            'total' => (clone $reminderBase)->count(),
            'manual_count' => (clone $reminderBase)->where('reminder_kind', 'manual')->count(),
            'auto_count' => (clone $reminderBase)->where('reminder_kind', 'auto')->count(),
            'sent_count' => (clone $reminderBase)->where('status', 'sent')->count(),
            'failed_count' => (clone $reminderBase)->where('status', 'failed')->count(),
        ];

        $autoDrawSummary = [
            'total_runs' => (clone $autoDrawBase)->count(),
            'shift_runs' => (clone $autoDrawBase)->where('target_type', 'shift')->count(),
            'lane_runs' => (clone $autoDrawBase)->where('target_type', 'lane')->count(),
            'pending_total' => (int) ((clone $autoDrawBase)->sum('total_pending') ?? 0),
            'success_total' => (int) ((clone $autoDrawBase)->sum('success_count') ?? 0),
            'failed_total' => (int) ((clone $autoDrawBase)->sum('failed_count') ?? 0),
        ];

        $reminderLogs = (clone $reminderBase)
            ->when($reminderKind !== '', function ($query) use ($reminderKind) {
                $query->where('reminder_kind', $reminderKind);
            })
            ->when($reminderStatus !== '', function ($query) use ($reminderStatus) {
                $query->where('status', $reminderStatus);
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'reminder_page')
            ->withQueryString();

        $autoDrawLogs = (clone $autoDrawBase)
            ->when($autoTarget !== '', function ($query) use ($autoTarget) {
                $query->where('target_type', $autoTarget);
            })
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'auto_draw_page')
            ->withQueryString();

        return view('tournament_entries.operation_logs', compact(
            'tournament',
            'reminderKind',
            'reminderStatus',
            'autoTarget',
            'reminderSummary',
            'autoDrawSummary',
            'reminderLogs',
            'autoDrawLogs',
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