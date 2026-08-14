<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $formatId = DB::table('tournament_result_formats')
            ->where('code', 'rokko_queens')
            ->value('id');

        if (! $formatId) {
            $formatId = DB::table('tournament_result_formats')->insertGetId([
                'name' => '六甲クイーンズ方式',
                'code' => 'rokko_queens',
                'description' => '六甲クイーンズオープン用のA4横2ページ・縦1ページ最終成績形式',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('tournament_result_format_versions')->updateOrInsert(
            [
                'tournament_result_format_id' => $formatId,
                'version_no' => 1,
            ],
            [
                'template_disk' => 'resource',
                'template_path' => 'resources/tournament_result_formats/rokko_queens_v1.xlsx',
                'notes' => '2025年・2026年公式最終成績PDFを全3ページ比較して作成した初版',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $versionId = DB::table('tournament_result_format_versions')
            ->where('tournament_result_format_id', $formatId)
            ->where('version_no', 1)
            ->value('id');

        $targets = DB::table('tournaments')
            ->where('year', 2026)
            ->where('name', 'like', '%六甲クイーンズ%')
            ->get(['id', 'template_snapshot', 'award_highlights', 'result_cards']);

        $resultFormatDefaults = [
            'english_title' => "42nd ROKKO QUEEN's OPEN TOURNAMENT",
            'venue_display' => '神戸六甲ボウル(ＡＭＦ ４０Ｌ)',
            'venue_postal_code' => '〒６５７－００３５',
            'schedule_text' => "１日目　７月１０日(金)　予選１０Ｇ･･プロ８５名・アマ１０名、計９５名にて予選１０Ｇを投球し上位３２名を準決勝へ選出\n２日目　７月１１日(土)　準決勝５Ｇ･･３２名にて準決勝５Ｇを投球し通算１５Ｇトータルピンにて上位８名を決勝ＲＲへ選出\n　　　　　　　　　　　 決勝RR８Ｇ･･８名によるラウンドロビン方式にて総当たり１回戦７Ｇ及びP･M１Ｇを投球し\n　　　　　　　　　　　　　　　　　 トータルポイントにて上位４名をＴＶ決勝へ選出\n　　　　　　　　　　　 ＴＶ決勝･･４名によるステップラダー方式にて最終順位決定(トップシードが負けた場合､再決定戦あり)",
            'broadcast_text' => "スカイＡ「パーフェクトボウリング」にて放送　※放送日時は都合により変更になる場合があります｡\n○７月２４日(土)２０：００～２２：００　準決勝５Ｇ\n○７月２５日(土)１３：００～１６：１５　決勝ラウンドロビン\n○７月２６日(日)１３：００～１４：４５　決勝ステップラダー\n※再放送および番組変更についてはスカイＡホームページをご参照ください。",
            'streaming_text' => 'JPBA公式チャンネル「JPBA LIVE」 及び 「BOWLING ch」(最終日のみ) にてライブ配信',
            'winner_headline' => "初優勝・初タイトル\n石田万音との同期(55期)対決を制し涙の初Ｖ！",
            'step_ladder_winner_note' => '初優勝・初タイトル！',
            'winner_record' => '優勝ボール･･TRACK Bowling キネティック・サファイア・アイス(サンブリッジ)',
            'winner_prize_display' => "2,000,000\n(副賞 500,000 含む)",
            'awards_text' => "☆ 優勝副賞(賞金)･･･５００,０００円(提供:小泉製麻(株))\n☆ 優勝副賞･･･真珠のネックレス･ピアスセット(提供:今啓パール(株))",
            'previous_results_text' => "金子の今季('26)及び昨季('25)主な成績\n2026年　○Glicoセブンティーンアイス杯第13回プロアマ　２位\n　　　　○スカイＡカップ2026プロボウリングレディース新人戦　９位\n　　　　○KISHIKAGAKU GROUP･ピュアフーズ岸プレゼンツ　１７位\n　　　　○中日杯2026東海オープン　１９位\n2025年　○スカイＡカップ2025プロボウリングレディース新人戦　２位\n　　　　○さわやかカップ2025第32回千葉オープン女子　７位\n　　　　○｢HANDA CUP｣･第57回全日本女子プロボウリング選手権大会　８位\n　　　　○Glicoセブンティーンアイス杯第12回プロアマ　８位\n　　　　○第47回STORMジャパンオープンボウリング選手権　９位",
            'perfect_text' => "大会１号 JPBA公認 第378号 石田 万音（予選９Ｇ目 5･6L）※自身５回目の公認300達成！\nパーフェクト賞･･･50,000円（提供：(株)サザンモール六甲）",
            'amateur_text' => 'ベストアマチュア賞･･･澤田 枇奈 選手 16歳（ＪＢ･津グランドボウル）総合第４０位',
            'round_robin_score_overrides' => [
                5 => '+ 660',
                6 => '+ 589',
                7 => '+ 543',
                8 => '+ 513',
            ],
        ];

        foreach ($targets as $target) {
            $snapshot = json_decode((string) ($target->template_snapshot ?? ''), true);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $snapshot['result_format'] = array_merge(
                (array) ($snapshot['result_format'] ?? []),
                $resultFormatDefaults
            );

            $awards = json_decode((string) ($target->award_highlights ?? ''), true);
            $awards = is_array($awards) ? $awards : [];
            if (! collect($awards)->contains(fn (array $award): bool => (string) ($award['player'] ?? '') === '石田 万音')) {
                $awards[] = [
                    'type' => 'perfect',
                    'player' => '石田 万音',
                    'game' => '予選9G目',
                    'lane' => '5･6L',
                    'note' => 'JPBA公認第378号・自身5回目／パーフェクト賞50,000円',
                    'title' => '大会第1号パーフェクトゲーム',
                    'photo' => null,
                ];
            }

            $cards = json_decode((string) ($target->result_cards ?? ''), true);
            $cards = is_array($cards) ? $cards : [];
            if (! collect($cards)->contains(fn (array $card): bool => str_contains((string) ($card['title'] ?? ''), 'ベストアマ'))) {
                $cards[] = [
                    'title' => 'ベストアマチュア賞',
                    'player' => '澤田 枇奈',
                    'balls' => null,
                    'note' => '16歳（ＪＢ･津グランドボウル）総合第40位',
                    'url' => null,
                    'photo' => null,
                    'pdf' => null,
                ];
            }

            DB::table('tournaments')
                ->where('id', $target->id)
                ->update([
                    'tournament_result_format_version_id' => $versionId,
                    'result_flow_type' => 'prelim_to_rr_to_final',
                    'round_robin_qualifier_count' => 8,
                    'round_robin_win_bonus' => 30,
                    'round_robin_tie_bonus' => 15,
                    'round_robin_position_round_enabled' => true,
                    'host' => '株式会社サザンモール六甲',
                    'support' => '神戸市 ／ 神戸市教育委員会 ／ 神戸市灘区役所 ／ (公財)神戸市スポーツ協会 ／ 神戸商工会議所 ／ (株)神戸新聞社 ／ (株)デイリースポーツ ／ (株)サンテレビジョン ／ (株)ラジオ関西',
                    'authorized_by' => '(公社)日本プロボウリング協会',
                    'prize' => '賞金総々額 7,500,000円',
                    'broadcast' => 'スカイＡ「パーフェクトボウリング」',
                    'streaming' => 'JPBA LIVE ／ BOWLING ch',
                    'template_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'award_highlights' => json_encode($awards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'result_cards' => json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);

            foreach ([
                ['category' => 'host', 'name' => '株式会社サザンモール六甲', 'sort_order' => 0],
                ['category' => 'support', 'name' => '神戸市', 'sort_order' => 0],
                ['category' => 'support', 'name' => '神戸市教育委員会', 'sort_order' => 1],
                ['category' => 'support', 'name' => '神戸市灘区役所', 'sort_order' => 2],
                ['category' => 'support', 'name' => '(公財)神戸市スポーツ協会', 'sort_order' => 3],
                ['category' => 'support', 'name' => '神戸商工会議所', 'sort_order' => 4],
                ['category' => 'support', 'name' => '(株)神戸新聞社', 'sort_order' => 5],
                ['category' => 'support', 'name' => '(株)デイリースポーツ', 'sort_order' => 6],
                ['category' => 'support', 'name' => '(株)サンテレビジョン', 'sort_order' => 7],
                ['category' => 'support', 'name' => '(株)ラジオ関西', 'sort_order' => 8],
                ['category' => 'cooperation', 'name' => '神戸商工会議所 神戸スポーツ産業懇話会', 'sort_order' => 0],
                ['category' => 'authorized', 'name' => '(公社)日本プロボウリング協会', 'sort_order' => 0],
            ] as $organization) {
                DB::table('tournament_organizations')->updateOrInsert(
                    [
                        'tournament_id' => $target->id,
                        'category' => $organization['category'],
                        'name' => $organization['name'],
                    ],
                    [
                        'url' => null,
                        'sort_order' => $organization['sort_order'],
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $formatId = DB::table('tournament_result_formats')
            ->where('code', 'rokko_queens')
            ->value('id');
        if (! $formatId) {
            return;
        }

        $versionIds = DB::table('tournament_result_format_versions')
            ->where('tournament_result_format_id', $formatId)
            ->pluck('id');

        DB::table('tournaments')
            ->whereIn('tournament_result_format_version_id', $versionIds)
            ->update(['tournament_result_format_version_id' => null]);

        DB::table('tournament_result_format_versions')
            ->where('tournament_result_format_id', $formatId)
            ->delete();
        DB::table('tournament_result_formats')->where('id', $formatId)->delete();
    }
};
