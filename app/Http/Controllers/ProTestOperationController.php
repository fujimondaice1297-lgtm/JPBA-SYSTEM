<?php

namespace App\Http\Controllers;

use App\Models\ProTestEvent;
use App\Models\ProTestSession;
use App\Services\ProTestCandidateImportService;
use App\Services\ProTestPublicationService;
use App\Services\ProTestScoreImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProTestOperationController extends Controller
{
    public function index(): View
    {
        return view('pro_tests.index', [
            'events' => ProTestEvent::query()
                ->withCount(['sessions', 'candidates'])
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'application_start' => ['nullable', 'date'],
            'application_end' => ['nullable', 'date', 'after_or_equal:application_start'],
            'male_generation' => ['nullable', 'string', 'max:255'],
            'female_generation' => ['nullable', 'string', 'max:255'],
            'public_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $event = ProTestEvent::query()->create($validated + [
            'status' => ProTestEvent::STATUS_DRAFT,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('pro_tests.show', $event)
            ->with('success', 'プロテスト年度を作成しました。次に実施日と受験者を登録してください。');
    }

    public function show(
        ProTestEvent $proTest,
        ProTestPublicationService $publicationService,
        ProTestCandidateImportService $candidateImportService,
    ): View {
        $proTest->load([
            'sessions' => fn ($query) => $query
                ->withCount('scores')
                ->with('latestPublication'),
            'candidates.proBowler',
            'candidates.previousCandidate.event',
            'candidates.stageResults',
            'latestFinalResultPublication',
        ]);

        return view('pro_tests.show', [
            'event' => $proTest,
            'maleCandidates' => $proTest->candidates->where('gender', 'M')->values(),
            'femaleCandidates' => $proTest->candidates->where('gender', 'F')->values(),
            'eligiblePreviousCandidates' => $candidateImportService->eligiblePreviousSecondFailures($proTest),
            'sessionPreviews' => $proTest->sessions->mapWithKeys(
                fn (ProTestSession $session): array => [$session->id => $publicationService->preview($session)]
            ),
        ]);
    }

    public function update(Request $request, ProTestEvent $proTest): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'application_start' => ['nullable', 'date'],
            'application_end' => ['nullable', 'date', 'after_or_equal:application_start'],
            'male_generation' => ['nullable', 'string', 'max:255'],
            'female_generation' => ['nullable', 'string', 'max:255'],
            'public_summary' => ['nullable', 'string', 'max:5000'],
        ]);
        $proTest->update($validated + ['updated_by' => $request->user()?->id]);

        return back()->with('success', '基本情報を更新しました。');
    }

    public function storeSession(Request $request, ProTestEvent $proTest): RedirectResponse
    {
        $validated = $request->validate([
            'gender' => ['required', Rule::in(['M', 'F'])],
            'stage_code' => ['required', Rule::in(['first', 'second', 'third'])],
            'stage_label' => ['required', 'string', 'max:100'],
            'day_number' => ['required', 'integer', 'between:1,20'],
            'test_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'game_start' => ['required', 'integer', 'between:1,200'],
            'game_end' => ['required', 'integer', 'between:1,200', 'gte:game_start'],
            'pass_average' => ['nullable', 'numeric', 'between:0,300'],
            'is_stage_final' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,1000'],
        ]);
        $validated['is_stage_final'] = $request->boolean('is_stage_final');

        $proTest->sessions()->create($validated);

        return back()->with('success', '実施日を追加しました。');
    }

    public function importCandidates(
        Request $request,
        ProTestEvent $proTest,
        ProTestCandidateImportService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'candidate_rows' => ['required', 'string'],
        ]);
        $result = $service->importCandidates($proTest, $validated['candidate_rows'], $request->user()?->id);

        return back()->with(
            'success',
            "受験者を取り込みました（新規{$result['created']}名・更新{$result['updated']}名）。"
        );
    }

    public function importStageResults(
        Request $request,
        ProTestEvent $proTest,
        ProTestCandidateImportService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage_result_rows' => ['required', 'string'],
        ]);
        $result = $service->importStageResults(
            $proTest,
            $validated['stage_result_rows'],
            $request->user()?->id,
        );

        return back()->with('success', "段階別結果を{$result['updated']}件更新しました。");
    }

    public function importScores(
        Request $request,
        ProTestEvent $proTest,
        ProTestSession $session,
        ProTestScoreImportService $service,
    ): RedirectResponse {
        $this->ensureSession($proTest, $session);
        $validated = $request->validate([
            'score_rows' => ['required', 'string'],
        ]);
        $result = $service->import($session, $validated['score_rows']);

        return back()->with(
            'success',
            "{$session->display_name}の得点を取り込みました（{$result['players']}名・{$result['scores']}件）。"
        );
    }

    public function publishSession(
        Request $request,
        ProTestEvent $proTest,
        ProTestSession $session,
        ProTestPublicationService $service,
    ): RedirectResponse {
        $this->ensureSession($proTest, $session);
        $publication = $service->publish($session, $request->user()?->id);

        return back()->with(
            'success',
            "{$session->display_name}を速報第{$publication->revision}版として公開しました。"
        );
    }

    public function importFinalResults(
        Request $request,
        ProTestEvent $proTest,
        ProTestCandidateImportService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'final_result_rows' => ['required', 'string'],
        ]);
        $result = $service->importFinalResults($proTest, $validated['final_result_rows']);

        return back()->with(
            'success',
            "最終結果を{$result['updated']}名分更新しました（選手マスタ自動紐付け{$result['linked']}名）。"
        );
    }

    public function publishFinalResults(
        Request $request,
        ProTestEvent $proTest,
        ProTestPublicationService $service,
    ): RedirectResponse {
        $publication = $service->publishFinalResults($proTest, $request->user()?->id);

        return back()->with(
            'success',
            "合格者一覧を第{$publication->revision}版として公開しました。不合格者は公開されません。"
        );
    }

    private function ensureSession(ProTestEvent $event, ProTestSession $session): void
    {
        abort_unless($session->pro_test_event_id === $event->id, 404);
    }
}
