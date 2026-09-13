<?php

namespace App\Console\Commands;

use App\Mail\SmtpReadinessProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailReadinessCheck extends Command
{
    protected $signature = 'jpba:mail-readiness
        {--recipient= : 疎通確認メールを受け取る1件のメールアドレス}
        {--send : 指定した1宛先へ疎通確認メールを実送信する}';

    protected $description = '本番SMTP設定を監査し、明示した1宛先だけへ疎通確認メールを送る';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $from = (string) config('mail.from.address');
        $configured = ! in_array($mailer, ['log', 'array'], true)
            && filled($host)
            && ! preg_match('/(?:localhost|127\.0\.0\.1|example\.)/i', $host)
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;

        $this->table(['監査項目', '結果'], [
            ['メール方式', $mailer],
            ['SMTPホスト', $this->maskHost($host)],
            ['送信元', filter_var($from, FILTER_VALIDATE_EMAIL) !== false ? '形式OK' : '未設定／不正'],
            ['本番送信設定', $configured ? 'OK' : 'NG'],
        ]);

        if (! $configured) {
            $this->error('本番SMTP設定が未完成です。外部送信は行いませんでした。');

            return self::FAILURE;
        }

        if (! $this->option('send')) {
            $this->info('設定値の監査だけを完了しました。外部送信は行っていません。');

            return self::SUCCESS;
        }

        $recipient = mb_strtolower(trim((string) $this->option('recipient')));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('--send には有効な --recipient を1件指定してください。');

            return self::FAILURE;
        }

        try {
            Mail::to($recipient)->send(new SmtpReadinessProbe);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('SMTP疎通確認メールの送信に失敗しました。ログを確認してください。');

            return self::FAILURE;
        }

        $this->info('SMTP疎通確認メールを1件送信しました: '.$this->maskEmail($recipient));

        return self::SUCCESS;
    }

    private function maskHost(string $host): string
    {
        if ($host === '') {
            return '未設定';
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return '設定済み';
        }

        return '***.'.implode('.', array_slice($parts, -2));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
