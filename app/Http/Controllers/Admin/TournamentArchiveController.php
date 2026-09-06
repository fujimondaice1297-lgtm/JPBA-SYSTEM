<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentArchive;
use App\Services\ManagedPublicPageSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentArchiveController extends Controller
{
    public function index(): View
    {
        return view('admin.tournament_archives.index', [
            'archives' => TournamentArchive::query()->orderByDesc('year')->orderByRaw('start_on desc nulls last')->paginate(40),
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
            'start_on' => ['nullable', 'date'],
            'end_on' => ['nullable', 'date', 'after_or_equal:start_on'],
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
