<?php

namespace App\Http\Controllers;

use App\Models\TournamentArchive;
use App\Services\TournamentArchiveMetadataExtractor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTournamentArchiveController extends Controller
{
    public function index(Request $request, TournamentArchiveMetadataExtractor $metadata): View
    {
        $year = $request->integer('year');
        $classification = trim((string) $request->query('classification', ''));
        $classification = in_array($classification, ['official_tournament', 'approved_event'], true) ? $classification : '';
        $keyword = trim((string) $request->query('keyword', ''));
        $venue = trim((string) $request->query('venue', ''));
        $query = TournamentArchive::query()->publiclyVisible()->with('tournament');

        if ($year >= 2015) {
            $query->where('year', $year);
        }
        if ($classification !== '') {
            $query->where('classification', $classification);
        }
        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->where(function ($query) use ($escaped): void {
                $query->where('title', 'ilike', "%{$escaped}%")
                    ->orWhere('venue_name', 'ilike', "%{$escaped}%")
                    ->orWhere('organizer_name', 'ilike', "%{$escaped}%")
                    ->orWhere('approval_number', 'ilike', "%{$escaped}%")
                    ->orWhere('body_html', 'ilike', "%{$escaped}%");
            });
        }
        if ($venue !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $venue);
            $query->where(function ($query) use ($escaped): void {
                $query->where('venue_name', 'ilike', "%{$escaped}%")
                    ->orWhere('body_html', 'ilike', "%{$escaped}%");
            });
        }

        $archives = $query->orderByDesc('year')->orderByRaw('start_on desc nulls last')->orderByDesc('id')->paginate(20)->withQueryString();
        $archives->getCollection()->each(function (TournamentArchive $archive) use ($metadata): void {
            $archive->setAttribute('display_venue_name', $archive->venue_name ?: $archive->tournament?->venue_name ?: $metadata->venue($archive->body_html));
            $archive->setAttribute('display_organizer_name', $archive->organizer_name ?: $archive->tournament?->host ?: $metadata->organizer($archive->body_html));
        });

        return view('public.tournaments.archive.index', [
            'publicConfig' => config('jpba_public', []),
            'archives' => $archives,
            'years' => TournamentArchive::query()->publiclyVisible()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedYear' => $year >= 2015 ? $year : null,
            'selectedClassification' => $classification,
            'keyword' => $keyword,
            'venue' => $venue,
        ]);
    }

    public function show(TournamentArchive $archive, TournamentArchiveMetadataExtractor $metadata): View
    {
        abort_unless($archive->is_public, 404);

        return view('public.tournaments.archive.show', [
            'publicConfig' => config('jpba_public', []),
            'archive' => $archive,
            'assetsByType' => collect($archive->assets ?: [])->groupBy('type'),
            'venueName' => $archive->venue_name ?: $archive->tournament?->venue_name ?: $metadata->venue($archive->body_html),
            'organizerName' => $archive->organizer_name ?: $archive->tournament?->host ?: $metadata->organizer($archive->body_html),
        ]);
    }
}
