<?php

namespace App\Http\Controllers;

use App\Models\ProTestEvent;
use App\Models\ProTestSession;
use Illuminate\View\View;

class PublicProTestResultController extends Controller
{
    public function show(ProTestEvent $proTest): View
    {
        abort_unless($this->isVisible($proTest), 404);

        $proTest->load([
            'sessions' => fn ($query) => $query
                ->whereNotNull('published_at')
                ->with('latestPublication')
                ->orderBy('sort_order')
                ->orderBy('id'),
            'latestFinalResultPublication.rows.proBowler',
        ]);

        return view('public.pro_tests.show', [
            'event' => $proTest,
            'passedCandidates' => $proTest->latestFinalResultPublication?->rows ?? collect(),
            'finalPublication' => $proTest->latestFinalResultPublication,
        ]);
    }

    public function session(ProTestEvent $proTest, ProTestSession $session): View
    {
        abort_unless($session->pro_test_event_id === $proTest->id && $this->isVisible($proTest), 404);
        $publication = $session->publications()->with('rows')->first();
        abort_unless($publication && $session->published_at, 404);

        return view('public.pro_tests.session', [
            'event' => $proTest,
            'session' => $session,
            'publication' => $publication,
            'gameNumbers' => range($session->game_start, $session->game_end),
        ]);
    }

    private function isVisible(ProTestEvent $event): bool
    {
        return $event->final_results_published_at !== null
            || $event->sessions()->whereNotNull('published_at')->exists();
    }
}
