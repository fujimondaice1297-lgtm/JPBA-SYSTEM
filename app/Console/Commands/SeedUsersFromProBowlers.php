<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedUsersFromProBowlers extends Command
{
    protected $signature = 'seed:users-from-bowlers
        {--bowler-id=* : 発行対象のpro_bowlers.id（複数指定可）}
        {--license=* : 発行対象のライセンス番号（複数指定可）}
        {--all : 全選手を対象にする}
        {--dry-run : DBを変更せず対象と処理内容だけ確認する}';

    protected $description = '選手プロフィールから会員アカウントを段階発行し、選手IDとライセンス番号を結線する';

    public function handle(): int
    {
        $bowlerIds = collect($this->option('bowler-id'))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();
        $licenses = collect($this->option('license'))
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if (! $all && $bowlerIds->isEmpty() && $licenses->isEmpty()) {
            $this->error('安全のため対象指定が必要です。--bowler-id、--license、または --all を指定してください。');

            return self::FAILURE;
        }

        if ($all && ($bowlerIds->isNotEmpty() || $licenses->isNotEmpty())) {
            $this->error('--all と個別の対象指定は同時に使えません。');

            return self::FAILURE;
        }

        $query = ProBowler::query()->orderBy('id');
        if (! $all) {
            $query->where(function ($scope) use ($bowlerIds, $licenses) {
                if ($bowlerIds->isNotEmpty()) {
                    $scope->whereIn('id', $bowlerIds);
                }
                if ($licenses->isNotEmpty()) {
                    $method = $bowlerIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $scope->{$method}('license_no', $licenses);
                }
            });
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $query->chunkById(200, function ($bowlers) use ($dryRun, &$created, &$updated, &$skipped) {
            foreach ($bowlers as $bowler) {
                $result = $this->issueAccount($bowler, $dryRun);
                match ($result) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $skipped++,
                };
            }
        });

        $mode = $dryRun ? 'DRY-RUN' : '確定';
        $this->info("{$mode}: 新規 {$created}件 / 更新 {$updated}件 / 見送り {$skipped}件");
        if ($dryRun) {
            $this->comment('DBは変更していません。');
        }

        return self::SUCCESS;
    }

    private function issueAccount(ProBowler $bowler, bool $dryRun): string
    {
        $licenseNo = mb_strtoupper(trim((string) $bowler->license_no));
        $email = mb_strtolower(trim((string) $bowler->email));

        if ($licenseNo === '') {
            $this->warn("見送り: 選手ID {$bowler->id} はライセンス番号がありません。");

            return 'skipped';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->warn("見送り: {$licenseNo} は有効なメールアドレスがありません。");

            return 'skipped';
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
            $this->warn("見送り: {$licenseNo} のメールアドレスは別アカウントで使用中です。");

            return 'skipped';
        }

        if ($account && $account->pro_bowler_id && (int) $account->pro_bowler_id !== (int) $bowler->id) {
            $this->warn("見送り: {$licenseNo} の既存アカウントは別の選手IDに結線されています。");

            return 'skipped';
        }

        $displayName = $bowler->name_kanji ?: $bowler->name_kana ?: $licenseNo;
        $role = $account && in_array($account->role, ['admin', 'editor'], true)
            ? $account->role
            : 'member';
        $payload = [
            'name' => $displayName,
            'email' => $account?->email ?: $email,
            'role' => $role,
            'pro_bowler_id' => $bowler->id,
            'pro_bowler_license_no' => $licenseNo,
            'license_no' => $licenseNo,
        ];

        if ($dryRun) {
            $action = $account ? '更新予定' : '新規予定';
            $this->line("{$action}: {$licenseNo} {$displayName} / 選手ID {$bowler->id}");

            return $account ? 'updated' : 'created';
        }

        if ($account) {
            $account->update($payload);
            $this->line("更新: {$licenseNo} {$displayName}");

            return 'updated';
        }

        User::create(array_merge($payload, [
            // 本人は「パスワードを忘れた方」から初期設定する。共通初期パスワードは使わない。
            'password' => Hash::make(Str::random(48)),
            'is_admin' => false,
        ]));
        $bowler->forceFill(['password_change_status' => 2])->saveQuietly();
        $this->line("新規: {$licenseNo} {$displayName}");

        return 'created';
    }
}
