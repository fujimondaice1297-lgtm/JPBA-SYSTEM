<?php

namespace App\Http\Controllers;

use App\Models\TournamentArchive;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTournamentArchiveController extends Controller
{
    public function index(Request $request): View
    {
        $year = $request->integer('year');
        $keyword = trim((string) $request->query('keyword', ''));
        $query = TournamentArchive::query()->publiclyVisible();

        if ($year >= 2016) {
            $query->where('year', $year);
        }
        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->where('title', 'ilike', "%{$escaped}%");
        }

        return view('public.tournaments.archive.index', [
            'publicConfig' => config('jpba_public', []),
            'archives' => $query->orderByDesc('year')->orderByRaw('start_on desc nulls last')->orderByDesc('id')->paginate(20)->withQueryString(),
            'years' => TournamentArchive::query()->publiclyVisible()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedYear' => $year >= 2016 ? $year : null,
            'keyword' => $keyword,
        ]);
    }

    public function show(TournamentArchive $archive): View
    {
        abort_unless($archive->is_public, 404);

        return view('public.tournaments.archive.show', [
            'publicConfig' => config('jpba_public', []),
            'archive' => $archive,
            'assetsByType' => collect($archive->assets ?: [])->groupBy('type'),
        ]);
    }
}
