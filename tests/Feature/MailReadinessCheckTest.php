<?php

use App\Mail\SmtpReadinessProbe;
use Illuminate\Support\Facades\Mail;

test('mail readiness refuses development mail drivers without sending', function () {
    Mail::fake();
    config()->set('mail.default', 'array');

    $this->artisan('jpba:mail-readiness')
        ->expectsOutputToContain('本番SMTP設定が未完成です。外部送信は行いませんでした。')
        ->assertFailed();

    Mail::assertNothingSent();
});

test('mail readiness audits configured smtp without sending by default', function () {
    Mail::fake();
    configureProductionSmtp();

    $this->artisan('jpba:mail-readiness')
        ->expectsOutputToContain('設定値の監査だけを完了しました。外部送信は行っていません。')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

test('mail readiness sends one probe only to the explicit recipient', function () {
    Mail::fake();
    configureProductionSmtp();

    $this->artisan('jpba:mail-readiness', [
        '--send' => true,
        '--recipient' => 'operator@jpba.test',
    ])
        ->expectsOutputToContain('SMTP疎通確認メールを1件送信しました: o***@jpba.test')
        ->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(SmtpReadinessProbe::class, fn ($mail) => $mail->hasTo('operator@jpba.test'));
});

function configureProductionSmtp(): void
{
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'smtp.jpba.test');
    config()->set('mail.from.address', 'no-reply@jpba.test');
}
