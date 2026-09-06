<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ($this->pages() as $page) {
            DB::table('managed_public_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                $page + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('managed_public_pages')->whereIn('slug', array_column($this->pages(), 'slug'))->delete();
    }

    private function pages(): array
    {
        return [
            [
                'slug' => 'permanent-seed', 'title' => '永久シードプロ', 'navigation_group' => 'other', 'sort_order' => 310,
                'source_url' => 'https://www.jpba.or.jp/information/tournament/LEseed.html', 'source_checked_at' => '2026-09-05 00:00:00',
                'is_published' => true, 'published_at' => '2026-09-05 00:00:00',
                'body_html' => '<h2>永久シードプロとは</h2><p>JPBA主催・公認トーナメントすべてに出場する権利を有する者（もしくは出場資格を有する者）をいいます。</p><p><strong>獲得条件：</strong>JPBA公認トーナメント（個人戦）において、生涯優勝回数20勝以上。</p><h2>男子</h2><table><thead><tr><th>氏名</th><th>通算優勝</th><th>備考</th></tr></thead><tbody><tr><td>矢島 純一</td><td>41勝</td><td></td></tr><tr><td>酒井 武雄</td><td>37勝</td><td></td></tr><tr><td>西城 正明</td><td>35勝</td><td>退会</td></tr><tr><td>塚原 次雄</td><td>25勝</td><td></td></tr><tr><td>川添 奨太</td><td>23勝</td><td></td></tr><tr><td>保倉 義孝</td><td>22勝</td><td>故人</td></tr><tr><td>山本 勲</td><td>22勝</td><td></td></tr></tbody></table><h2>女子</h2><table><thead><tr><th>氏名</th><th>通算優勝</th><th>備考</th></tr></thead><tbody><tr><td>斉藤 志乃ぶ</td><td>75勝</td><td></td></tr><tr><td>須田 開代子</td><td>43勝</td><td>故人</td></tr><tr><td>並木 惠美子</td><td>36勝</td><td></td></tr><tr><td>時本 美津子</td><td>35勝</td><td></td></tr><tr><td>姫路 麗</td><td>34勝</td><td></td></tr><tr><td>中山 律子</td><td>33勝</td><td></td></tr><tr><td>杉本 勝子</td><td>28勝</td><td></td></tr><tr><td>金田 惠子</td><td>27勝</td><td>故人</td></tr><tr><td>稲橋 和枝</td><td>21勝</td><td></td></tr></tbody></table>',
            ],
            [
                'slug' => 'hall-of-fame', 'title' => '日本プロボウリング殿堂', 'navigation_group' => 'other', 'sort_order' => 320,
                'source_url' => 'https://www.jpba.or.jp/information/tournament/HallofFame.html', 'source_checked_at' => '2026-09-05 00:00:00',
                'is_published' => true, 'published_at' => '2026-09-05 00:00:00',
                'body_html' => '<p>2017年、協会創立50年の節目に、本協会の発展に大きく貢献した方々ならびに顕著な活躍をしたプレイヤーの功績を永久に称え顕彰するため、日本プロボウリング殿堂が創設されました。</p><h2>2025年度表彰</h2><p>半井 清 ／ 小林 万修</p><h2>2020年度表彰</h2><p>石原 章夫 ／ 栴檀 稔</p><h2>2019年度表彰</h2><p>塚原 次雄 ／ 谷口 健 ／ 稲橋 和枝</p><h2>2018年度表彰</h2><p>西城 正明 ／ 時本 美津子 ／ 金田 惠子</p><h2>2017年度表彰</h2><p>都築 俊三郎 ／ 井本 正忠 ／ 岩上 太郎 ／ すみ 光保 ／ 石川 雅章 ／ 粕谷 三郎 ／ 和田 幸二郎 ／ 波間 章 ／ 大久保 洪基 ／ 矢島 純一 ／ 酒井 武雄 ／ 須田 開代子 ／ 中山 律子 ／ 石井 利枝 ／ 並木 惠美子 ／ 藤原 清子 ／ 斉藤 志乃ぶ ／ 杉本 勝子 ／ 松田 秀樹</p>',
            ],
            [
                'slug' => 'official-high-records', 'title' => 'JPBA公認最高記録', 'navigation_group' => 'other', 'sort_order' => 330,
                'source_url' => 'https://www.jpba.or.jp/information/tournament/HR/index.html', 'source_checked_at' => '2026-09-05 00:00:00',
                'is_published' => true, 'published_at' => '2026-09-05 00:00:00',
                'body_html' => '<h2>シリーズ最高記録</h2><table><thead><tr><th>区分</th><th>男子</th><th>女子</th></tr></thead><tbody><tr><th>H/S</th><td>900　西村 了（2003年11月27日）</td><td>843　並木 惠美子（1972年8月3日）</td></tr><tr><th>H/4</th><td>1,131　山本 勲（2022年1月18日）</td><td>1,092　桑藤 美樹（2014年12月20日）</td></tr><tr><th>H/5</th><td>1,357　森本 健太（2018年7月20日）</td><td>1,334　ウエンディ・マックファーソン（2005年12月10日）</td></tr><tr><th>H/6</th><td>1,659　西村 了（2003年11月27日）</td><td>1,575　谷川 章子（2013年12月7日）</td></tr><tr><th>H/9</th><td>2,433　西城 正明（1972年1月7日）</td><td>2,316　近藤 文美（2014年12月11日）</td></tr></tbody></table><p>シリーズ最高記録は2023年1月1日時点の公式掲載内容です。</p><h2>通算タイトル上位</h2><table><thead><tr><th>順位</th><th>男子</th><th>女子</th></tr></thead><tbody><tr><th>1位</th><td>41勝　矢島 純一</td><td>75勝　斉藤 志乃ぶ</td></tr><tr><th>2位</th><td>37勝　酒井 武雄</td><td>43勝　須田 開代子</td></tr><tr><th>3位</th><td>35勝　西城 正明</td><td>36勝　並木 惠美子</td></tr><tr><th>4位</th><td>25勝　塚原 次雄</td><td>35勝　時本 美津子</td></tr><tr><th>5位</th><td>23勝　川添 奨太</td><td>34勝　姫路 麗</td></tr><tr><th>6位</th><td>22勝　保倉 義孝／山本 勲</td><td>33勝　中山 律子</td></tr></tbody></table><p>通算タイトルは2025年5月25日時点の公式掲載内容です。今後の達成分は管理画面から更新できます。</p>',
            ],
        ];
    }
};
