<?php

namespace App\Services;

use App\Models\ProTestCandidate;
use App\Models\ProTestScore;
use App\Models\ProTestSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProTestScoreImportService
{
    /** @return array{players:int,scores:int} */
    public function import(ProTestSession $session, string $input): array
    {
        $lines = collect(preg_split('/\R/u', trim($input)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'score_rows' => 'スコアを1行以上入力してください。',
            ]);
        }

        $players = 0;
        $scores = 0;

        DB::transaction(function () use ($session, $lines, &$players, &$scores): void {
            foreach ($lines as $lineIndex => $line) {
                $columns = $this->columns($line);
                $examNumber = trim((string) array_shift($columns));
                $candidate = ProTestCandidate::query()
                    ->where('pro_test_event_id', $session->pro_test_event_id)
                    ->where('gender', $session->gender)
                    ->where('exam_number', $examNumber)
                    ->first();

                if (! $candidate) {
                    throw ValidationException::withMessages([
                        'score_rows' => ($lineIndex + 1)."行目の受験番号 {$examNumber} は対象者にありません。",
                    ]);
                }

                if ($this->stageRank($session->stage_code) < $this->stageRank($candidate->entry_stage)) {
                    throw ValidationException::withMessages([
                        'score_rows' => ($lineIndex + 1)."行目の {$candidate->name} は{$candidate->entry_stage_label}の受験者です。{$session->stage_label}には入力できません。",
                    ]);
                }

                if (count($columns) > $session->game_count) {
                    throw ValidationException::withMessages([
                        'score_rows' => ($lineIndex + 1)."行目は最大 {$session->game_count} ゲームです。",
                    ]);
                }

                $savedForPlayer = 0;
                foreach (range(0, $session->game_count - 1) as $offset) {
                    $raw = trim((string) ($columns[$offset] ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    if ($raw === '-') {
                        ProTestScore::query()
                            ->where('pro_test_session_id', $session->id)
                            ->where('pro_test_candidate_id', $candidate->id)
                            ->where('game_number', $session->game_start + $offset)
                            ->delete();
                        $savedForPlayer++;

                        continue;
                    }
                    if (! ctype_digit($raw) || (int) $raw < 0 || (int) $raw > 300) {
                        throw ValidationException::withMessages([
                            'score_rows' => ($lineIndex + 1).'行目に0〜300以外のスコアがあります。',
                        ]);
                    }

                    ProTestScore::query()->updateOrCreate([
                        'pro_test_session_id' => $session->id,
                        'pro_test_candidate_id' => $candidate->id,
                        'game_number' => $session->game_start + $offset,
                    ], [
                        'score' => (int) $raw,
                    ]);
                    $savedForPlayer++;
                    $scores++;
                }

                if ($savedForPlayer > 0) {
                    $players++;
                }
            }
        });

        return compact('players', 'scores');
    }

    /** @return array<int,string> */
    private function columns(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        if (str_contains($line, ',')) {
            return array_map('trim', str_getcsv($line, ',', '"', ''));
        }

        return preg_split('/\s+/u', trim($line)) ?: [];
    }

    private function stageRank(string $stage): int
    {
        return match ($stage) {
            'first' => 1,
            'second' => 2,
            'third' => 3,
            default => 0,
        };
    }
}
