<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('managed_public_pages')->updateOrInsert(
            ['slug' => 'pro-wappen'],
            [
                'title' => 'プロワッペン',
                'body_html' => $this->body(),
                'source_url' => 'https://www.jpba.or.jp/information/tournament/wappen.html',
                'navigation_group' => 'other',
                'sort_order' => 45,
                'is_published' => true,
                'published_at' => $now,
                'source_checked_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('managed_public_pages')->where('slug', 'pro-wappen')->delete();
    }

    private function body(): string
    {
        return <<<'HTML'
<p>JPBAプロボウラーが着用する公認ワッペンの区分をご案内します。</p>
<h2>赤枠ワッペン</h2>
<table><tbody><tr><th>ワッペン</th><td><img src="/images/jpba/pro-wappen/Emblem_N.jpg" alt="赤枠ワッペン"></td></tr><tr><th>対象</th><td>プロボウラー資格取得テスト合格後に交付されます。</td></tr><tr><th>着用</th><td>JPBA公式戦・承認イベントでは左胸への着用が必要です。</td></tr></tbody></table>
<h2>金枠ワッペン</h2>
<table><tbody><tr><th>ワッペン</th><td><img src="/images/jpba/pro-wappen/Emblem_A.jpg" alt="金枠ワッペン"></td></tr><tr><th>対象</th><td>永久A級ライセンス取得者が着用します。</td></tr><tr><th>男子取得条件</th><td>年間200ゲーム以上・年間アベレージ210以上、または5年連続シード権獲得。全日本プロボウリング選手権大会優勝者も対象です。</td></tr><tr><th>女子取得条件</th><td>年間200ゲーム以上・年間アベレージ200以上、または5年連続シード権獲得。全日本女子プロボウリング選手権大会優勝者も対象です。</td></tr></tbody></table>
<ul><li><a href="/records/a-class/men">男子 永久A級ライセンス取得者</a></li><li><a href="/records/a-class/women">女子 永久A級ライセンス取得者</a></li></ul>
<h2>金ワッペン</h2>
<table><tbody><tr><th>ワッペン</th><td><img src="/images/jpba/pro-wappen/Emblem_H.jpg" alt="金ワッペン"></td></tr><tr><th>対象</th><td>名誉プロボウラーが着用します。</td></tr></tbody></table>
<p>名誉プロボウラー制度は2015年に設けられました。ボウリングの普及に協力する芸能・スポーツ分野の著名人が対象です。</p>
<h3>第1号名誉プロボウラー 村田雄浩</h3>
<p>1960年3月18日生まれ。ハイスコア300。</p>
<table><tbody><tr><td><img src="/images/jpba/pro-wappen/Hpro_Murata1.jpg" alt="村田雄浩 名誉プロボウラー"></td><td><img src="/images/jpba/pro-wappen/0115_3.jpg" alt="金ワッペン贈呈"></td><td><img src="/images/jpba/pro-wappen/Hpro_Murata2.jpg" alt="村田雄浩 ボウリング"></td></tr></tbody></table>
HTML;
    }
};
