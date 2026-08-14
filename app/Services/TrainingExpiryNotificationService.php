<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\TrainingComplianceNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TrainingExpiryNotificationService
{
    public function __construct(private readonly TrainingComplianceService $compliance) {}

    public function countCandidatesForExpiryYear(int $expiryYear, ?array $bowlerIds = null): int
    {
        $count = 0;
        $query = ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player');
        if ($bowlerIds) {
            $query->whereIn('id', $bowlerIds);
        }

        $query->orderBy('id')->chunkById(100, function ($bowlers) use ($expiryYear, &$count): void {
            foreach ($bowlers as $bowler) {
                $expiresAt = $this->compliance->statusAt($bowler)['expires_at'] ?? null;
                if ($expiresAt && (int) $expiresAt->year === $expiryYear) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /** @return array{sent:int,skipped:int,failed:int,candidates:int} */
    public function sendForExpiryYear(int $expiryYear, ?array $bowlerIds = null, ?int $requestedBy = null): array
    {
        $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'candidates' => 0];

        $query = ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->with('userAccount');

        if ($bowlerIds) {
            $query->whereIn('id', $bowlerIds);
        }

        $query->orderBy('id')->chunkById(100, function ($bowlers) use ($expiryYear, $requestedBy, &$summary): void {
            foreach ($bowlers as $bowler) {
                $evidence = $this->compliance->statusAt($bowler);
                $record = $evidence['record'] ?? null;
                $expiresAt = $evidence['expires_at'] ?? null;
                if (! $expiresAt || (int) $expiresAt->year !== $expiryYear) {
                    continue;
                }
                $summary['candidates']++;

                $email = $bowler->userAccount?->email
                    ?: User::query()->where('pro_bowler_license_no', $bowler->license_no)->value('email')
                    ?: $bowler->email;

                $notification = TrainingComplianceNotification::query()->firstOrNew([
                    'pro_bowler_id' => $bowler->id,
                    'expires_on' => $expiresAt->toDateString(),
                    'notification_type' => 'expiry_previous_year',
                ]);

                if ($notification->exists && $notification->status === 'sent') {
                    $summary['skipped']++;

                    continue;
                }

                $notification->fill([
                    'pro_bowler_training_id' => $record?->id,
                    'notice_year' => $expiryYear - 1,
                    'recipient_email' => $email,
                    'requested_by_user_id' => $requestedBy,
                    'status' => $email ? 'pending' : 'skipped',
                    'error_message' => $email ? null : '送信先メールアドレスが登録されていません。',
                ])->save();

                if (! $email) {
                    $summary['skipped']++;

                    continue;
                }

                try {
                    $subject = '【JPBA】トーナメントプレイヤー講習会 更新時期のご案内';
                    $body = $bowler->name_kanji." 様\n\n"
                        .'現在登録されているトーナメントプレイヤー講習会の有効期限は '
                        .$expiresAt->format('Y年n月j日').' です。'."\n"
                        .'有効期限が切れる次年度に備え、今年度中に更新講習会の日程をご確認ください。'."\n\n"
                        .'受講申込・登録内容についてご不明な点は、JPBA事務局へお問い合わせください。'."\n\n"
                        .'公益社団法人 日本プロボウリング協会';

                    Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));

                    $notification->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                    $summary['sent']++;
                } catch (\Throwable $e) {
                    report($e);
                    $notification->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 2000)]);
                    $summary['failed']++;
                }
            }
        });

        return $summary;
    }
}
