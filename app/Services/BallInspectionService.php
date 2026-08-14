<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\UsedBall;
use Illuminate\Support\Carbon;

class BallInspectionService
{
    public const EXPIRING_SOON_DAYS = 30;

    public function expiresOn($inspectionDate): ?Carbon
    {
        if (blank($inspectionDate)) {
            return null;
        }

        return Carbon::parse($inspectionDate)
            ->startOfDay()
            ->addYear()
            ->subDay();
    }

    public function status(?string $inspectionNumber, $expiresAt, $referenceDate = null): array
    {
        $number = trim((string) $inspectionNumber);
        $reference = $referenceDate
            ? Carbon::parse($referenceDate)->startOfDay()
            : today();

        if ($number === '' || blank($expiresAt)) {
            return [
                'key' => 'provisional',
                'label' => '仮登録 / 検量証待ち',
                'badge' => 'warning',
                'days_to_expire' => null,
            ];
        }

        $expires = Carbon::parse($expiresAt)->startOfDay();
        $days = $reference->diffInDays($expires, false);

        if ($expires->lt($reference)) {
            return [
                'key' => 'expired',
                'label' => '期限切れ',
                'badge' => 'danger',
                'days_to_expire' => $days,
            ];
        }

        if ($expires->lte($reference->copy()->addDays(self::EXPIRING_SOON_DAYS))) {
            return [
                'key' => 'expiring_soon',
                'label' => '期限間近',
                'badge' => 'warning',
                'days_to_expire' => $days,
            ];
        }

        return [
            'key' => 'valid',
            'label' => '有効',
            'badge' => 'success',
            'days_to_expire' => $days,
        ];
    }

    public function referenceDateForTournament(?Tournament $tournament): Carbon
    {
        return $tournament?->start_date
            ? Carbon::parse($tournament->start_date)->startOfDay()
            : today();
    }

    public function tournamentEligibility(UsedBall $ball, ?Tournament $tournament): array
    {
        $referenceDate = $this->referenceDateForTournament($tournament);
        $status = $this->status(
            $ball->inspection_number,
            $ball->expires_at,
            $referenceDate
        );

        if ($status['key'] === 'provisional') {
            return [
                'allowed' => false,
                'message' => '検量証番号と有効期限が登録されていません。',
                'reference_date' => $referenceDate,
                'status' => $status,
            ];
        }

        if ($status['key'] === 'expired') {
            return [
                'allowed' => false,
                'message' => '大会開催日（'.$referenceDate->format('Y-m-d').'）時点で検量証が期限切れです。',
                'reference_date' => $referenceDate,
                'status' => $status,
            ];
        }

        return [
            'allowed' => true,
            'message' => '大会開催日時点で有効です。',
            'reference_date' => $referenceDate,
            'status' => $status,
        ];
    }
}
