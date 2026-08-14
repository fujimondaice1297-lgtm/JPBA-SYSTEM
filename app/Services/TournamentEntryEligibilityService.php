<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;

class TournamentEntryEligibilityService
{
    public function __construct(private readonly TrainingComplianceService $trainingCompliance)
    {
    }

    /** @return array<string, mixed> */
    public function evaluate(?ProBowler $bowler, ?Tournament $tournament = null): array
    {
        if (!$bowler) {
            return $this->denied('未結線', '選手情報が未結線です。', '-', '不可', '未結線');
        }

        $memberClass = (string) ($bowler->member_class ?? '');
        $memberClassLabel = $this->memberClassLabel($memberClass);
        $officialEntryAllowed = (bool) ($bowler->can_enter_official_tournament ?? false);

        if (!(bool) ($bowler->is_active ?? false)) {
            return $this->denied('会員無効', '現在の会員状態が無効です。', $memberClassLabel, $officialEntryAllowed ? '可' : '不可', '無効');
        }

        if ($memberClass !== 'player') {
            return $this->denied($memberClassLabel, '競技参加対象外の会員区分です。', $memberClassLabel, $officialEntryAllowed ? '可' : '不可', '有効');
        }

        if (!$officialEntryAllowed) {
            return $this->denied('公式戦対象外', '公式戦出場対象外として登録されています。', $memberClassLabel, '不可', '有効');
        }

        $asOf = $tournament?->start_date ?: today();
        $training = $this->trainingCompliance->entryDecision($bowler, $asOf);
        if (!($training['allowed'] ?? false)) {
            return array_merge(
                $this->denied('講習未受講', (string) ($training['message'] ?? '講習受講状況を確認してください。'), $memberClassLabel, '不可', '有効'),
                ['training' => $training]
            );
        }

        $message = ($training['status'] ?? null) === TrainingComplianceService::UNCONFIRMED
            ? (string) $training['message']
            : '大会エントリー可能です。講習：'.($training['label'] ?? '受講済み');

        return [
            'allowed' => true,
            'short' => '参加権利あり',
            'message' => $message,
            'member_class_label' => $memberClassLabel,
            'official_entry_label' => '可',
            'active_label' => '有効',
            'training' => $training,
        ];
    }

    /** @return array<string, mixed> */
    private function denied(string $short, string $message, string $memberClass, string $official, string $active): array
    {
        return [
            'allowed' => false,
            'short' => $short,
            'message' => $message,
            'member_class_label' => $memberClass,
            'official_entry_label' => $official,
            'active_label' => $active,
            'training' => null,
        ];
    }

    private function memberClassLabel(?string $memberClass): string
    {
        return match ($memberClass) {
            'player' => 'トーナメントプレイヤー',
            'pro_instructor' => 'プロインストラクター',
            'honorary_or_overseas' => '海外プロ',
            'other' => 'その他',
            default => '会員区分未設定',
        };
    }
}
