<?php

namespace App\Services;

use App\Models\BallAnnualRegistration;
use App\Models\BallAnnualRegistrationHistory;
use App\Models\ProBowler;
use App\Models\Tournament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BallAnnualRegistrationService
{
    public function registrationYearForTournament(Tournament $tournament): int
    {
        if ($tournament->start_date) {
            return (int) $tournament->start_date->year;
        }

        if ((int) $tournament->year > 0) {
            return (int) $tournament->year;
        }

        return (int) now()->year;
    }

    public function latestApproved(int $proBowlerId, int $year): ?BallAnnualRegistration
    {
        return BallAnnualRegistration::query()
            ->where('pro_bowler_id', $proBowlerId)
            ->where('registration_year', $year)
            ->where('status', BallAnnualRegistration::STATUS_APPROVED)
            ->orderByDesc('revision')
            ->first();
    }

    public function latestApprovedOrCarryover(int $proBowlerId, int $year): ?BallAnnualRegistration
    {
        $approved = $this->latestApproved($proBowlerId, $year);
        if ($approved) {
            return $approved;
        }

        $this->ensureInspectionCarryover($proBowlerId, $year);

        return $this->latestApproved($proBowlerId, $year);
    }

    public function workingRegistration(int $proBowlerId, int $year): ?BallAnnualRegistration
    {
        return BallAnnualRegistration::query()
            ->where('pro_bowler_id', $proBowlerId)
            ->where('registration_year', $year)
            ->whereIn('status', [
                BallAnnualRegistration::STATUS_DRAFT,
                BallAnnualRegistration::STATUS_SUBMITTED,
                BallAnnualRegistration::STATUS_RETURNED,
            ])
            ->orderByDesc('revision')
            ->first();
    }

    public function approvedUsedBallIds(int $proBowlerId, int $year): Collection
    {
        $registration = $this->latestApprovedOrCarryover($proBowlerId, $year);

        if (! $registration) {
            return collect();
        }

        return $registration->usedBalls()
            ->pluck('used_balls.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * 前年度に承認済みで、対象年度の元日時点でも検量証が有効なボールだけを
     * 対象年度へ自動承認として引き継ぐ。対象年度に申請が1件でもあれば変更しない。
     */
    public function ensureInspectionCarryover(int $proBowlerId, int $year): ?BallAnnualRegistration
    {
        if ($year <= 2000) {
            return null;
        }

        return DB::transaction(function () use ($proBowlerId, $year) {
            ProBowler::query()
                ->whereKey($proBowlerId)
                ->lockForUpdate()
                ->firstOrFail(['id']);

            $targetExists = BallAnnualRegistration::query()
                ->where('pro_bowler_id', $proBowlerId)
                ->where('registration_year', $year)
                ->exists();

            if ($targetExists) {
                return $this->latestApproved($proBowlerId, $year);
            }

            $previous = $this->latestApproved($proBowlerId, $year - 1);
            if (! $previous) {
                return null;
            }

            $ballIds = $this->eligibleCarryoverBallIds($previous, $year);
            if ($ballIds->isEmpty()) {
                return null;
            }

            $registration = BallAnnualRegistration::query()->create([
                'pro_bowler_id' => $proBowlerId,
                'registration_year' => $year,
                'revision' => 1,
                'status' => BallAnnualRegistration::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
            $registration->usedBalls()->sync($ballIds->all());

            $this->recordHistory(
                $registration,
                'inspection_carryover',
                null,
                BallAnnualRegistration::STATUS_APPROVED,
                null,
                ($year - 1).'年度承認から、有効な検量証を持つボールを自動引継ぎしました。',
                [
                    'source_registration_id' => $previous->id,
                    'ball_ids' => $ballIds->all(),
                    'ball_count' => $ballIds->count(),
                    'valid_on' => Carbon::create($year, 1, 1)->toDateString(),
                ]
            );

            return $registration->fresh();
        });
    }

    public function carryOverYear(int $year, bool $write = false): array
    {
        $previousApprovals = BallAnnualRegistration::query()
            ->where('registration_year', $year - 1)
            ->where('status', BallAnnualRegistration::STATUS_APPROVED)
            ->orderByDesc('revision')
            ->get()
            ->unique('pro_bowler_id')
            ->values();

        $planned = [];
        $skippedExisting = 0;
        $skippedEmpty = 0;

        foreach ($previousApprovals as $previous) {
            $proBowlerId = (int) $previous->pro_bowler_id;
            $targetExists = BallAnnualRegistration::query()
                ->where('pro_bowler_id', $proBowlerId)
                ->where('registration_year', $year)
                ->exists();

            if ($targetExists) {
                $skippedExisting++;

                continue;
            }

            $ballIds = $this->eligibleCarryoverBallIds($previous, $year);
            if ($ballIds->isEmpty()) {
                $skippedEmpty++;

                continue;
            }

            $planned[] = [
                'pro_bowler_id' => $proBowlerId,
                'source_registration_id' => (int) $previous->id,
                'ball_ids' => $ballIds->all(),
            ];
        }

        $created = 0;
        if ($write) {
            foreach ($planned as $row) {
                if ($this->ensureInspectionCarryover((int) $row['pro_bowler_id'], $year)) {
                    $created++;
                }
            }
        }

        return [
            'mode' => $write ? 'write' : 'dry-run',
            'target_year' => $year,
            'source_year' => $year - 1,
            'previous_approved_count' => $previousApprovals->count(),
            'planned_count' => count($planned),
            'created_count' => $created,
            'skipped_existing_count' => $skippedExisting,
            'skipped_no_valid_inspection_count' => $skippedEmpty,
            'planned' => $planned,
        ];
    }

    public function recordHistory(
        BallAnnualRegistration $registration,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorId,
        ?string $note = null,
        ?array $payload = null
    ): BallAnnualRegistrationHistory {
        return $registration->histories()->create([
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'acted_by_user_id' => $actorId,
            'note' => $note,
            'payload' => $payload,
        ]);
    }

    private function eligibleCarryoverBallIds(BallAnnualRegistration $registration, int $year): Collection
    {
        $validOn = Carbon::create($year, 1, 1)->toDateString();

        return $registration->usedBalls()
            ->whereNotNull('used_balls.inspection_number')
            ->whereRaw("btrim(used_balls.inspection_number) <> ''")
            ->whereNotNull('used_balls.expires_at')
            ->whereDate('used_balls.expires_at', '>=', $validOn)
            ->pluck('used_balls.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
