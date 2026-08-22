<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\RegisteredBall;
use App\Models\UsedBall;

class RegisteredBallLinkageService
{
    /**
     * 本登録ボールを、年度申請・大会登録で使用するマイボールへ同期する。
     */
    public function sync(RegisteredBall $registeredBall): ?UsedBall
    {
        $proBowler = $this->resolveOwner($registeredBall);
        if (! $proBowler) {
            return null;
        }

        if ((int) ($registeredBall->pro_bowler_id ?? 0) !== (int) $proBowler->id) {
            $registeredBall->forceFill(['pro_bowler_id' => $proBowler->id])->saveQuietly();
        }

        $payload = [
            'approved_ball_id' => $registeredBall->approved_ball_id,
            'serial_number' => $registeredBall->serial_number,
            'inspection_number' => $registeredBall->inspection_number,
            'registered_at' => $registeredBall->registered_at,
            'expires_at' => $registeredBall->expires_at,
        ];

        $usedBall = UsedBall::query()
            ->where('pro_bowler_id', $proBowler->id)
            ->whereRaw('upper(serial_number) = ?', [mb_strtoupper((string) $registeredBall->serial_number)])
            ->first();

        if ($usedBall) {
            $usedBall->update($payload);

            return $usedBall->fresh();
        }

        return UsedBall::create(array_merge(
            ['pro_bowler_id' => $proBowler->id],
            $payload
        ));
    }

    /**
     * 選手の本登録ボールを一括同期する。戻り値は同期できた件数。
     */
    public function syncForBowler(int $proBowlerId): int
    {
        $proBowler = ProBowler::query()->find($proBowlerId);
        if (! $proBowler) {
            return 0;
        }

        $registeredBalls = RegisteredBall::query()
            ->where(function ($query) use ($proBowler) {
                $query->where('pro_bowler_id', $proBowler->id)
                    ->orWhere('license_no', $proBowler->license_no);
            })
            ->get();

        $synced = 0;
        foreach ($registeredBalls as $registeredBall) {
            if ($this->sync($registeredBall)) {
                $synced++;
            }
        }

        return $synced;
    }

    private function resolveOwner(RegisteredBall $registeredBall): ?ProBowler
    {
        $proBowlerId = (int) ($registeredBall->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            $owner = ProBowler::query()->find($proBowlerId);
            if ($owner) {
                return $owner;
            }
        }

        $licenseNo = trim((string) ($registeredBall->license_no ?? ''));

        return $licenseNo === ''
            ? null
            : ProBowler::query()->where('license_no', $licenseNo)->first();
    }
}
