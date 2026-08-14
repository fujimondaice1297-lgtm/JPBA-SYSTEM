<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\ProBowlerTraining;
use App\Models\Training;
use App\Models\TrainingOfficialListEntry;
use App\Models\TrainingSession;
use App\Models\TrainingSessionParticipant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TrainingComplianceService
{
    private ?Training $mandatoryTrainingModel = null;

    public const UNCONFIRMED = 'unconfirmed';

    public const VALID = 'valid';

    public const OFFICIAL_LIST_VALID = 'official_list_valid';

    public const EXPIRING_THIS_YEAR = 'expiring_this_year';

    public const EXPIRING_NEXT_YEAR = 'expiring_next_year';

    public const EXPIRED = 'expired';

    public const MISSING = 'missing';

    public const EXEMPT = 'exempt';

    public function mandatoryTraining(): Training
    {
        return $this->mandatoryTrainingModel ??= Training::query()->firstOrCreate(
            ['code' => 'mandatory'],
            ['name' => 'トーナメントプレイヤー講習会', 'valid_for_months' => 36, 'mandatory' => true]
        );
    }

    public function calculateExpiry(CarbonInterface|string $completedAt, ?Training $training = null): Carbon
    {
        $training ??= $this->mandatoryTraining();
        $months = max(1, (int) ($training->valid_for_months ?: 36));

        return Carbon::parse($completedAt)->startOfDay()->addMonthsNoOverflow($months)->subDay();
    }

    /** @return array<string, mixed> */
    public function statusAt(ProBowler $bowler, CarbonInterface|string|null $asOf = null): array
    {
        $date = $asOf ? Carbon::parse($asOf)->startOfDay() : today();
        $training = $this->mandatoryTraining();

        $records = ProBowlerTraining::query()
            ->where('pro_bowler_id', $bowler->id)
            ->where('training_id', $training->id)
            ->where('record_status', 'valid')
            ->whereNull('revoked_at')
            ->whereDate('completed_at', '<=', $date)
            ->orderByDesc('completed_at')
            ->get();

        $current = $records->first(fn (ProBowlerTraining $record): bool => $record->expires_at?->gte($date) ?? false);
        if ($current) {
            $expiryYear = (int) $current->expires_at->year;
            $status = self::VALID;
            if ($expiryYear === (int) $date->year) {
                $status = self::EXPIRING_THIS_YEAR;
            } elseif ($expiryYear === ((int) $date->year + 1)) {
                $status = self::EXPIRING_NEXT_YEAR;
            }

            return $this->result($status, $current, $date);
        }

        $officialEvidence = $this->officialListEvidenceAt($bowler, $date);
        if ($officialEvidence) {
            return $this->officialListResult($officialEvidence, $date);
        }

        if ($records->isNotEmpty()) {
            return $this->result(self::EXPIRED, $records->first(), $date);
        }

        $expiredOfficialEvidence = $this->expiredOfficialListEvidenceAt($bowler, $date);
        if ($expiredOfficialEvidence) {
            return $this->expiredOfficialListResult($expiredOfficialEvidence, $date);
        }

        return $this->result(self::MISSING, null, $date);
    }

    /** @return array<string, mixed> */
    public function entryDecision(ProBowler $bowler, CarbonInterface|string|null $asOf = null): array
    {
        $stored = (string) ($bowler->training_compliance_status ?: self::UNCONFIRMED);

        if ($stored === self::EXEMPT) {
            return [
                'allowed' => true,
                'status' => self::EXEMPT,
                'label' => '免除',
                'message' => '講習受講免除として登録されています。',
                'record' => null,
            ];
        }

        $status = $this->statusAt($bowler, $asOf);

        $status['allowed'] = in_array($status['status'], [
            self::VALID,
            self::OFFICIAL_LIST_VALID,
            self::EXPIRING_THIS_YEAR,
            self::EXPIRING_NEXT_YEAR,
        ], true);

        return $status;
    }

    /** @return array<string, mixed> */
    public function syncBowler(ProBowler $bowler, CarbonInterface|string|null $asOf = null): array
    {
        if ($bowler->training_compliance_status === self::EXEMPT) {
            return $this->entryDecision($bowler, $asOf);
        }

        $status = $this->statusAt($bowler, $asOf);
        $bowler->forceFill([
            'training_compliance_status' => $status['status'],
            'training_compliance_checked_at' => now(),
        ])->save();

        return $status;
    }

    /** @return array{attended:int,absent:int,exempt:int} */
    public function finalizeSession(TrainingSession $session, int $userId): array
    {
        $session->loadMissing('training', 'participants.bowler');
        $totals = ['attended' => 0, 'absent' => 0, 'exempt' => 0];

        DB::transaction(function () use ($session, $userId, &$totals): void {
            foreach ($session->participants as $participant) {
                if (! $participant->bowler) {
                    continue;
                }

                if (
                    $participant->attendance_status !== TrainingSessionParticipant::STATUS_ATTENDED
                    && $participant->pro_bowler_training_id
                ) {
                    ProBowlerTraining::query()->whereKey($participant->pro_bowler_training_id)->update([
                        'record_status' => 'revoked',
                        'revoked_at' => now(),
                    ]);
                }

                if ($participant->attendance_status === TrainingSessionParticipant::STATUS_ATTENDED) {
                    $expiry = $this->calculateExpiry($session->held_on, $session->training);
                    $record = ProBowlerTraining::query()->updateOrCreate(
                        [
                            'pro_bowler_id' => $participant->pro_bowler_id,
                            'training_id' => $session->training_id,
                            'completed_at' => $session->held_on,
                        ],
                        [
                            'training_session_id' => $session->id,
                            'expires_at' => $expiry,
                            'record_status' => 'valid',
                            'revoked_at' => null,
                            'recorded_by_user_id' => $userId,
                            'notes' => $participant->notes,
                        ]
                    );
                    $participant->pro_bowler_training_id = $record->id;
                    $totals['attended']++;
                } elseif ($participant->attendance_status === TrainingSessionParticipant::STATUS_EXEMPT) {
                    $participant->bowler->forceFill([
                        'training_compliance_status' => self::EXEMPT,
                        'training_compliance_checked_at' => now(),
                    ])->save();
                    $totals['exempt']++;
                } else {
                    $totals['absent']++;
                }

                $participant->processed_at = now();
                $participant->processed_by_user_id = $userId;
                $participant->save();

                if ($participant->attendance_status !== TrainingSessionParticipant::STATUS_EXEMPT) {
                    $this->syncBowler($participant->bowler);
                }
            }

            $session->forceFill([
                'status' => TrainingSession::STATUS_COMPLETED,
                'finalized_at' => now(),
                'finalized_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ])->save();
        });

        return $totals;
    }

    public function officialListEvidenceAt(
        ProBowler $bowler,
        CarbonInterface|string|null $asOf = null,
    ): ?TrainingOfficialListEntry {
        $date = $asOf ? Carbon::parse($asOf)->startOfDay() : today();
        $training = $this->mandatoryTraining();

        return TrainingOfficialListEntry::query()
            ->with('officialList')
            ->where('pro_bowler_id', $bowler->id)
            ->whereIn('match_status', ['matched', 'inactive'])
            ->whereHas('officialList', function ($query) use ($training, $date): void {
                $query->where('training_id', $training->id)
                    ->whereDate('valid_from', '<=', $date)
                    ->whereDate('valid_through', '>=', $date)
                    // 最新PDFは過去時点の受講を証明しないため、公開日より前に遡及適用しない。
                    ->whereDate('source_published_at', '<=', $date);
            })
            ->get()
            ->sortByDesc(fn (TrainingOfficialListEntry $entry) => $entry->officialList?->source_published_at?->timestamp ?? 0)
            ->first();
    }

    public function expiredOfficialListEvidenceAt(
        ProBowler $bowler,
        CarbonInterface|string|null $asOf = null,
    ): ?TrainingOfficialListEntry {
        $date = $asOf ? Carbon::parse($asOf)->startOfDay() : today();
        $training = $this->mandatoryTraining();

        return TrainingOfficialListEntry::query()
            ->with('officialList')
            ->where('pro_bowler_id', $bowler->id)
            ->whereIn('match_status', ['matched', 'inactive'])
            ->whereHas('officialList', function ($query) use ($training, $date): void {
                $query->where('training_id', $training->id)
                    ->whereDate('valid_through', '<', $date)
                    ->whereDate('source_published_at', '<=', $date);
            })
            ->get()
            ->sort(function (TrainingOfficialListEntry $left, TrainingOfficialListEntry $right): int {
                $leftList = $left->officialList;
                $rightList = $right->officialList;

                return [
                    $rightList?->valid_through?->timestamp ?? 0,
                    $rightList?->source_published_at?->timestamp ?? 0,
                ] <=> [
                    $leftList?->valid_through?->timestamp ?? 0,
                    $leftList?->source_published_at?->timestamp ?? 0,
                ];
            })
            ->first();
    }

    /** @return array<string, mixed> */
    private function result(string $status, ?ProBowlerTraining $record, Carbon $date): array
    {
        $labels = [
            self::VALID => '受講済み（有効）',
            self::EXPIRING_THIS_YEAR => '今年度で期限切れ',
            self::EXPIRING_NEXT_YEAR => '次年度で期限切れ・通知対象',
            self::EXPIRED => '期限切れ／大会出場権利なし',
            self::MISSING => '未受講／大会出場権利なし',
        ];

        $messages = [
            self::VALID => '講習受講期限は有効です。',
            self::EXPIRING_THIS_YEAR => '講習期限が今年度中に切れます。更新受講が必要です。',
            self::EXPIRING_NEXT_YEAR => '講習期限が次年度に切れるため、更新案内の対象です。',
            self::EXPIRED => '講習期限が切れているため、トーナメント出場資格がありません。',
            self::MISSING => '有効な講習履歴がないため、トーナメント出場資格がありません。',
        ];

        return [
            'allowed' => in_array($status, [self::VALID, self::OFFICIAL_LIST_VALID, self::EXPIRING_THIS_YEAR, self::EXPIRING_NEXT_YEAR], true),
            'status' => $status,
            'label' => $labels[$status] ?? $status,
            'message' => $messages[$status] ?? '',
            'record' => $record,
            'official_evidence' => null,
            'as_of' => $date,
            'completed_at' => $record?->completed_at,
            'expires_at' => $record?->expires_at,
            'date_precision' => $record ? 'exact_day' : null,
        ];
    }

    /** @return array<string, mixed> */
    private function officialListResult(TrainingOfficialListEntry $evidence, Carbon $date): array
    {
        $list = $evidence->officialList;

        return [
            'allowed' => true,
            'status' => self::OFFICIAL_LIST_VALID,
            'label' => '受講済み（公式修了者一覧）',
            'message' => sprintf('第%d回公式修了者一覧で受講済みを確認しています。', $list->edition_number),
            'record' => null,
            'official_evidence' => $evidence,
            'as_of' => $date,
            'completed_at' => null,
            'expires_at' => $list->valid_through,
            'date_precision' => 'official_cycle',
        ];
    }

    /** @return array<string, mixed> */
    private function expiredOfficialListResult(TrainingOfficialListEntry $evidence, Carbon $date): array
    {
        $list = $evidence->officialList;

        return [
            'allowed' => false,
            'status' => self::EXPIRED,
            'label' => '過去受講歴あり・期限切れ／大会出場権利なし',
            'message' => sprintf('%sで過去の受講を確認しましたが、現在は有効期限切れです。', $list->title),
            'record' => null,
            'official_evidence' => $evidence,
            'as_of' => $date,
            'completed_at' => null,
            'expires_at' => $list->valid_through,
            'date_precision' => 'official_cycle',
        ];
    }
}
