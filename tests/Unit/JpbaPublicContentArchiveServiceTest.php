<?php

use App\Services\JpbaPublicContentArchiveService;

test('legacy information html is converted to an editable internal article', function () {
    $html = <<<'HTML'
    <html><body>
      <div class="info-list__item-date"><span>2026年8月20日</span></div>
      <div class="info-list__item-category tournament">大会</div>
      <div class="info-detail">
        <div class="info-detail__ttl">大会観覧のお知らせ</div>
        本文です。<br>
        詳細は https://www.jpba.or.jp/information/legacy.html で確認できます。<br>
        <a href="/.file/public/2026/08/20/sample.pdf"><img src="/.file/public/2026/08/20/sample.jpg" alt="観覧席"></a>
        <a href="https://example.com/form">申込ページ</a>
        <a href="https://www.jpba.or.jp/legacy.html">旧大会ページ</a>
      </div>
    </body></html>
    HTML;

    $article = app(JpbaPublicContentArchiveService::class)->parseInformationHtml(
        $html,
        'https://www.jpba1.jp/information/detail.html?id=410',
        410,
    );

    expect($article)
        ->not->toBeNull()
        ->and($article['source_key'])->toBe('legacy-information-410')
        ->and($article['published_on'])->toBe('2026-08-20')
        ->and($article['category'])->toBe('大会')
        ->and($article['body_html'])->toContain('本文です。')
        ->and($article['body_html'])->toContain('https://example.com/form')
        ->and($article['body_html'])->not->toContain('jpba.or.jp')
        ->and($article['body_html'])->not->toContain('旧大会ページ')
        ->and($article['body_html'])->toContain('新サイト内の該当ページ')
        ->and($article['assets'])->toHaveCount(2)
        ->and($article['assets'][0]['type'])->toBe('image')
        ->and($article['assets'][1]['type'])->toBe('pdf');
});

test('newer topic headings use the second h5 after the date', function () {
    $html = <<<'HTML'
    <html><head><meta charset="SHIFT_JIS"></head><body>
      <table class="tbl_border"><tr><td><table class="tbl_list">
        <tr><td><h5>2026/07/28</h5></td></tr>
        <tr><td><h5>サマーシリーズ終了</h5></td></tr>
        <tr><td><p>大会本文</p></td></tr>
      </table></td></tr></table>
    </body></html>
    HTML;

    $articles = app(JpbaPublicContentArchiveService::class)->parseTopicsHtml(
        mb_convert_encoding($html, 'SJIS-win', 'UTF-8'),
        'https://www.jpba.or.jp/topics/2026/07.html',
    );

    expect($articles)->toHaveCount(1)
        ->and($articles[0]['title'])->toBe('サマーシリーズ終了')
        ->and($articles[0]['published_on'])->toBe('2026-07-28');
});

test('legacy topic page is split into independent dated articles', function () {
    $html = <<<'HTML'
    <html><body><table class="contents_main">
      <tr><td><table class="tbl_border"><tr><td><p>2018/01/31<br><span class="bold">大会終了</span></p></td></tr><tr><td>第1本文<img src="img/winner.jpg" alt="優勝者"></td></tr></table></td></tr>
      <tr><td><table class="tbl_border"><tr><td><p>2018/01/18<br><span class="bold">テレビ放送決定</span></p></td></tr><tr><td>第2本文</td></tr></table></td></tr>
    </table></body></html>
    HTML;

    $articles = app(JpbaPublicContentArchiveService::class)->parseTopicsHtml(
        $html,
        'https://www.jpba.or.jp/topics/2018/topics01.html',
    );

    expect($articles)->toHaveCount(2)
        ->and($articles[0]['published_on'])->toBe('2018-01-31')
        ->and($articles[0]['category'])->toBe('大会')
        ->and($articles[0]['body_html'])->toContain('第1本文')
        ->and($articles[0]['body_html'])->not->toContain('2018/01/31')
        ->and($articles[0]['assets'])->toHaveCount(1)
        ->and($articles[1]['category'])->toBe('TV情報');
});
