<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_result_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tournament_result_format_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_result_format_id')
                ->constrained('tournament_result_formats')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('template_disk', 32)->default('resource');
            $table->string('template_path');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['tournament_result_format_id', 'version_no'],
                'result_format_version_unique'
            );
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            $table->foreignId('tournament_result_format_version_id')
                ->nullable()
                ->after('tournament_template_version_id')
                ->constrained('tournament_result_format_versions')
                ->nullOnDelete();
        });

        $now = now();
        $formatId = DB::table('tournament_result_formats')->insertGetId([
            'name' => 'ピュアフーズ岸方式',
            'code' => 'purefoods_kishi',
            'description' => 'ピュアフーズ岸プレゼンツ レディースプロボウリングトーナメント用の4ページ最終成績形式',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $versionId = DB::table('tournament_result_format_versions')->insertGetId([
            'tournament_result_format_id' => $formatId,
            'version_no' => 1,
            'template_disk' => 'resource',
            'template_path' => 'resources/tournament_result_formats/purefoods_kishi_v1.xlsx',
            'notes' => '2025年・2026年公式最終成績PDFを比較して作成した初版',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('tournaments')
            ->where('year', 2026)
            ->where('name', 'like', '%ピュアフーズ岸%')
            ->update([
                'tournament_result_format_version_id' => $versionId,
                'result_flow_type' => 'prelim_to_semifinal_to_single_elimination_to_final',
                'single_elimination_qualifier_count' => 12,
                'single_elimination_seed_source_result_code' => 'semifinal_total',
                'single_elimination_seed_policy' => 'standard',
                'host' => 'KISHIKAGAKU GROUP・ピュアフーズ岸株式会社',
                'support' => '鹿屋市 / 志布志市 / 垂水市 / 曽於市 / 肝付町 / 東串良町 / 大崎町 / 錦江町 / 南大隅町 / ボウリングマガジン / ボウリングジャーナル',
                'sponsor' => '各社',
                'supervisor' => 'KISHIKAGAKU GROUP・ピュアフーズ岸プレゼンツボウリングトーナメント実行委員会',
                'authorized_by' => '公益社団法人日本プロボウリング協会（A公認）',
                'updated_at' => $now,
            ]);

        $resultFormatDefaults = [
            'english_title' => 'KISHIKAGAKU GROUP・PUREFOODS KISHI PRESENTS LADIES PRO-BOWLING TOURNAMENTO 2026',
            'tagline' => '～「2026シーズン開幕戦」女子プロTOP36が鹿児島県鹿屋に集結！～',
            'schedule_text' => "3月3日(火) 予選12G：参加プロ36名にて予選12Gを投球し上位24名を準決勝へ選出\n3月4日(水) 準決勝6G：24名で準決勝6Gを投球し通算18Gにて上位12名を決勝トーナメントへ選出\n決勝トーナメント：12名によるトーナメント方式",
            'broadcast_text' => "スカイA「パーフェクトボウリング」にて放送\n3月20日(金)14:00～17:00 準決勝6G\n3月22日(日)14:00～17:15 決勝トーナメント",
            'winner_roman_name' => 'Sato Masami',
            'winner_headline' => "大会初優勝\n今季開幕戦V・通算6勝目",
            'winner_record' => "優勝ボール：900GLOBAL クルーズ・ブラックナイト\nナノデス・アキュドライブ シックス",
            'winner_prize_display' => "2,500,000\n副賞 500,000",
            'awards_text' => "☆ 優勝副賞（賞金）・・・500,000円（提供：ピュアフーズ岸株式会社）\n☆ 優勝副賞・・・きりしま悠久の宿「一心」1泊2日ペア宿泊券（提供：ピュアフーズ岸株式会社）\n☆ 優勝副賞・・・鹿屋市賞（提供：鹿児島県鹿屋市）\n　○鹿児島県大隅産「うなぎ蒲焼」\n　○「かごしま黒豚お肉セット」\n　○鹿屋市漁協の鹿児島県認定ブランド魚「かのやカンパチ」\n　○鹿屋をまるっと味わえる「特産品の詰め合わせ」\n　○かのやばら園「プリザーブドフラワー」\n☆ 第2位～第4位副賞・・・鹿児島黒毛和牛「日本一」（提供：ピュアフーズ岸株式会社）\n☆ 第2位副賞・・・鹿屋市賞（提供：鹿児島県鹿屋市）\n　○鹿児島県大隅産「うなぎ蒲焼」\n　○「かごしま黒豚お肉セット」\n　○鹿屋市漁協の鹿児島県認定ブランド魚「かのやカンパチ」\n☆ 第3位副賞・・・鹿屋市賞（提供：鹿児島県鹿屋市）\n　○「かごしま黒豚お肉セット」\n　○鹿屋市漁協の鹿児島県認定ブランド魚「かのやカンパチ」\n☆ 第4位副賞・・・鹿屋市賞（提供：鹿児島県鹿屋市）\n　○鹿屋をまるっと味わえる「特産品の詰め合わせ」\n☆ 第2位～第4位副賞・・・かのやばら園「プリザーブドフラワー」（提供：鹿児島県鹿屋市）",
            'previous_results_text' => "佐藤の昨シーズン('25)主な成績\n第55回全日本女子プロ選手権大会 第6位\n第47回ジャパンオープン 第7位\nGlicoセブンティーンアイス杯第12回 第10位\n大岡産業レディース2025 第21位\n第41回六甲クイーンズ 第24位",
            'bracket_rules' => "決勝トーナメント：1回戦＆2回戦＝2Gトータルにて勝敗決定\n準決勝＆優勝決定戦＝1Gマッチ",
            'footnote' => '※準決勝通過者12名によるトーナメント方式（1位～4位は1回戦シード）',
        ];

        $targetTournaments = DB::table('tournaments')
            ->where('year', 2026)
            ->where('name', 'like', '%ピュアフーズ岸%')
            ->get(['id', 'template_snapshot']);

        foreach ($targetTournaments as $targetTournament) {
            $snapshot = json_decode((string) ($targetTournament->template_snapshot ?? ''), true);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $snapshot['result_format'] = $resultFormatDefaults;

            DB::table('tournaments')
                ->where('id', $targetTournament->id)
                ->update([
                    'template_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

            foreach ([
                ['category' => 'co_host', 'name' => '公益社団法人日本プロボウリング協会', 'sort_order' => 0],
                ['category' => 'cooperation', 'name' => '株式会社スカイA', 'sort_order' => 0],
                ['category' => 'cooperation', 'name' => '笠之原ボウリングセンター', 'sort_order' => 1],
                ['category' => 'cooperation', 'name' => 'JPBA九州南地区', 'sort_order' => 2],
                ['category' => 'cooperation', 'name' => '他', 'sort_order' => 3],
            ] as $organization) {
                DB::table('tournament_organizations')->updateOrInsert(
                    [
                        'tournament_id' => $targetTournament->id,
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
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tournament_result_format_version_id');
        });

        Schema::dropIfExists('tournament_result_format_versions');
        Schema::dropIfExists('tournament_result_formats');
    }
};
