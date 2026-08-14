<?php

namespace Tests\Unit;

use App\Services\BallCatalogScraperService;
use Tests\TestCase;

class BallCatalogScraperServiceTest extends TestCase
{
    public function test_it_parses_abs_ball_cards_and_archive_status(): void
    {
        $html = <<<'HTML'
        <html><body>
        <ul>
          <li class="list-products__item">
            <a class="list-products__link" href="/product/accu-test/">
              <div><img data-src="/wabsp/wp-content/uploads/2026/01/accu.png"></div>
              <div class="list-products__wrap">
                <p><span class="list-grid-01__category-item">NANODESUボール</span><span class="list-grid-01__category-item">アーカイブ</span></p>
                <p class="list-products__ttl">ACCU TEST</p>
                <p class="list-products__ttl-jp">アキュ・テスト</p>
                <p class="list-products__price">販売終了しました</p>
              </div>
            </a>
          </li>
        </ul>
        <a class="next page-numbers" href="/product-category/cat01/page/2/">次へ</a>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseListingPage(
            'abs',
            $html,
            'https://www.absbowling.co.jp/product-category/cat01/'
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame('ACCU TEST', $result['items'][0]['name']);
        $this->assertSame('アキュ・テスト', $result['items'][0]['name_kana']);
        $this->assertSame('NANODESU', $result['items'][0]['brand']);
        $this->assertSame('archive', $result['items'][0]['catalog_status']);
        $this->assertSame(
            'https://www.absbowling.co.jp/wabsp/wp-content/uploads/2026/01/accu.png',
            $result['items'][0]['source_image_url']
        );
        $this->assertSame(
            'https://www.absbowling.co.jp/product-category/cat01/page/2/',
            $result['next_url']
        );
    }

    public function test_it_parses_hi_sp_ball_cards_and_selects_largest_image(): void
    {
        $html = <<<'HTML'
        <html><body>
        <ul class="products">
          <li class="product type-product product_cat-ball">
            <a href="/product/code_crush/" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
              <img data-src="/uploads/code-300x300.jpg"
                   data-srcset="/uploads/code-300x300.jpg 300w, /uploads/code-800.jpg 800w">
              <h2 class="woocommerce-loop-product__title">コード・クラッシュ</h2>
              <div class="pwb-brands-in-loop"><span><a href="/brand/storm/">STORM-ストーム</a></span></div>
            </a>
          </li>
        </ul>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseListingPage(
            'hi-sp',
            $html,
            'https://hi-sp.co.jp/product-category/ball/'
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame('コード・クラッシュ', $result['items'][0]['name']);
        $this->assertSame('STORM', $result['items'][0]['brand']);
        $this->assertSame(
            'https://hi-sp.co.jp/uploads/code-800.jpg',
            $result['items'][0]['source_image_url']
        );
    }

    public function test_hi_sp_brand_name_keeps_the_distributor_hyphen(): void
    {
        $html = <<<'HTML'
        <html><body><ul>
          <li class="product product_cat-ball">
            <a href="/product/sample/" class="woocommerce-LoopProduct-link">
              <img data-src="/sample.jpg">
              <h2 class="woocommerce-loop-product__title">サンプル</h2>
              <div class="pwb-brands-in-loop"><a>HI-SP-ハイスポ</a></div>
            </a>
          </li>
        </ul></body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseListingPage(
            'hi-sp',
            $html,
            'https://hi-sp.co.jp/product-category/ball/'
        );

        $this->assertSame('HI-SP', $result['items'][0]['brand']);
    }

    public function test_it_parses_sunbridge_ball_cards_and_release_month(): void
    {
        $html = <<<'HTML'
        <html><body>
          <div class="post_con">
            <a href="/product/ball/all-balls/no-doubt-hybrid/">
              <div class="img_wrap"><img data-src="/kanri/uploads/no-doubt.png"></div>
            </a>
            <div class="ttl_wrap"><span class="brand">RADICAL</span><span class="ttl">NO DOUBT HYBRID™</span></div>
            <div class="info_wrap"><span class="sub_ttl">ノーダウト・ハイブリッド™</span><span class="date">2026年7月発売</span></div>
          </div>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseListingPage(
            'sunbridge',
            $html,
            'https://www.sunbridge-group.com/product/product_cat/ball/'
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame('NO DOUBT HYBRID™', $result['items'][0]['name']);
        $this->assertSame('ノーダウト・ハイブリッド™', $result['items'][0]['name_kana']);
        $this->assertSame('RADICAL', $result['items'][0]['brand']);
        $this->assertSame('2026-07-01', $result['items'][0]['release_date']);
        $this->assertSame('ノーダウトハイブリッド™', $result['items'][0]['sort_name']);
    }

    public function test_it_parses_abs_release_text_and_keeps_month_precision(): void
    {
        $html = <<<'HTML'
        <html><head>
          <script type="application/ld+json">
            {"datePublished":"2026-07-01T07:59:53+00:00"}
          </script>
        </head><body>
          <h3 class="product-desc__ttl">発売予定日</h3>
          <div class="product-desc__txt">2026年7月中旬</div>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseProductDetail(
            'abs',
            $html,
            'https://www.absbowling.co.jp/product/sample/'
        );

        $this->assertSame('2026-07-01', $result['release_date']);
        $this->assertSame('2026年7月中旬', $result['release_text']);
        $this->assertSame('official_release_text', $result['release_date_basis']);
        $this->assertSame('month', $result['release_date_precision']);
        $this->assertSame('2026-07-01', $result['published_date']);
    }

    public function test_abs_release_text_without_year_uses_official_publish_year(): void
    {
        $html = <<<'HTML'
        <html><head>
          <script type="application/ld+json">
            {"datePublished":"2026-04-30T08:00:47+00:00"}
          </script>
        </head><body>
          <h3 class="product-desc__ttl">発売予定日</h3>
          <div class="product-desc__txt">6月下旬～7月上旬 発売予定</div>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseProductDetail(
            'abs',
            $html,
            'https://www.absbowling.co.jp/product/sample/'
        );

        $this->assertSame('2026-06-01', $result['release_date']);
        $this->assertSame('6月下旬～7月上旬 発売予定', $result['release_text']);
        $this->assertSame('month', $result['release_date_precision']);
    }

    public function test_it_parses_hi_sp_release_month_separately_from_publish_date(): void
    {
        $html = <<<'HTML'
        <html><body>
          <p>公開日:2026/07/16</p>
          <p><p>発売：2026年8月予定</p></p>
        </body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseProductDetail(
            'hi-sp',
            $html,
            'https://hi-sp.co.jp/product/sample/'
        );

        $this->assertSame('2026-08-01', $result['release_date']);
        $this->assertSame('2026年8月予定', $result['release_text']);
        $this->assertSame('2026-07-16', $result['published_date']);
        $this->assertSame('official_release_text', $result['release_date_basis']);
    }

    public function test_product_publish_month_is_used_when_release_text_is_absent(): void
    {
        $html = <<<'HTML'
        <html><head>
          <script type="application/ld+json">
            {"datePublished":"2024-03-14T08:00:00+00:00"}
          </script>
        </head><body><h1>旧商品</h1></body></html>
        HTML;

        $result = app(BallCatalogScraperService::class)->parseProductDetail(
            'hi-sp',
            $html,
            'https://hi-sp.co.jp/product/old/'
        );

        $this->assertSame('2024-03-01', $result['release_date']);
        $this->assertSame('2024年3月発売', $result['release_text']);
        $this->assertSame('official_publish_month', $result['release_date_basis']);
        $this->assertSame('month', $result['release_date_precision']);
        $this->assertSame('2024-03-14', $result['published_date']);
    }
}
