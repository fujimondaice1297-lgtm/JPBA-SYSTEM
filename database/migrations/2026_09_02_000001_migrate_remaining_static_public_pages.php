<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 現行公開サイトから、更新可能な固定ページとしてそのまま移せる内容を内部化する。
     * source_url は移行元を示す非公開監査情報であり、公開画面のリンクには使用しない。
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->pages() as $page) {
            DB::table('managed_public_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'is_published' => true,
                    'published_at' => $now,
                    'source_checked_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('managed_public_pages')->whereIn('slug', array_column($this->pages(), 'slug'))->delete();
    }

    /** @return array<int,array<string,mixed>> */
    private function pages(): array
    {
        return [
            [
                'slug' => 'support',
                'title' => '法人・個人賛助会員',
                'navigation_group' => 'support',
                'sort_order' => 10,
                'source_url' => 'https://www.jpba1.jp/support/index.html',
                'body_html' => <<<'HTML'
<p>公益社団法人日本プロボウリング協会では、プロボウリングの普及・振興と公益事業を支えてくださる法人・個人の賛助会員を募集しています。</p>
<h2>賛助会員のご案内</h2>
<ul><li><a href="/pages/support-corporate">法人賛助会員のご案内</a></li><li><a href="/pages/support-individual">個人賛助会員のご案内</a></li></ul>
<p>内容や申込みについてのお問い合わせは、協会事務局までご連絡ください。</p>
HTML,
            ],
            [
                'slug' => 'support-corporate',
                'title' => '法人賛助会員のご案内',
                'navigation_group' => 'support',
                'sort_order' => 20,
                'source_url' => 'https://www.jpba1.jp/support/corporation.html',
                'body_html' => <<<'HTML'
<p>公益社団法人日本プロボウリング協会では、本協会の目的と事業にご賛同いただける法人賛助会員を募集しています。</p>
<h2>会員特典</h2>
<ul><li>法人賛助会員証を発行します。</li><li>メディアガイド及びカレンダーを各5部進呈します。</li><li>月刊ボウリング・マガジンを1年間進呈します。</li><li>主催大会へご招待します。</li><li>JPBA公式サイトで法人名・所在地・連絡先・ホームページをご紹介します。</li></ul>
<h2>会費</h2>
<p>1口 年額100,000円（入会金なし）。会員期間は毎年1月から12月までです。</p>
<h2>法人賛助会員一覧</h2>
<table><thead><tr><th>法人名</th><th>所在地・連絡先</th></tr></thead><tbody>
<tr><td><a href="https://www.meijiyasuda.co.jp/">明治安田生命保険相互会社</a></td><td>〒144-0052 東京都大田区蒲田4-21-14 明治安田生命蒲田ビル3階 品川支社 蒲田営業所<br>03-3494-0995</td></tr>
<tr><td><a href="http://www.sport-bowling.co.jp/">株式会社スポルト</a></td><td>〒460-0007 愛知県名古屋市中区新栄2-45-26 スポルト名古屋1F<br>052-238-0315</td></tr>
<tr><td><a href="http://www.round1.co.jp/">株式会社ラウンドワンジャパン</a></td><td>〒542-0076 大阪府大阪市中央区難波5丁目1番60号 なんばスカイオ23F<br>06-6647-6600</td></tr>
<tr><td><a href="https://kd2nd.co.jp/">株式会社ケーダッシュセカンド</a></td><td>〒160-0023 東京都新宿区西新宿1-23-7 新宿ファーストウエスト7階<br>03-3348-8989</td></tr>
<tr><td><a href="https://www.mk-group.co.jp/">MKグループ</a></td><td>〒601-8432 京都市南区西九条東島町63-1<br>075-555-3132</td></tr>
<tr><td><a href="https://www.korona.co.jp/">株式会社コロナワールド</a></td><td>〒485-0048 愛知県小牧市間々本町200<br>0568-73-1610</td></tr>
<tr><td><a href="https://yoshikawa-cpta.tkcnf.com/">𠮷川高広税理士事務所</a></td><td>〒170-0012 東京都豊島区上池袋3-39-22<br>03-5980-7491</td></tr>
<tr><td><a href="https://sunsquare.jp/bowling/">サンスクエアボウル</a></td><td>〒114-0002 東京都北区王子1-4-1<br>03-3927-0200</td></tr>
<tr><td><a href="http://parklanes.jp/">相模原パークレーンズ</a></td><td>〒252-0231 神奈川県相模原市中央区相模原2-7-4<br>042-755-1110</td></tr>
<tr><td><a href="https://www.japanbowlingpromotion.com/">株式会社ジャパンボウリングプロモーション</a></td><td>〒358-0026 埼玉県入間市小谷田1262-2 fuji BOWL内<br>04-2934-4142</td></tr>
<tr><td><a href="http://ltb.co.jp/">株式会社LTB</a></td><td>〒819-0005 福岡市西区内浜1-7-1 北山興産ビル105号室<br>092-881-3664</td></tr>
<tr><td><a href="http://takeuchi-clinic.girly.jp/">とちおとめ会</a></td><td>〒329-2162 栃木県矢板市末広町42-9 渡辺セントラル歯科内<br>0287-43-8020</td></tr>
<tr><td><a href="http://www.k-k-b.co.jp/">北小金ボウル</a></td><td>〒270-0011 千葉県松戸市根木内249-7<br>047-341-9191</td></tr>
<tr><td>JJコーポレーション</td><td>〒567-0852 大阪府茨木市小柳町3-6 SKYファーストF<br>072-657-0117</td></tr>
<tr><td><a href="https://sap-f.com/">サンケイボウル</a></td><td>〒620-0882 京都府福知山市字堀小字下高田2346<br>0773-23-0300</td></tr>
<tr><td><a href="http://www.ltbbowl.com/">LTB水前寺ボウル</a></td><td>〒862-0956 熊本県熊本市中央区水前寺公園6-8<br>096-383-0195</td></tr>
<tr><td><a href="https://www.misuzu.jp/">ミスズガーデン株式会社</a></td><td>〒731-5124 広島県広島市佐伯区皆賀4-19-6<br>082-922-5161</td></tr>
<tr><td><a href="https://pro-cera.com/">プロショップセラ</a></td><td>〒108-0074 東京都港区高輪4丁目10-30 品川プリンスホテルボウリングセンター1F<br>03-5422-9658</td></tr>
<tr><td><a href="https://bowlstar.jp/">株式会社ボウルスター</a></td><td>〒224-0024 神奈川県横浜市都筑区東山田町1610-1<br>045-514-9607</td></tr>
<tr><td><a href="https://www.landtradingllc.com/">Land Trading LLC ランドトレーディング</a></td><td>〒216-0002 神奈川県川崎市宮前区東有馬1-3-16<br>044-982-9200</td></tr>
</tbody></table>
<p>入会・掲載内容の変更は<a href="/contact">協会事務局へお問い合わせください</a>。</p>
HTML,
            ],
            [
                'slug' => 'support-individual',
                'title' => '個人賛助会員のご案内',
                'navigation_group' => 'support',
                'sort_order' => 30,
                'source_url' => 'https://www.jpba1.jp/support/person.html',
                'body_html' => <<<'HTML'
<p>公益社団法人日本プロボウリング協会では、本協会の目的と事業にご賛同いただける個人賛助会員を募集しています。</p>
<h2>会員特典</h2>
<ul><li>個人賛助会員証と会員ワッペンを発行します。</li><li>メディアガイド及びカレンダーを進呈します。</li><li>月刊ボウリング・マガジンを1年間進呈します。</li><li>主催大会へご招待します。</li><li>対象大会・催事で会員向け特典をご案内します。</li></ul>
<h2>会費</h2>
<p>個人会員は年額20,000円、同居家族会員は年額10,000円（入会金なし）。会員期間は毎年1月から12月までです。</p>
<h2>申込書</h2>
<ul><li><a href="/documents/jpba/individual-support-application.pdf">個人賛助会員申込書（PDF）</a></li><li><a href="/documents/jpba/individual-support-application.docx">個人賛助会員申込書（Word）</a></li></ul>
<p>必要事項を記入し、協会事務局へお送りください。ご不明点は<a href="/contact">お問い合わせページ</a>からご確認ください。</p>
HTML,
            ],
            [
                'slug' => 'instructor-flow',
                'title' => 'インストラクター資格取得までの流れ',
                'navigation_group' => 'instructor',
                'sort_order' => 40,
                'source_url' => 'https://www.jpba1.jp/instructor/flow.html',
                'body_html' => <<<'HTML'
<p>JPBAが認定するインストラクター資格の区分と、資格取得までの流れをご案内します。</p>
<p><a href="/documents/jpba/instructor-license-flow.pdf">インストラクター資格取得までの流れ（PDF）</a></p>
<p>開催年度、受講条件、申込期間及び提出書類は、一般公開INFORMATIONの当年度案内もあわせてご確認ください。</p>
HTML,
            ],
            [
                'slug' => 'instructor-plan',
                'title' => 'インストラクター講習会年間計画',
                'navigation_group' => 'instructor',
                'sort_order' => 50,
                'source_url' => 'https://www.jpba1.jp/instructor/plan.html',
                'body_html' => <<<'HTML'
<p>公認A・B・C級、認定1級・2級、プロ・インストラクターの資格取得講習会、専門講習会及び研修会を年度ごとに実施します。</p>
<h2>日程の確認</h2>
<p>開催日、会場、申込期間、受講料、持参物及び申込方法は、当年度の一般公開INFORMATIONに掲載します。内容は変更になる場合があるため、申込前に最新案内をご確認ください。</p>
<h2>資格名簿</h2>
<p>現在登録されているインストラクターは、インストラクター検索ページで資格区分別に確認できます。</p>
HTML,
            ],
            [
                'slug' => 'instructor-school-about',
                'title' => 'JPBA公認ボウリングスクールについて',
                'navigation_group' => 'instructor',
                'sort_order' => 60,
                'source_url' => 'https://www.jpba1.jp/instructor/school.html',
                'body_html' => <<<'HTML'
<p>JPBA公認ボウリングスクールは、初心者を中心に、ボウリングの基本、マナー、安全な楽しみ方を正しく伝えるためのスクールです。</p>
<h2>開講できるインストラクター</h2>
<p>JPBA公認A・B・C級インストラクター、プロ・インストラクター及び認定1級インストラクターが対象です。開講説明を受け、所定の申請を行ってスクール開講資格を取得します。</p>
<h2>申請・教材</h2>
<ul><li><a href="/documents/jpba/instructor-school-application.pdf">JPBA公認ボウリングスクール開講申請書（PDF）</a></li><li><a href="/documents/jpba/instructor-school-tools-order.pdf">スクール用ツール注文書（PDF）</a></li></ul>
<p>開催中のスクールは「スクール情報」と一般公開INFORMATIONでご案内します。</p>
HTML,
            ],
            [
                'slug' => 'instructor-signage',
                'title' => 'インストラクター ステッカー・ワッペン',
                'navigation_group' => 'instructor',
                'sort_order' => 70,
                'source_url' => 'https://www.jpba1.jp/instructor/signage.html',
                'body_html' => <<<'HTML'
<p>JPBAは、登録インストラクターが所属・活動するボウリング場で掲示できるステッカーと、資格区分を示すワッペンを用意しています。</p>
<h2>ボウリング場掲示用ステッカー</h2>
<table><tbody><tr><th>A級・マスター</th><td><img src="/images/jpba/instructor/Sticker_A.jpg" alt="A級・マスター ステッカー"></td></tr><tr><th>B級・C級</th><td><img src="/images/jpba/instructor/Sticker_BC.jpg" alt="B級・C級 ステッカー"></td></tr><tr><th>認定インストラクター</th><td><img src="/images/jpba/instructor/Sticker_N.jpg" alt="認定インストラクター ステッカー"></td></tr></tbody></table>
<h2>資格ワッペン</h2>
<table><tbody><tr><th>A級・マスター</th><td><img src="/images/jpba/instructor/Wappen_A.jpg" alt="A級・マスター ワッペン"></td></tr><tr><th>B級・C級</th><td><img src="/images/jpba/instructor/Wappen_BC.jpg" alt="B級・C級 ワッペン"></td></tr><tr><th>認定インストラクター</th><td><img src="/images/jpba/instructor/Wappen_02.jpg" alt="認定インストラクター ワッペン"></td></tr><tr><th>プロ・インストラクター</th><td><img src="/images/jpba/instructor/Wappen_03.jpg" alt="プロ・インストラクター ワッペン"></td></tr></tbody></table>
HTML,
            ],
        ];
    }
};
