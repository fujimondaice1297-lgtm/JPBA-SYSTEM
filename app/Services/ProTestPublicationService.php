<?php

namespace App\Services;

use App\Models\ProTestCandidate;
use App\Models\ProTestEvent;
use App\Models\ProTestFinalResultPublication;
use App\Models\ProTestResultPublication;
use App\Models\ProTestScore;
use App\Models\ProTestSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProTestPublicationService
{
    public function publish(ProTestSession $session, ?int $userId): ProTestResultPublication
    {
        return DB::transaction(function () use ($session, $userId): ProTestResultPublication {
            $session = ProTestSession::query()->lockForUpdate()->findOrFail($session->id);
            $session->load('event');
            $rows = $this->preview($session);
            if ($rows === []) {
                throw ValidationException::withMessages([
                    'publication' => '公開できるスコアがありません。先に得点を入力してください。',
                ]);
            }
            $previous = ProTestResultPublication::query()
                ->where('pro_test_session_id', $session->id)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $publication = ProTestResultPublication::query()->create([
                'pro_test_session_id' => $session->id,
                'revision' => ((int) ($previous?->revision ?? 0)) + 1,
                'row_count' => count($rows),
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            foreach ($rows as $row) {
                $publication->rows()->create($row);
            }

            $this->syncStageResults($session, $rows, $publication->revision, $userId);

            $session->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            if ($session->event->status === ProTestEvent::STATUS_DRAFT) {
                $session->event->update([
                    'status' => ProTestEvent::STATUS_LIVE,
                    'updated_by' => $userId,
                ]);
            }

            return $publication;
        });
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function syncStageResults(
        ProTestSession $session,
        array $rows,
        int $revision,
        ?int $userId,
    ): void {
        if (! $session->is_stage_final || $session->pass_average === null) {
            return;
        }

        $expectedGames = $this->expectedGameCount($session);

        foreach ($rows as $row) {
            if ((int) $row['games'] < $expectedGames) {
                continue;
            }

            ProTestCandidate::query()->findOrFail($row['pro_test_candidate_id'])
                ->stageResults()
                ->updateOrCreate(['stage_code' => $session->stage_code], [
                    'result' => (float) $row['average'] >= (float) $session->pass_average
                        ? 'passed'
                        : 'not_passed',
                    'note' => "{$session->display_name} 速報公開第{$revision}版から自動判定",
                    'decided_by' => $userId,
                    'decided_at' => now(),
                ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function preview(ProTestSession $session): array
    {
        $stageSessionIds = ProTestSession::query()
            ->where('pro_test_event_id', $session->pro_test_event_id)
            ->where('gender', $session->gender)
            ->where('stage_code', $session->stage_code)
            ->where('sort_order', '<=', $session->sort_order)
            ->pluck('id');
        $scores = ProTestScore::query()
            ->whereIn('pro_test_session_id', $stageSessionIds)
            ->orderBy('game_number')
            ->get()
            ->groupBy('pro_test_candidate_id');
        $candidates = ProTestCandidate::query()
            ->where('pro_test_event_id', $session->pro_test_event_id)
            ->where('gender', $session->gender)
            ->whereIn('id', $scores->keys())
            ->get();

        return $candidates->isEmpty()
            ? []
            : $this->rankedRows($session, $candidates, $scores, $this->expectedGameCount($session));
    }

    public function publishFinalResults(ProTestEvent $event, ?int $userId): ProTestFinalResultPublication
    {
        return DB::transaction(function () use ($event, $userId): ProTestFinalResultPublication {
            $event = ProTestEvent::query()->lockForUpdate()->findOrFail($event->id);
            $candidates = $event->candidates()
                ->where('final_result', 'passed')
                ->orderBy('gender')
                ->orderBy('license_no')
                ->orderBy('exam_number')
                ->get();

            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages([
                    'final_publication' => '合格者が登録されていないため、最終結果を公開できません。',
                ]);
            }

            $previous = ProTestFinalResultPublication::query()
                ->where('pro_test_event_id', $event->id)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();
            $publication = ProTestFinalResultPublication::query()->create([
                'pro_test_event_id' => $event->id,
                'revision' => ((int) ($previous?->revision ?? 0)) + 1,
                'row_count' => $candidates->count(),
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            foreach ($candidates as $candidate) {
                $publication->rows()->create([
                    'pro_test_candidate_id' => $candidate->id,
                    'gender' => $candidate->gender,
                    'exam_number' => $candidate->exam_number,
                    'license_no' => $candidate->license_no,
                    'name' => $candidate->name,
                    'name_kana' => $candidate->name_kana,
                    'pro_bowler_id' => $candidate->pro_bowler_id,
                ]);
            }

            $event->update([
                'status' => ProTestEvent::STATUS_FINAL,
                'final_results_published_at' => now(),
                'updated_by' => $userId,
            ]);

            return $publication;
        });
    }

    /**
     * @param  Collection<int,ProTestCandidate>  $candidates
     * @param  Collection<int,Collection<int,ProTestScore>>  $scores
     * @return array<int,array<string,mixed>>
     */
    private function rankedRows(
        ProTestSession $session,
        Collection $candidates,
        Collection $scores,
        int $expectedGames,
    ): array {
        $rows = $candidates->map(function (ProTestCandidate $candidate) use ($session, $scores, $expectedGames): array {
            $candidateScores = $scores->get($candidate->id, collect());
            $games = $candidateScores->count();
            $total = (int) $candidateScores->sum('score');
            $average = $games > 0 ? round($total / $games, 2) : 0;
            $sessionScores = $candidateScores
                ->where('pro_test_session_id', $session->id)
                ->sortBy('game_number')
                ->mapWithKeys(fn (ProTestScore $score): array => [(string) $score->game_number => $score->score])
                ->all();

            $resultLabel = null;
            if ($session->is_stage_final && $session->pass_average !== null && $games >= $expectedGames) {
                $resultLabel = $average >= (float) $session->pass_average
                    ? $session->stage_label.'合格'
                    : '不合格';
            }

            return [
                'pro_test_candidate_id' => $candidate->id,
                'exam_number' => $candidate->exam_number,
                'name' => $candidate->name,
                'name_kana' => $candidate->name_kana,
                'resident_prefecture' => $candidate->resident_prefecture,
                'handedness' => $candidate->handedness,
                'games' => $games,
                'total_pin' => $total,
                'average' => $average,
                'result_label' => $resultLabel,
                'session_scores' => $sessionScores,
            ];
        })->sort(function (array $left, array $right): int {
            return $right['total_pin'] <=> $left['total_pin']
                ?: strnatcasecmp($left['exam_number'], $right['exam_number']);
        })->values();

        $rank = 0;
        $previousTotal = null;

        return $rows->map(function (array $row, int $index) use (&$rank, &$previousTotal): array {
            if ($previousTotal === null || $row['total_pin'] !== $previousTotal) {
                $rank = $index + 1;
            }
            $previousTotal = $row['total_pin'];

            return ['rank' => $rank] + $row;
        })->all();
    }

    private function expectedGameCount(ProTestSession $session): int
    {
        return ProTestSession::query()
            ->where('pro_test_event_id', $session->pro_test_event_id)
            ->where('gender', $session->gender)
            ->where('stage_code', $session->stage_code)
            ->where('sort_order', '<=', $session->sort_order)
            ->get(['game_start', 'game_end'])
            ->flatMap(fn (ProTestSession $item) => range($item->game_start, $item->game_end))
            ->unique()
            ->count();
    }
}
