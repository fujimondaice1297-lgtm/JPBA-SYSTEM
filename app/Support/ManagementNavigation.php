<?php

namespace App\Support;

use App\Models\User;

final class ManagementNavigation
{
    /**
     * 管理画面で使用する導線を、実際の作業順にまとめる。
     *
     * @return array<int, array<string, mixed>>
     */
    public function groups(User $user): array
    {
        $groups = [
            [
                'key' => 'preparation',
                'label' => '大会準備',
                'short_label' => '準備',
                'description' => '大会の新規作成、要項、会場、シード、出力形式を整えます。',
                'tone' => 'blue',
                'items' => [
                    $this->item('年間予定表', 'annual_schedules.edit', '公式PDFと同じ配置で年間予定を編集・公開します。', ['annual_schedules.*'], false, ['year' => now()->year]),
                    $this->item('大会一覧・編集', 'tournaments.index', '大会を選び、設定や運用画面へ進みます。', ['tournaments.*']),
                    $this->item('大会を新規作成', 'tournaments.create', '新しい大会と申込条件を作成します。', ['tournaments.create']),
                    $this->item('大会テンプレート', 'tournament_templates.index', '過去大会の設定を再利用します。', ['tournament_templates.*']),
                    $this->item('最終成績フォーマット', 'tournament_result_formats.index', '大会別の成績出力形式を管理します。', ['tournament_result_formats.*']),
                    $this->item('会場マスタ', 'venues.index', '会場情報を登録・修正します。', ['venues.*']),
                    $this->item('シード選手リスト', 'pro_bowler_seed_lists.index', '年度別のシード対象を確認します。', ['pro_bowler_seed_lists.*']),
                ],
            ],
            [
                'key' => 'entry',
                'label' => '参加受付・ボール',
                'short_label' => '受付',
                'description' => 'エントリー、参加選手、抽選、年度ボール承認を処理します。',
                'tone' => 'teal',
                'items' => [
                    $this->item('選手・ボール登録', 'tournaments.index', '大会を選び、参加者と使用ボールを確認します。', ['tournaments.entries.*']),
                    $this->item('年度ボール申請承認', 'ball_annual_registrations.index', '提出済み申請を選手単位で承認します。', ['ball_annual_registrations.*']),
                    $this->item('ボールカタログ', 'approved_balls.index', 'メーカー掲載品とUSBC照合を管理します。', ['approved_balls.*']),
                    $this->item('選手登録ボール', 'used_balls.index', '選手ごとのボールと検量証を確認します。', ['used_balls.*']),
                    $this->item('プロ参加グループ', 'pro_groups.index', '大会参加者のグループを作成します。', ['pro_groups.*']),
                ],
            ],
            [
                'key' => 'event_day',
                'label' => '大会当日',
                'short_label' => '当日',
                'description' => '参加確認、抽選、レーン、スコア、速報を運用します。',
                'tone' => 'orange',
                'items' => [
                    $this->item('大会運用画面を選ぶ', 'tournaments.index', '対象大会から参加者・抽選・運用ログへ進みます。', ['tournaments.draws.*', 'tournaments.operation_logs.*']),
                    $this->item('速報・スコア入力', 'scores.input', 'ゲームごとのスコアを入力します。', ['scores.input']),
                    $this->item('速報ランキング確認', 'scores.result', '入力済みスコアの順位を確認します。', ['scores.result']),
                    $this->item('大会成績入力', 'tournament_results.index', '対象大会を選んで成績を登録します。', ['tournament_results.*']),
                ],
            ],
            [
                'key' => 'after_event',
                'label' => '大会終了後',
                'short_label' => '終了後',
                'description' => '成績確定、ランキング、公認記録、公開内容を確認します。',
                'tone' => 'purple',
                'items' => [
                    $this->item('大会成績一覧', 'tournament_results.index', '大会ごとの確定成績を確認・修正します。', ['tournament_results.*']),
                    $this->item('年間ランキング', 'rankings.index', 'ポイント・賞金・アベレージを確認します。', ['rankings.*']),
                    $this->item('公認記録管理', 'record_types.index', 'パーフェクト等の明細と公認番号を管理します。', ['record_types.*']),
                    $this->item('パーフェクト記録', 'perfect_records.index', '従来の記録一覧を確認します。', ['perfect_records.*']),
                ],
            ],
            [
                'key' => 'people',
                'label' => '選手・資格',
                'short_label' => '選手',
                'description' => '選手プロフィール、資格、講習、出場資格を管理します。',
                'tone' => 'navy',
                'items' => [
                    $this->item('全プロデータ', 'pro_bowlers.list', '選手を検索してプロフィールを編集します。', ['pro_bowlers.*']),
                    $this->item('選手を新規登録', 'pro_bowlers.create', '新しいプロボウラーを登録します。', ['pro_bowlers.create']),
                    $this->item('今年度シードプロ', 'tournament_pro.index', '当年度の対象選手を確認します。', ['tournament_pro.*']),
                    $this->item('認定インストラクター', 'instructors.index', '資格情報を登録・更新します。', ['instructors.*']),
                    $this->item('講習一括登録', 'trainings.bulk', '講習受講履歴をまとめて登録します。', ['trainings.*']),
                    $this->item('出場資格一覧', 'eligibility.evergreen', '永久・A級などの資格対象を確認します。', ['eligibility.*']),
                    $this->item('TP登録会受講情報', 'tp_registration.index', '受講・登録情報を確認します。', ['tp_registration.*']),
                    $this->item('講習コンプライアンス', 'admin.compliance.index', '期限、未受講、通知、出場資格を確認します。', ['admin.compliance.*'], true),
                ],
            ],
            [
                'key' => 'publishing',
                'label' => '広報・システム',
                'short_label' => '広報',
                'description' => 'お知らせ、カレンダー、速報記事、公開情報を管理します。',
                'tone' => 'rose',
                'items' => [
                    $this->item('INFORMATION管理', 'admin.informations.index', '公開・会員向けのお知らせを編集します。', ['admin.informations.*'], true),
                    $this->item('INFORMATION新規作成', 'admin.informations.create', '新しいお知らせを掲載します。', ['admin.informations.create'], true),
                    $this->item('一般公開ページ編集', 'admin.public_pages.index', '規程・方針・制度案内などの固定ページを編集します。', ['admin.public_pages.*'], true),
                    $this->item('カレンダー管理', 'calendar_events.index', '大会・行事の日程を登録します。', ['calendar_events.*']),
                    $this->item('速報ニュース管理', 'flash_news.index', '速報記事を作成・修正します。', ['flash_news.*']),
                    $this->item('殿堂入り管理', 'hof.index', '殿堂入り選手と写真を管理します。', ['hof.*']),
                    $this->item('公開INFORMATION確認', 'informations.index', '一般公開側の見え方を確認します。', ['informations.*']),
                ],
            ],
        ];

        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => !($item['admin_only'] ?? false) || $user->isAdmin()
            ));
        }
        unset($group);

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function quickActions(User $user): array
    {
        $actions = [
            $this->item('大会を選ぶ', 'tournaments.index', '大会別の作業を開始', ['tournaments.*']),
            $this->item('速報を入力', 'scores.input', 'スコア入力画面へ', ['scores.input']),
            $this->item('選手を探す', 'pro_bowlers.list', 'プロフィール検索・編集', ['pro_bowlers.*']),
            $this->item('ボール申請を承認', 'ball_annual_registrations.index', '提出済み申請を確認', ['ball_annual_registrations.*']),
        ];

        if ($user->isAdmin()) {
            $actions[] = $this->item('お知らせを作る', 'admin.informations.create', '公開・会員向けに掲載', ['admin.informations.*'], true);
        }

        return $actions;
    }

    /**
     * @param array<int, string> $patterns
     * @return array<string, mixed>
     */
    private function item(
        string $label,
        string $route,
        string $description,
        array $patterns,
        bool $adminOnly = false,
        array $routeParameters = [],
    ): array {
        return [
            'label' => $label,
            'route' => $route,
            'description' => $description,
            'patterns' => $patterns,
            'admin_only' => $adminOnly,
            'route_parameters' => $routeParameters,
        ];
    }
}
