<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use App\Services\TournamentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentTemplateController extends Controller
{
    public function index()
    {
        $templates = TournamentTemplate::query()
            ->with(['series', 'latestPublishedVersion'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('tournament_templates.index', compact('templates'));
    }

    public function create(Request $request)
    {
        return view('tournament_templates.create', [
            'templates' => TournamentTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'seriesList' => TournamentSeries::query()->where('is_active', true)->orderBy('name')->get(),
            'tournaments' => Tournament::query()->orderByDesc('year')->orderBy('name')->get(),
            'selectedTournamentId' => $request->integer('source_tournament_id') ?: null,
        ]);
    }

    public function store(Request $request, TournamentTemplateService $templateService)
    {
        $validated = $request->validate([
            'source_tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'tournament_template_id' => ['nullable', 'integer', 'exists:tournament_templates,id'],
            'tournament_series_id' => ['nullable', 'integer', 'exists:tournament_series,id'],
            'new_series_name' => ['nullable', 'string', 'max:255'],
            'recurrence_type' => ['nullable', Rule::in(['annual', 'seasonal', 'irregular', 'one_off'])],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'change_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $series = null;
        if (! empty($validated['new_series_name'])) {
            $series = TournamentSeries::query()->firstOrCreate(
                ['name' => trim($validated['new_series_name'])],
                [
                    'recurrence_type' => $validated['recurrence_type'] ?? 'annual',
                    'is_active' => true,
                ]
            );
        } elseif (! empty($validated['tournament_series_id'])) {
            $series = TournamentSeries::query()->findOrFail($validated['tournament_series_id']);
        }

        $source = Tournament::query()->findOrFail($validated['source_tournament_id']);
        $template = ! empty($validated['tournament_template_id'])
            ? TournamentTemplate::query()->findOrFail($validated['tournament_template_id'])
            : null;

        $version = $templateService->createVersion(
            source: $source,
            name: $validated['name'],
            series: $series,
            template: $template,
            code: $validated['code'] ?? null,
            description: $validated['description'] ?? null,
            changeNote: $validated['change_note'] ?? null,
        );

        return redirect()
            ->route('tournament_templates.index')
            ->with('success', "大会テンプレート v{$version->version} を保存しました。");
    }

    public function apply(
        TournamentTemplateVersion $version,
        TournamentTemplateService $templateService,
    ) {
        abort_unless($version->status === 'published', 404);

        session()->flash('tournament_prefill', $templateService->prefill($version));

        return redirect()
            ->route('tournaments.create')
            ->with('success', "{$version->template->name} v{$version->version} を読み込みました。日付と大会名を確認してください。");
    }
}
