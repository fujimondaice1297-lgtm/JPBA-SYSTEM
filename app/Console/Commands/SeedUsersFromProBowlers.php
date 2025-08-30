<?php

namespace App\Console\Commands;

use App\Models\ProBowler;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedUsersFromProBowlers extends Command
{
    // ← これがあなたが実行したコマンド名
    protected $signature = 'seed:users-from-bowlers';

    protected $description = 'pro_bowlers から users を作成/更新（license_no と紐付け）';

    public function handle(): int
    {
        $count = 0;

        ProBowler::query()->orderBy('id')->chunk(500, function ($chunk) use (&$count) {
            foreach ($chunk as $bowler) {
                if (empty($bowler->license_no)) {
                    $this->warn("skip: id={$bowler->id}（license_noなし）");
                    continue;
                }

                // 1) メールを決める（重複を避ける）
                $email = $bowler->email ?: strtolower($bowler->license_no).'@example.invalid';

                // すでに他ユーザーが同じメールを使っている場合は、ライセンス由来のダミーに差し替え
                $dup = User::where('email', $email)
                    ->where('pro_bowler_license_no', '!=', $bowler->license_no)
                    ->exists();
                if ($dup) {
                    $email = strtolower($bowler->license_no).'@example.invalid';
                }

                // 2) 既存のユーザーを license_no で探す
                $user = User::where('pro_bowler_license_no', $bowler->license_no)->first();

                if ($user) {
                    // 更新
                    $user->update([
                        'name'  => $bowler->name_kanji ?: $bowler->name_kana ?: $bowler->license_no,
                        'email' => $email,
                    ]);
                } else {
                    // 新規作成（仮パスワードを入れておく）
                    $user = User::create([
                        'name'                   => $bowler->name_kanji ?: $bowler->name_kana ?: $bowler->license_no,
                        'email'                  => $email,
                        'password'               => Hash::make('changeme'), // あとで本人が変更
                        'pro_bowler_license_no'  => $bowler->license_no,
                        'is_admin'               => 0,
                    ]);
                }

                $count++;
            }
        });

        $this->info("done. seeded/updated users: {$count}");
        return self::SUCCESS;
    }
}
