<?php

namespace App\Services;

use App\Models\BallAnnualRegistration;
use App\Models\BallAnnualRegistrationHistory;
use App\Models\Tournament;
use Illuminate\Support\Collection;

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
        $registration = $this->latestApproved($proBowlerId, $year);

        if (!$registration) {
            return collect();
        }

        return $registration->usedBalls()
            ->pluck('used_balls.id')
            ->map(fn ($id) => (int) $id)
            ->values();
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
}
