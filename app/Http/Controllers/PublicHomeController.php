<?php

namespace App\Http\Controllers;

use App\Models\Information;
use App\Models\Tournament;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();

        $tournaments = $this->publicTournamentQuery()
            ->where(function ($query) use ($today) {
                $query->whereDate('start_date', '>=', $today)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->orderBy('start_date')
            ->limit(8)
            ->get();

        if ($tournaments->isEmpty()) {
            $tournaments = $this->publicTournamentQuery()
                ->orderByDesc('start_date')
                ->limit(8)
                ->get()
                ->sortBy('start_date')
                ->values();
        }

        $informations = Information::query()
            ->active()
            ->public()
            ->withCount(['files' => function ($query) {
                $query->where('visibility', 'public');
            }])
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('public.home', [
            'tournaments' => $tournaments,
            'informations' => $informations,
            'publicConfig' => config('jpba_public', []),
        ]);
    }

    private function publicTournamentQuery()
    {
        return Tournament::query()
            ->with(['files' => function ($query) {
                $query->where('visibility', 'public')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->whereNotNull('start_date');
    }
}
