<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Services\TournamentAggregateResultService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class PublicTournamentAggregateController extends Controller
{
    private const PUBLIC_STATUSES = ['in_progress', 'provisional', 'final', 'archived', 'completed'];

    public function show(
        Request $request,
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
        TournamentAggregateResultService $service,
    ) {
        $this->guard($tournament, $definition);
        $payload = $this->payload($request, $definition, $service);
        $rows = $this->paginate(collect($payload['rows']), $request);

        return view('public.tournaments.aggregate', [
            'publicConfig' => config('jpba_public', []),
            'tournament' => $tournament,
            'definition' => $definition,
            'rows' => $rows,
            'sources' => $payload['sources'],
            'mode' => $payload['mode'],
            'updatedAt' => $payload['updated_at'],
            'hasOfficialSnapshot' => $this->publishedSnapshotExists($definition),
            'groupMembers' => $this->groupMembers($tournament),
        ]);
    }

    public function pdf(
        Request $request,
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
        TournamentAggregateResultService $service,
    ) {
        $this->guard($tournament, $definition);
        $payload = $this->payload($request, $definition, $service);
        $safeName = str_replace(
            ['\\', '/', ':', '*', '?', '"', '<', '>', '|'],
            '_',
            $tournament->name.'_'.$definition->name,
        ) ?: 'japan-open-result';

        $pdf = Pdf::loadView('tournament_results.pdfs.aggregate', [
            'tournament' => $tournament,
            'definition' => $definition,
            'rows' => collect($payload['rows']),
            'sources' => $payload['sources'],
            'mode' => $payload['mode'],
            'updatedAt' => $payload['updated_at'],
            'groupMembers' => $this->groupMembers($tournament),
        ])->setPaper('a4', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('defaultFont', 'ipaexg');
        $options->set('isFontSubsettingEnabled', true);

        $fontPath = storage_path('fonts/ipaexg.ttf');
        if (is_file($fontPath)) {
            $dompdf->getFontMetrics()->registerFont([
                'family' => 'ipaexg',
                'style' => 'normal',
                'weight' => 'normal',
            ], $fontPath);
        }

        return $pdf->download($safeName.'.pdf');
    }

    /** @return array<string,mixed> */
    private function payload(
        Request $request,
        TournamentAggregateDefinition $definition,
        TournamentAggregateResultService $service,
    ): array {
        $mode = $request->query('mode') === 'official' ? 'official' : 'live';
        $snapshot = $definition->snapshots()
            ->where('is_current', true)
            ->where('is_published', true)
            ->latest('id')
            ->first();

        if ($mode === 'official') {
            abort_unless($snapshot, 404);
            $definition->loadMissing('sources');

            return [
                'mode' => 'official',
                'rows' => $snapshot->rows()->orderBy('ranking')->orderBy('id')->get()->map(
                    fn ($row): array => [
                        'ranking' => (int) $row->ranking,
                        'display_name' => (string) $row->display_name,
                        'pro_bowler_id' => $row->pro_bowler_id,
                        'pro_bowler_license_no' => $row->pro_bowler_license_no,
                        'competitor_group_id' => $row->competitor_group_id,
                        'total_pin' => (int) $row->total_pin,
                        'games' => (int) $row->games,
                        'average' => $row->average === null ? null : (float) $row->average,
                        'is_complete' => (bool) $row->is_complete,
                        'incomplete_reasons' => (array) data_get($row->breakdown, 'incomplete_reasons', []),
                        'source_breakdown' => collect((array) data_get($row->breakdown, 'sources', []))->keyBy('source_id')->all(),
                    ]
                )->all(),
                'sources' => $definition->sources->map(fn ($source): array => [
                    'id' => (int) $source->id,
                    'label' => (string) $source->label,
                ])->all(),
                'updated_at' => $snapshot->reflected_at,
            ];
        }

        try {
            $preview = $service->preview($definition);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return [
            'mode' => 'live',
            'rows' => $preview['rows'],
            'sources' => $preview['sources'],
            'updated_at' => now(),
        ];
    }

    private function guard(Tournament $tournament, TournamentAggregateDefinition $definition): void
    {
        abort_unless(in_array((string) $tournament->setup_status, self::PUBLIC_STATUSES, true), 404);
        abort_unless((int) $definition->tournament_id === (int) $tournament->id, 404);
        abort_unless($definition->is_active && $definition->is_published, 404);
    }

    private function publishedSnapshotExists(TournamentAggregateDefinition $definition): bool
    {
        return $definition->snapshots()
            ->where('is_current', true)
            ->where('is_published', true)
            ->exists();
    }

    /** @return array<int,array<int,string>> */
    private function groupMembers(Tournament $tournament): array
    {
        return $tournament->competitorGroups()
            ->with('members.participant')
            ->get()
            ->mapWithKeys(fn ($group): array => [
                (int) $group->id => $group->members->map(function ($member): string {
                    $participant = $member->participant;
                    if (! $participant) {
                        return '選手未設定';
                    }
                    $name = trim((string) $participant->display_name) ?: '選手未設定';
                    $license = $participant->pro_bowler_id
                        ? \App\Support\PublicLicenseNumber::format($participant->display_license_no ?: $participant->pro_bowler_license_no)
                        : 'アマ';

                    return $name.'（'.$license.'）';
                })->all(),
            ])->all();
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $perPage = 100;

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
