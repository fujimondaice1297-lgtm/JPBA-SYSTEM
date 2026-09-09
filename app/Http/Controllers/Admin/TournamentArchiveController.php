<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentArchive;
use App\Services\ManagedPublicPageSanitizer;
use App\Services\TournamentArchiveMetadataExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentArchiveController extends Controller
{
    public function index(Request $request, TournamentArchiveMetadataExtractor $metadata): View
    {
        $classification = trim((string) $request->query('classification', ''));
        $classification = in_array($classification, ['official_tournament', 'approved_event'], true) ? $classification : '';
        $keyword = trim((string) $request->query('keyword', ''));
        $year = $request->integer('year');
        $query = TournamentArchive::query()->with('tournament');

        if ($classification !== '') {
            $query->where('classification', $classification);
        }
        if ($year >= 2015) {
            $query->where('year', $year);
        }
        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->where(function ($query) use ($escaped): void {
                $query->where('title', 'ilike', "%{$escaped}%")
                    ->orWhere('venue_name', 'ilike', "%{$escaped}%")
                    ->orWhere('organizer_name', 'ilike', "%{$escaped}%")
                    ->orWhere('approval_number', 'ilike', "%{$escaped}%");
            });
        }

        $archives = $query->orderByDesc('year')->orderByRaw('start_on desc nulls last')->paginate(40)->withQueryString();
        $archives->getCollection()->each(function (TournamentArchive $archive) use ($metadata): void {
            $archive->setAttribute('display_venue_name', $archive->venue_name ?: $archive->tournament?->venue_name ?: $metadata->venue($archive->body_html));
        });

        return view('admin.tournament_archives.index', [
            'archives' => $archives,
            'years' => TournamentArchive::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedClassification' => $classification,
            'selectedYear' => $year >= 2015 ? $year : null,
            'keyword' => $keyword,
        ]);
    }

    public function edit(TournamentArchive $archive): View
    {
        return view('admin.tournament_archives.edit', compact('archive'));
    }

    public function update(Request $request, TournamentArchive $archive, ManagedPublicPageSanitizer $sanitizer): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'classification' => ['required', 'in:official_tournament,approved_event'],
            'start_on' => ['nullable', 'date'],
            'end_on' => ['nullable', 'date', 'after_or_equal:start_on'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'approval_number' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:completed,scheduled,cancelled,postponed'],
            'body_html' => ['required', 'string', 'max:1000000'],
            'is_public' => ['nullable', 'boolean'],
        ]);
        $data['body_html'] = $sanitizer->sanitize($data['body_html']);
        $data['is_public'] = $request->boolean('is_public');
        $archive->update($data);

        return back()->with('success', '大会アーカイブを更新しました。次回同期でも手修正は保護されます。');
    }
}
