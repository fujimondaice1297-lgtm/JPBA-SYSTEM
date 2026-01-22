<?php

namespace App\Mail;

use App\Models\GroupMailout;
use App\Models\GroupMailRecipient;
use App\Models\ProBowler;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GroupNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GroupMailout $mailout,
        public GroupMailRecipient $recipient,
        public ProBowler $bowler
    ){}

    public function build()
    {
        $subject = $this->mailout->subject;
        $body = $this->renderBody($this->mailout->body, $this->bowler);

        $m = $this->subject($subject)
                ->view('emails.group_notice', [
                    'html'=>$body,
                    'bowler'=>$this->bowler,
                    'mailout'=>$this->mailout
                ]);

        // ★ From を必ず決定（mailout → .env → それでも無ければ null ）
        $addr = $this->mailout->from_address ?: config('mail.from.address');
        $name = $this->mailout->from_name ?: config('mail.from.name');
        if ($addr) {
            $m->from($addr, $name ?: null);
        }

        return $m;
    }

    /** 超ライトな差し込み： {name} {license_no} {district} */
    private function renderBody(string $tpl, ProBowler $b): string
    {
        $rep = [
            '{name}'       => $b->name_kanji ?? '',
            '{license_no}' => $b->license_no ?? '',
            '{district}'   => $b->district?->label ?? '',
        ];
        return strtr($tpl, $rep);
    }
}
