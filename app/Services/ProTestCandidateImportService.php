<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProTestCandidate;
use App\Models\ProTestEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProTestCandidateImportService
{
    /** @return array{created:int,updated:int} */
    public function importCandidates(ProTestEvent $event, string $input, ?int $userId = null): array
    {
        $created = 0;
        $updated = 0;
        $rows = $this->rows($input);

        if ($rows === []) {
            throw ValidationException::withMessages(['candidate_rows' => '受験者データを1行以上入力してください。']);
        }

        DB::transaction(function () use ($event, $rows, $userId, &$created, &$updated): void {
            foreach ($rows as $lineNumber => $columns) {
                $examNumber = trim((string) ($columns[0] ?? ''));
                $gender = $this->gender((string) ($columns[1] ?? ''));
                $name = trim((string) ($columns[2] ?? ''));
                $entryStage = $this->stageCode((string) ($columns[6] ?? 'first'));
                $entryReason = $this->entryReason((string) ($columns[7] ?? 'regular'));
                $previousExamNumber = $this->nullable($columns[8] ?? null);

                if ($examNumber === '' || $gender === null || $name === '' || $entryStage === null || $entryReason === null) {
                    throw ValidationException::withMessages([
                        'candidate_rows' => ($lineNumber + 1).'行目の受験番号、性別、氏名、開始段階、免除理由を確認してください。',
                    ]);
                }

                $this->validateEntryRule($entryStage, $entryReason, $lineNumber);
                $exemptionNote = $this->nullable($columns[9] ?? null);
                if (in_array($entryReason, [
                    'approved_amateur_performance',
                    'amateur_pro_event_champion',
                    'other',
                ], true) && $exemptionNote === null) {
                    throw ValidationException::withMessages([
                        'candidate_rows' => ($lineNumber + 1).'行目の協会承認・特例の根拠を承認メモへ入力してください。',
                    ]);
                }
                $candidate = ProTestCandidate::query()->firstOrNew([
                    'pro_test_event_id' => $event->id,
                    'exam_number' => $examNumber,
                ]);
                $previousCandidate = $entryReason === 'previous_year_second_fail'
                    ? $this->eligiblePreviousCandidate(
                        $event,
                        $previousExamNumber,
                        $gender,
                        $lineNumber,
                        $candidate->exists ? $candidate->id : null,
                    )
                    : null;
                $candidate->fill([
                    'gender' => $gender,
                    'name' => $name,
                    'name_kana' => $this->nullable($columns[3] ?? null),
                    'resident_prefecture' => $this->nullable($columns[4] ?? null),
                    'handedness' => $this->nullable($columns[5] ?? null),
                    'entry_stage' => $entryStage,
                    'entry_reason' => $entryReason,
                    'previous_candidate_id' => $previousCandidate?->id,
                    'exemption_approved_at' => $entryStage === 'first' ? null : now(),
                    'exemption_approved_by' => $entryStage === 'first' ? null : $userId,
                    'exemption_note' => $exemptionNote,
                ]);
                $candidate->exists ? $updated++ : $created++;
                $candidate->save();
                $this->syncExemptStages($candidate, $userId);
            }
        });

        return compact('created', 'updated');
    }

    /** @return array{updated:int} */
    public function importStageResults(ProTestEvent $event, string $input, ?int $userId = null): array
    {
        $rows = $this->rows($input);
        $updated = 0;

        if ($rows === []) {
            throw ValidationException::withMessages(['stage_result_rows' => '段階別結果を1行以上入力してください。']);
        }

        DB::transaction(function () use ($event, $rows, $userId, &$updated): void {
            foreach ($rows as $lineNumber => $columns) {
                $examNumber = trim((string) ($columns[0] ?? ''));
                $stageCode = $this->stageCode((string) ($columns[1] ?? ''));
                $result = $this->stageResult((string) ($columns[2] ?? ''));
                $candidate = $event->candidates()->where('exam_number', $examNumber)->first();

                if (! $candidate || $stageCode === null || $result === null) {
                    throw ValidationException::withMessages([
                        'stage_result_rows' => ($lineNumber + 1).'行目の受験番号、段階、結果を確認してください。',
                    ]);
                }

                $stageRank = $this->stageRank($stageCode);
                $entryRank = $this->stageRank($candidate->entry_stage);
                if (($stageRank < $entryRank && $result !== 'exempt')
                    || ($stageRank >= $entryRank && $result === 'exempt')) {
                    throw ValidationException::withMessages([
                        'stage_result_rows' => ($lineNumber + 1).'行目の開始段階と結果区分が一致しません。免除は開始段階より前だけに設定できます。',
                    ]);
                }

                $candidate->stageResults()->updateOrCreate(['stage_code' => $stageCode], [
                    'result' => $result,
                    'note' => $this->nullable($columns[3] ?? null),
                    'decided_by' => $userId,
                    'decided_at' => now(),
                ]);
                $updated++;
            }
        });

        return compact('updated');
    }

    /** @return array{updated:int,linked:int} */
    public function importFinalResults(ProTestEvent $event, string $input): array
    {
        $updated = 0;
        $linked = 0;
        $rows = $this->rows($input);
        $allowed = ['pending', 'passed', 'not_passed', 'withdrawn'];

        if ($rows === []) {
            throw ValidationException::withMessages(['final_result_rows' => '最終結果を1行以上入力してください。']);
        }

        DB::transaction(function () use ($event, $rows, $allowed, &$updated, &$linked): void {
            foreach ($rows as $lineNumber => $columns) {
                $examNumber = trim((string) ($columns[0] ?? ''));
                $result = $this->resultCode((string) ($columns[1] ?? ''));
                $licenseNo = $this->nullable($columns[2] ?? null);

                if ($examNumber === '' || ! in_array($result, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'final_result_rows' => ($lineNumber + 1).'行目の受験番号または結果区分を確認してください。',
                    ]);
                }

                $candidate = $event->candidates()->where('exam_number', $examNumber)->first();
                if (! $candidate) {
                    throw ValidationException::withMessages([
                        'final_result_rows' => ($lineNumber + 1)."行目の受験番号 {$examNumber} は登録されていません。",
                    ]);
                }

                $proBowler = $licenseNo ? ProBowler::query()->where('license_no', $licenseNo)->first() : null;
                $candidate->update([
                    'final_result' => $result,
                    'license_no' => $licenseNo,
                    'pro_bowler_id' => $proBowler?->id,
                ]);
                $updated++;
                $linked += $proBowler ? 1 : 0;
            }
        });

        return compact('updated', 'linked');
    }

    /** @return Collection<int,ProTestCandidate> */
    public function eligiblePreviousSecondFailures(ProTestEvent $event): Collection
    {
        $alreadyLinked = $event->candidates()->whereNotNull('previous_candidate_id')->pluck('previous_candidate_id');

        return ProTestCandidate::query()
            ->with('event')
            ->whereHas('event', fn ($query) => $query->where('year', $event->year - 1))
            ->where('entry_reason', '!=', 'previous_year_second_fail')
            ->whereHas('stageResults', fn ($query) => $query
                ->where('stage_code', 'second')
                ->where('result', 'not_passed'))
            ->whereNotIn('id', $alreadyLinked)
            ->orderBy('gender')
            ->orderBy('exam_number')
            ->get();
    }

    /** @return array<int,array<int,string>> */
    private function rows(string $input): array
    {
        return collect(preg_split('/\R/u', trim($input)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(function (string $line): array {
                if (str_contains($line, "\t")) {
                    return array_map('trim', explode("\t", $line));
                }

                return array_map('trim', str_getcsv($line, ',', '"', ''));
            })
            ->values()
            ->all();
    }

    private function gender(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'm', 'male', '男', '男子' => 'M',
            'f', 'female', '女', '女子' => 'F',
            default => null,
        };
    }

    private function resultCode(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            'passed', 'pass', '合格' => 'passed',
            'not_passed', 'failed', 'fail', '不合格' => 'not_passed',
            'withdrawn', 'withdraw', '棄権' => 'withdrawn',
            'pending', '未確定' => 'pending',
            default => '',
        };
    }

    private function stageCode(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'first', '1', '1次', '第1次', '第1次テスト' => 'first',
            'second', '2', '2次', '第2次', '第2次テスト' => 'second',
            'third', '3', '3次', '第3次', '第3次テスト' => 'third',
            default => null,
        };
    }

    private function entryReason(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            '', 'regular', '通常', '通常受験' => 'regular',
            'previous_year_second_fail', '前年2次不合格', '前年第2次不合格' => 'previous_year_second_fail',
            'approved_amateur_performance', 'アマ好成績', 'アマチュア好成績' => 'approved_amateur_performance',
            'amateur_pro_event_champion', 'アマ優勝', 'プロ公式戦アマ優勝' => 'amateur_pro_event_champion',
            'other', 'その他', 'その他特例' => 'other',
            default => null,
        };
    }

    private function stageResult(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'pending', '未確定' => 'pending',
            'passed', 'pass', '合格' => 'passed',
            'not_passed', 'failed', 'fail', '不合格' => 'not_passed',
            'withdrawn', 'withdraw', '棄権' => 'withdrawn',
            'exempt', '免除' => 'exempt',
            default => null,
        };
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

    private function validateEntryRule(string $stage, string $reason, int $lineNumber): void
    {
        $valid = match ($stage) {
            'first' => $reason === 'regular',
            'second' => in_array($reason, ['previous_year_second_fail', 'approved_amateur_performance', 'other'], true),
            'third' => in_array($reason, ['amateur_pro_event_champion', 'other'], true),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'candidate_rows' => ($lineNumber + 1).'行目の開始段階と免除理由の組み合わせが不正です。',
            ]);
        }
    }

    private function eligiblePreviousCandidate(
        ProTestEvent $event,
        ?string $previousExamNumber,
        string $gender,
        int $lineNumber,
        ?int $currentCandidateId,
    ): ProTestCandidate {
        $candidate = ProTestCandidate::query()
            ->where('exam_number', $previousExamNumber)
            ->where('gender', $gender)
            ->whereHas('event', fn ($query) => $query->where('year', $event->year - 1))
            ->where('entry_reason', '!=', 'previous_year_second_fail')
            ->whereHas('stageResults', fn ($query) => $query
                ->where('stage_code', 'second')
                ->where('result', 'not_passed'))
            ->first();

        if (! $candidate) {
            throw ValidationException::withMessages([
                'candidate_rows' => ($lineNumber + 1).'行目の前年受験番号は、1回限りの第1次免除条件を満たしません。',
            ]);
        }

        $alreadyUsed = ProTestCandidate::query()
            ->where('pro_test_event_id', $event->id)
            ->where('previous_candidate_id', $candidate->id)
            ->when($currentCandidateId, fn ($query) => $query->whereKeyNot($currentCandidateId))
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'candidate_rows' => ($lineNumber + 1).'行目の前年受験番号は、すでに翌年度の受験者へ紐付いています。',
            ]);
        }

        return $candidate;
    }

    private function syncExemptStages(ProTestCandidate $candidate, ?int $userId): void
    {
        $exemptStages = match ($candidate->entry_stage) {
            'second' => ['first'],
            'third' => ['first', 'second'],
            default => [],
        };

        $candidate->stageResults()->where('result', 'exempt')->whereNotIn('stage_code', $exemptStages)->delete();

        foreach ($exemptStages as $stageCode) {
            $candidate->stageResults()->updateOrCreate(['stage_code' => $stageCode], [
                'result' => 'exempt',
                'note' => $candidate->entry_reason_label,
                'decided_by' => $userId,
                'decided_at' => now(),
            ]);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
