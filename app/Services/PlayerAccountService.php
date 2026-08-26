<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\User;
use App\Models\UserAccountStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PlayerAccountService
{
    /**
     * @return array{status:string,message:string,user:?User}
     */
    public function issue(ProBowler $bowler, bool $dryRun = false, ?int $changedBy = null): array
    {
        $licenseNo = mb_strtoupper(trim((string) $bowler->license_no));
        $email = mb_strtolower(trim((string) $bowler->email));

        if (! $bowler->is_active) {
            return $this->skipped("{$licenseNo} は会員状態が無効です。");
        }
        if ($licenseNo === '') {
            return $this->skipped("選手ID {$bowler->id} はライセンス番号がありません。");
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->skipped("{$licenseNo} は有効なメールアドレスがありません。");
        }

        $account = User::query()
            ->where('pro_bowler_id', $bowler->id)
            ->orWhere('pro_bowler_license_no', $licenseNo)
            ->orWhere('license_no', $licenseNo)
            ->first();
        $emailOwner = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->when($account, fn ($query) => $query->whereKeyNot($account->id))
            ->first();

        if ($emailOwner) {
            return $this->skipped("{$licenseNo} のメールアドレスは別アカウントで使用中です。");
        }
        if ($account?->pro_bowler_id && (int) $account->pro_bowler_id !== (int) $bowler->id) {
            return $this->skipped("{$licenseNo} の既存アカウントは別の選手IDに結線されています。");
        }
        if ($account && in_array($account->role, ['admin', 'editor'], true)) {
            return $this->skipped("{$licenseNo} は管理者・編集者アカウントに結線されているため自動更新できません。");
        }

        $displayName = $bowler->name_kanji ?: $bowler->name_kana ?: $licenseNo;
        $payload = [
            'name' => $displayName,
            'email' => $email,
            'role' => 'member',
            'pro_bowler_id' => $bowler->id,
            'pro_bowler_license_no' => $licenseNo,
            'license_no' => $licenseNo,
        ];

        if ($dryRun) {
            $action = $account ? '更新予定' : '新規予定';

            return [
                'status' => $account ? 'updated' : 'created',
                'message' => "{$action}: {$licenseNo} {$displayName} / 選手ID {$bowler->id}",
                'user' => $account,
            ];
        }

        return DB::transaction(function () use ($account, $bowler, $payload, $licenseNo, $displayName, $changedBy): array {
            if ($account) {
                $account->update($payload);

                return [
                    'status' => 'updated',
                    'message' => "更新: {$licenseNo} {$displayName}",
                    'user' => $account->fresh(),
                ];
            }

            $created = User::query()->create(array_merge($payload, [
                'password' => Hash::make(Str::random(48)),
                'is_admin' => false,
                'account_status' => User::STATUS_ACTIVE,
            ]));
            UserAccountStatusLog::query()->create([
                'user_id' => $created->id,
                'from_status' => null,
                'to_status' => User::STATUS_ACTIVE,
                'reason' => '選手アカウント発行',
                'changed_by' => $changedBy,
            ]);
            $bowler->forceFill(['password_change_status' => 2])->saveQuietly();

            return [
                'status' => 'created',
                'message' => "新規: {$licenseNo} {$displayName}",
                'user' => $created,
            ];
        });
    }

    public function sendSetupLink(User $user): string
    {
        if (! $user->isAccountActive()) {
            throw new InvalidArgumentException('利用中のアカウントだけに初期設定メールを送信できます。');
        }

        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status === Password::RESET_LINK_SENT) {
            $user->forceFill(['setup_link_sent_at' => now()])->save();
        }

        return $status;
    }

    public function changeStatus(User $user, string $status, ?string $reason, ?int $changedBy): User
    {
        if (! in_array($status, [User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('アカウント状態が不正です。');
        }
        if (in_array($user->role, ['admin', 'editor'], true)) {
            throw new InvalidArgumentException('管理者・編集者アカウントは選手画面から状態変更できません。');
        }

        $reason = trim((string) $reason);
        if ($status !== User::STATUS_ACTIVE && $reason === '') {
            throw new InvalidArgumentException('利用停止または終了の理由を入力してください。');
        }

        return DB::transaction(function () use ($user, $status, $reason, $changedBy): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $fromStatus = $locked->account_status ?: User::STATUS_ACTIVE;

            $locked->forceFill([
                'account_status' => $status,
                'suspended_at' => $status === User::STATUS_SUSPENDED ? now() : null,
                'closed_at' => $status === User::STATUS_CLOSED ? now() : null,
                'account_status_note' => $reason !== '' ? $reason : null,
            ])->save();

            if ($fromStatus !== $status) {
                UserAccountStatusLog::query()->create([
                    'user_id' => $locked->id,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'reason' => $reason !== '' ? $reason : null,
                    'changed_by' => $changedBy,
                ]);
            }

            if ($status !== User::STATUS_ACTIVE) {
                DB::table('sessions')->where('user_id', $locked->id)->delete();
                DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
            }

            return $locked->fresh();
        });
    }

    /**
     * @return array{status:string,message:string,user:null}
     */
    private function skipped(string $message): array
    {
        return ['status' => 'skipped', 'message' => '見送り: '.$message, 'user' => null];
    }
}
