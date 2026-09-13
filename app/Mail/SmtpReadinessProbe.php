<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SmtpReadinessProbe extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): static
    {
        return $this
            ->subject('JPBA新サイト SMTP疎通確認')
            ->text('emails.smtp_readiness_probe');
    }
}
