<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Services\PlayerAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

class SeedUsersFromProBowlers extends Command
{
    protected $signature = 'seed:users-from-bowlers
        {--bowler-id=* : 発行対象のpro_bowlers.id（複数指定可）}
        {--license=* : 発行対象のライセンス番号（複数指定可）}
        {--all : 全選手を対象にする}
        {--dry-run : DBを変更せず対象と処理内容だけ確認する}
        {--send-setup-link : 発行・更新後に初回パスワード設定メールを送信する}';

    protected $description = '選手プロフィールから会員アカウントを段階発行し、選手IDとライセンス番号を結線する';

    public function handle(PlayerAccountService $accounts): int
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
        $sendSetupLink = (bool) $this->option('send-setup-link');

        if (! $all && $bowlerIds->isEmpty() && $licenses->isEmpty()) {
            $this->error('安全のため対象指定が必要です。--bowler-id、--license、または --all を指定してください。');

            return self::FAILURE;
        }

        if ($all && ($bowlerIds->isNotEmpty() || $licenses->isNotEmpty())) {
            $this->error('--all と個別の対象指定は同時に使えません。');

            return self::FAILURE;
        }
        if ($all && $sendSetupLink) {
            $this->error('安全のため --all と --send-setup-link は同時に使えません。');

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
        $mailSent = 0;
        $mailFailed = 0;

        $query->chunkById(200, function ($bowlers) use (
            $accounts,
            $dryRun,
            $sendSetupLink,
            &$created,
            &$updated,
            &$skipped,
            &$mailSent,
            &$mailFailed
        ): void {
            foreach ($bowlers as $bowler) {
                $result = $accounts->issue($bowler, $dryRun);
                $this->{$result['status'] === 'skipped' ? 'warn' : 'line'}($result['message']);
                match ($result['status']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $skipped++,
                };

                if (! $sendSetupLink || $result['status'] === 'skipped') {
                    continue;
                }
                if ($dryRun) {
                    $this->comment('送信予定: '.$bowler->email);

                    continue;
                }

                $status = $accounts->sendSetupLink($result['user']);
                if ($status === Password::RESET_LINK_SENT) {
                    $mailSent++;
                } else {
                    $mailFailed++;
                    $this->warn('初期設定メール送信失敗: '.$bowler->license_no.' / '.__($status));
                }
            }
        });

        $mode = $dryRun ? 'DRY-RUN' : '確定';
        $this->info("{$mode}: 新規 {$created}件 / 更新 {$updated}件 / 見送り {$skipped}件");
        if ($sendSetupLink) {
            $this->info("初期設定メール: 送信 {$mailSent}件 / 失敗 {$mailFailed}件");
        }
        if ($dryRun) {
            $this->comment('DBは変更していません。');
        }

        return $mailFailed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
