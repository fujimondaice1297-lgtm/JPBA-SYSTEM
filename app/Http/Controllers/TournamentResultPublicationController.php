<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Services\TournamentResultPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class TournamentResultPublicationController extends Controller
{
    public function index(
        Request $request,
        Tournament $tournament,
        TournamentResultPublicationService $service,
    ): View {
        $finalSnapshots = TournamentResultSnapshot::query()
            ->where('tournament_id', $tournament->id)
            ->where('is_final', true)
            ->withCount('rows')
            ->orderByDesc('is_current')
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->get();

        $selectedSnapshotId = (int) $request->query('snapshot_id', 0);
        $selectedSnapshot = $finalSnapshots->first(
            fn (TournamentResultSnapshot $snapshot): bool => (int) $snapshot->id === $selectedSnapshotId,
        ) ?? $finalSnapshots->first();

        $preview = $selectedSnapshot !== null
            ? $service->preview($tournament, $selectedSnapshot)
            : null;

        $publications = TournamentResultPublication::query()
            ->with(['snapshot', 'publishedBy'])
            ->where('tournament_id', $tournament->id)
            ->orderByDesc('revision')
            ->get();

        return view('tournament_result_publications.index', [
            'tournament' => $tournament,
            'finalSnapshots' => $finalSnapshots,
            'selectedSnapshot' => $selectedSnapshot,
            'preview' => $preview,
            'publications' => $publications,
            'currentPublication' => $service->currentPublication($tournament),
        ]);
    }

    public function publish(
        Request $request,
        Tournament $tournament,
        TournamentResultPublicationService $service,
    ): RedirectResponse {
        abort_unless(auth()->user()?->isAdmin(), 403, '公式結果の確定は管理者だけが実行できます。');

        $data = $request->validate([
            'snapshot_id' => ['required', 'integer'],
            'expected_checksum' => ['required', 'string', 'size:64'],
            'confirm_publish' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $snapshot = TournamentResultSnapshot::query()
            ->where('id', (int) $data['snapshot_id'])
            ->where('tournament_id', $tournament->id)
            ->firstOrFail();

        try {
            $publication = $service->publish(
                $tournament,
                $snapshot,
                (int) auth()->id(),
                (string) $data['expected_checksum'],
                $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['publication' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('tournaments.result_publications.index', [
                'tournament' => $tournament->id,
                'snapshot_id' => $snapshot->id,
            ])
            ->with('success', sprintf(
                '公式結果 第%d版を確定しました。成績%d件、合計ポイント%sP、賞金総額¥%sを公開PDFへ反映しました。',
                $publication->revision,
                $publication->row_count,
                number_format($publication->total_points),
                number_format($publication->total_prize_money),
            ));
    }
}
