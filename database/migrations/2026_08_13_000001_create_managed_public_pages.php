<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_public_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->longText('body_html');
            $table->text('source_url')->nullable();
            $table->string('navigation_group', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('source_checked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['is_published', 'navigation_group', 'sort_order'],
                'managed_public_pages_publish_navigation_index'
            );
        });

        $now = now();
        DB::table('managed_public_pages')->insert($this->initialPages($now));
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_public_pages');
    }

    /**
     * 現行公式サイトで公開中の、DB化に適した固定ページを初期値として保存する。
     * 外部サイト閉鎖後も本文が残り、以後は管理画面を正本として更新できる。
     *
     * @return array<int, array<string, mixed>>
     */
    private function initialPages($now): array
    {
        $pages = [
            [
                'slug' => 'contact',
                'title' => 'お問い合わせ',
                'navigation_group' => 'footer',
                'sort_order' => 10,
                'source_url' => 'https://www.jpba1.jp/inquiry/index.html',
                'body_html' => <<<'HTML'
<p>公益社団法人日本プロボウリング協会へのお問合せ・ご要望などは、協会事務局までご連絡ください。</p>
<h2>お問い合わせ時にお知らせいただく内容</h2>
<ul><li>氏名（必須）</li><li>会社名</li><li>電話番号</li><li>メールアドレス（必須）</li><li>ご用件（大会エントリー、プロ資格、賛助会員、取材、承認大会・イベント、その他）</li><li>お問い合わせ内容</li></ul>
<p>個人情報の取り扱いについては、プライバシーポリシーをご確認ください。</p>
<h2>協会事務局</h2>
<p>公益社団法人 日本プロボウリング協会<br>〒105-0023 東京都港区芝浦1-13-10 第三東運ビル2F<br>TEL. 03-6436-0310<br>FAX. 03-3454-6140<br>受付時間：平日10時～17時</p>
HTML,
            ],
            [
                'slug' => 'media',
                'title' => '取材のお申込み',
                'navigation_group' => 'footer',
                'sort_order' => 20,
                'source_url' => 'https://www.jpba1.jp/media/index.html',
                'body_html' => <<<'HTML'
<p>大会の取材については、感染予防対策及びセキュリティ強化のため、会場への出入りを厳密に管理させていただきたく、取材にお越しになる方に下記ご協力をお願いしております。大変恐れ入りますがご協力のほどお願いいたします。</p>
<h2>1．取材申請について</h2>
<p>取材をご希望の際は、「取材時遵守事項」をお読みいただき、JPBA事務局に「取材申請書」を以てお申込みください。申請期限は原則として開催14日前までとさせて頂きます。申請期限を過ぎた場合はお受けできない場合がございます。</p>
<ul><li><a href="https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf">取材時遵守事項（PDF）</a></li><li><a href="https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf">取材申請書（PDF）</a></li><li><a href="https://ws.formzu.net/fgen/S86209866/">取材申請書フォーム</a></li></ul>
<h2>2．申請受理について</h2>
<p>取材申請書の内容を審査させていただきます。取材内容の使用用途、掲載用途などが不明瞭な場合はお断りする場合がございます。その際はご連絡いたします。</p>
<h2>3．会場受付について</h2>
<p>入場に際し手続きがある場合は、受付の指示に従ってお入りください。</p>
<p>以上、大変お手数ですが、ご理解・ご協力いただけますようお願いいたします。</p>
HTML,
            ],
            [
                'slug' => 'commerce',
                'title' => '特定商取引法に基づく広告',
                'navigation_group' => 'footer',
                'sort_order' => 30,
                'source_url' => 'https://www.jpba1.jp/ovservance/index.html',
                'body_html' => <<<'HTML'
<table><tbody><tr><th>事業者名</th><td>公益社団法人 日本プロボウリング協会（JPBA）</td></tr><tr><th>所在地</th><td>〒105-0023 東京都港区芝浦1-13-10 第三東運ビル2F</td></tr><tr><th>販売責任者</th><td>谷口 健</td></tr><tr><th>販売価格</th><td>都度、該当箇所に表記します。</td></tr><tr><th>その他の料金</th><td>送料、現金書留郵便料及び手数料</td></tr><tr><th>代金の支払時期</th><td>現金書留（前払い）</td></tr><tr><th>代金の支払方法</th><td>現金（現金書留にて郵送）</td></tr><tr><th>商品の引渡時期</th><td>現金：即時<br>現金書留：現金書留到着後の翌日以降<br>但し、商品の在庫が無い場合は都度ご相談とさせて頂きます。</td></tr><tr><th>問合せ時間</th><td>平日午前10時～午後5時</td></tr></tbody></table>
<h2>返品について</h2>
<p>ご注文内容に対してお送りした商品が違った場合や、到着時に商品破損、汚損などがあった場合は、弊会の責任として個別に対応させて頂きます。その際の返品に掛かる送料は、弊会負担にて返送して頂きます。何か問題がございましたら、商品到着後7日以内にお知らせください。</p>
<p>商品到着後のお客様のご都合によるキャンセルの場合は、その旨速やかにご連絡ください。その際の返品に掛かる送料は、お客様負担とさせていただきます。返金が発生する場合は、返送された商品を確認した後の対応とさせていただきます。返金に掛かる手数料もしくは郵送料などは、お客様負担とさせていただきます。</p>
<p>商品を使用した後、或いは商品到着後、未使用でも7日を過ぎた場合のキャンセルは、原則としてお断りいたします。</p>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'プライバシーポリシー',
                'navigation_group' => 'footer',
                'sort_order' => 40,
                'source_url' => 'https://www.jpba1.jp/policy/index.html',
                'body_html' => <<<'HTML'
<p>公益社団法人日本プロボウリング協会（以下、「当会」といいます。）は、事業の適正な運営に資する上で個人情報の適切な保護と取扱いが重要であると認識し、個人情報保護に関するプライバシー・ポリシーを定め、これを実行します。</p>
<h2>1．法令等の遵守</h2><p>当会は、個人情報を取り扱う際に、「個人情報の保護に関する法律」をはじめ、個人情報保護に関する関係諸法令、各省庁ガイドライン及び東京都が定める条例その他の規範を遵守します。</p>
<h2>2．個人情報の取得</h2><p>当会は、個人情報の取得にあたっては、利用目的を明確にした上で、必要な範囲において、適正かつ適法な手段により個人情報を取得します。また、あらかじめご本人の同意を得ることなく要配慮個人情報は取得しません。</p>
<h2>3．個人情報の安全管理措置</h2><p>当会は、取り扱う個人情報を正確かつ最新の状態で保管・管理するよう努めるとともに、個人情報の漏えい、滅失又はき損等を防止するため、適切な安全管理措置を講じます。また、従業者や委託先を適切に監督します。</p>
<h2>4．個人情報の第三者への提供</h2><p>当会は、法令等に基づく場合を除き、あらかじめご本人の同意を得ることなく、保有する個人情報を第三者に対して提供しません。ただし、当会のサービス提供に必要な業務を委託する場合、必要最低限の個人データを十分に保護された方法で提供する場合がございます。</p>
<h2>5．個人情報の利用目的</h2><p>個人情報をご提供いただく場合、その目的を明示又は公表します。また、明示又は公表した目的以外に無断で利用することはありません。</p>
<h2>6．開示請求等の手続き</h2><p>当会は、ご本人から利用目的の通知及び内容の開示を求められた場合、法令及び当会が定める手続に従って対応します。訂正、追加、削除、利用停止、消去又は第三者提供の停止などの請求についても、適切かつ迅速な対応を行うよう努めます。開示手数料は実費といたします。</p>
<h2>7．問い合わせへの対応</h2><p>公益社団法人日本プロボウリング協会<br>電話番号 03-6436-0310<br>受付時間 平日10時～17時</p>
<h2>8．継続的改善</h2><p>当会は、情報技術の発展や社会的要請の変化などを踏まえて、個人情報保護のための管理体制及び取組みについて、継続的に見直し、その改善に努めます。</p>
HTML,
            ],
            [
                'slug' => 'president',
                'title' => '会長挨拶',
                'navigation_group' => 'association',
                'sort_order' => 10,
                'source_url' => 'https://www.jpba1.jp/association/president.html',
                'body_html' => <<<'HTML'
<p><strong>公益社団法人 日本プロボウリング協会<br>会長 谷口 健</strong></p>
<p>公益社団法人日本プロボウリング協会の会長として4期目を拝命しました 谷口 健（たにぐち たけし）でございます。</p>
<p>日頃よりプロボウリングに多大なるご支援ご協力を賜り 心より御礼申しあげます。</p>
<p>3か年に亙る感染予防対策に加え、経済・海外情勢の急激な変化など 弊会の運営にも影響がございましたが、2023年 各事業の更なる発展を目指し、新たな施策も含め 鋭意取り組む所存でございます。</p>
<p>スポンサーの皆様、関係各位、プロボウラーを応援してくださる多くのファンの皆様、引き続きのご支援を賜りますよう、そしてこれからのプロボウリングにますますご期待頂きますよう お願い申しあげます。</p>
<p>2023年3月</p>
HTML,
            ],
            [
                'slug' => 'organization-chart',
                'title' => '運営機構図',
                'navigation_group' => 'association',
                'sort_order' => 20,
                'source_url' => 'https://www.jpba1.jp/association/map.html',
                'body_html' => <<<'HTML'
<p>公益社団法人 日本プロボウリング協会の運営機構です。</p>
<h2>代議員総会</h2><p>代議員（全国21地区39名）</p>
<h2>理事会</h2><p>業務執行理事（正会員10名）、学識経験理事4名、相談役、監事</p>
<h2>委員会・組織</h2><ul><li>資格審査委員会</li><li>トーナメント委員会</li><li>インストラクター委員会</li><li>国際委員会</li><li>開発委員会</li><li>広報委員会</li><li>総務委員会</li><li>基本問題研究会</li><li>事務局</li></ul>
<p>※2025年3月24日現在</p>
HTML,
            ],
            [
                'slug' => 'instructor-overview',
                'title' => 'インストラクター制度概要',
                'navigation_group' => 'instructor',
                'sort_order' => 10,
                'source_url' => 'https://www.jpba1.jp/instructor/overview.html',
                'body_html' => <<<'HTML'
<p>広範な国民にスポーツへの参加意識を高め、不特定かつ多数の者がボウリングを通じて心身の健全な発達と豊かな人間性を涵養するには、基本から正しく指導するインストラクターが必要である。</p>
<p>本事業は、インストラクターを志すプロボウラー及びプロボウラー以外の一般のインストラクターを志す者に講習会及びテストを実施し、合格者に資格の付与を行い、併せて認定したインストラクターに継続的に講習・研修を行い質の向上はかる。</p>
<p>実施する公益目的事業は、不特定多数の者の利益の増進に寄与するものである。</p>
<ol><li>高齢者の福祉の増進を目的とする事業</li><li>児童又は青少年の健全な育成を目的とする事業</li><li>教育、スポーツ等を通じて国民の心身の健全な発達に寄与し、又は、豊かな人間性を涵養することを目的とする事業</li><li>地域社会の健全な発展を目的とする事業</li><li>その他前各号の他、公益に関する事業として政令で定めるもの</li></ol>
<h2>インストラクター事業の推進</h2><ul><li>公認A・B・C級プロインストラクター資格取得講習会</li><li>認定1・2級インストラクター資格取得講習会</li><li>プロ・インストラクター資格取得実技試験</li><li>専門講習会、健康ボウリングスクール講師認定講習会の開催</li></ul>
<h2>スクール開催事業</h2><ul><li>JPBA公認ボウリングスクール</li><li>春・夏・冬休み全国ジュニアボウリングスクール</li><li>ジュニアボウリングキャラバン</li><li>「健康」をテーマとしたボウリングスクール</li></ul>
HTML,
            ],
            [
                'slug' => 'instructor-textbook',
                'title' => 'インストラクターテキスト販売',
                'navigation_group' => 'instructor',
                'sort_order' => 20,
                'source_url' => 'https://www.jpba1.jp/instructor/textbook.html',
                'body_html' => <<<'HTML'
<p>インストラクター用のテキスト、教本、資料等の販売を致します。教室、スクールなどにお役立てください。</p>
<h2>販売中テキスト</h2><ol><li>「脳力トレーニングドリル」／監修：田中喜代次 筑波大学名誉教授／A5サイズ 全87ページ／1冊200円（税別）</li><li>「健幸華齢ボウリングQ&amp;A」／監修：公益社団法人日本プロボウリング協会／A5サイズ 全153ページ／1冊200円（税別）</li><li>「高齢ボウリング愛好家の認知機能に関する質問紙調査の概要と結果」／報告者（代表）：田中喜代次 筑波大学名誉教授／A4サイズ 全40ページ／1冊200円（税別）</li><li>「機能解剖学」／渡曾 公治 帝京平成大学教授</li></ol>
<h2>購入方法</h2><p>スクール開催用にまとめてご購入される場合など、ご要望に応じて対応いたしますので、インストラクター委員会までご連絡ください。</p>
<p><strong>振込先</strong><br>三井住友銀行ベイサイド支店 普通 7747908<br>公益社団法人日本プロボウリング協会</p>
<p><strong>お問い合わせ</strong><br>（公社）日本プロボウリング協会 インストラクター委員会<br>〒105-0023 東京都港区芝浦1-13-10 第三東運ビル2F<br>TEL. 03-6436-0310 / FAX. 03-3454-6140<br>メールアドレス i.c@jpba.or.jp</p>
HTML,
            ],
            [
                'slug' => 'instructor-school',
                'title' => 'スクール情報',
                'navigation_group' => 'instructor',
                'sort_order' => 30,
                'source_url' => 'https://www.jpba1.jp/instructor/school_guide.html',
                'body_html' => <<<'HTML'
<h2>スクール情報</h2>
<p>JPBA公認A・B・C級インストラクター（プロボウラー及びプロ・インストラクター）及び認定1級インストラクターが開講する、JPBA公認ボウリングスクールのご案内です。</p>
<p>教室・スクールの開催案内は、一般公開INFORMATIONにも掲載します。開催日時、会場、参加条件及び申込方法は各案内をご確認ください。</p>
<p>各ボウリング場で開催される教室についてのお問い合わせは、各会場へお願いします。</p>
HTML,
            ],
            [
                'slug' => 'pro-test-guide',
                'title' => 'プロテスト実施概要',
                'navigation_group' => 'protest',
                'sort_order' => 10,
                'source_url' => 'https://www.jpba1.jp/protest/guide.html',
                'body_html' => <<<'HTML'
<h2>プロテスト受験の流れ（2027年度以降）</h2>
<p>プロボウラー資格取得テストを受験するにあたり、実技テストを受ける前に2つの条件が必要です。</p>
<ol><li>前年度9月から11月に行われる認定2級インストラクター資格取得講習会を受講し、筆記テストに合格すること。</li><li>2級インストラクターに合格し、登録すること。</li></ol>
<p>受験資格に2級インストラクターを保持することが加わります。2026年までの受験者講習会受講者は、有効期限まで受験可能です。</p>
<h2>試験方法</h2><p>第1次・第2次テスト（実技）は従来通りです。実技テスト合格者の研修では、前年度講習会で筆記テストを行うため、筆記テストを行いません。</p>
<p>資格審査委員会での協議により、受験要項が変更になる場合があります。最新の開催日、申請期間、提出書類、受験料及びゲーム数は、当年度のINFORMATIONと実施要項をご確認ください。</p>
HTML,
            ],
        ];

        return array_map(function (array $page) use ($now): array {
            return array_merge($page, [
                'is_published' => true,
                'published_at' => $now,
                'source_checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $pages);
    }
};
