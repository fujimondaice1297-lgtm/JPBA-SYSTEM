<?php

namespace App\Services;

use App\Models\RegisteredBall;
use App\Models\UsedBall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BallRegistrationRetentionAuditService
{
    public function build(?Carbon $asOf = null): array
    {
        $generatedAt = Carbon::now('Asia/Tokyo');
        $targetDate = ($asOf ?: $generatedAt)->copy()->startOfDay();

        $registeredProvisional = RegisteredBall::query()
            ->where(function ($query) {
                $query->whereNull('inspection_number')
                    ->orWhere('inspection_number', '')
                    ->orWhereNull('expires_at');
            })
            ->count();

        $registeredExpired = RegisteredBall::query()
            ->whereNotNull('inspection_number')
            ->where('inspection_number', '<>', '')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $targetDate->toDateString())
            ->count();

        $usedProvisional = UsedBall::query()
            ->where(function ($query) {
                $query->whereNull('inspection_number')
                    ->orWhere('inspection_number', '')
                    ->orWhereNull('expires_at');
            })
            ->count();

        $usedExpired = UsedBall::query()
            ->whereNotNull('inspection_number')
            ->where('inspection_number', '<>', '')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $targetDate->toDateString())
            ->count();

        $linkedExpiredUsed = DB::table('tournament_entry_balls')
            ->join('used_balls', 'used_balls.id', '=', 'tournament_entry_balls.used_ball_id')
            ->whereNotNull('used_balls.expires_at')
            ->whereDate('used_balls.expires_at', '<', $targetDate->toDateString())
            ->distinct()
            ->count('used_balls.id');

        return [
            'as_of_date' => $targetDate->toDateString(),
            'generated_at' => $generatedAt->toIso8601String(),
            'policy' => 'history_retained',
            'registered_balls' => [
                'total' => RegisteredBall::query()->count(),
                'provisional' => $registeredProvisional,
                'expired' => $registeredExpired,
            ],
            'used_balls' => [
                'total' => UsedBall::query()->count(),
                'provisional' => $usedProvisional,
                'expired' => $usedExpired,
                'expired_with_tournament_history' => $linkedExpiredUsed,
            ],
            'deleted' => 0,
        ];
    }
}
