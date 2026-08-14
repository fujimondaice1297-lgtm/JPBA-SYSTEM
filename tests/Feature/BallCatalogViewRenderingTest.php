<?php

namespace Tests\Feature;

use App\Http\Controllers\ApprovedBallController;
use App\Models\ApprovedBall;
use App\Models\BallManufacturer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BallCatalogViewRenderingTest extends TestCase
{
    public function test_catalog_index_renders_photo_filters_and_approval_separately(): void
    {
        $ball = new ApprovedBall([
            'name' => 'ACCU TEST',
            'name_kana' => 'アキュ・テスト',
            'manufacturer' => 'ABS',
            'brand' => 'NANODESU',
            'catalog_status' => 'listed',
            'approved' => false,
            'release_date' => '2026-07-01',
            'source_payload' => [
                'release_text' => '2026年7月中旬',
                'release_date_basis' => 'official_release_text',
            ],
            'source_url' => 'https://www.absbowling.co.jp/product/accu-test/',
        ]);
        $ball->id = 1;
        $ball->exists = true;

        $balls = new LengthAwarePaginator([$ball], 1, 50, 1, [
            'path' => url('/approved_balls'),
        ]);
        $manufacturer = new BallManufacturer([
            'name' => 'ABS',
            'slug' => 'abs',
            'base_url' => 'https://www.absbowling.co.jp/',
            'catalog_url' => 'https://www.absbowling.co.jp/product-category/cat01/',
        ]);
        $manufacturer->setAttribute('approved_balls_count', 159);
        $manufacturer->setAttribute('image_count', 159);
        $catalogSummary = collect([$manufacturer]);
        $manufacturers = collect(['ABS']);
        $brands = collect(['NANODESU']);

        $html = view('approved_balls.index', compact(
            'balls',
            'catalogSummary',
            'manufacturers',
            'brands'
        ))->render();

        $this->assertStringContainsString('ボールカタログ', $html);
        $this->assertStringContainsString('メーカー・ブランド・五十音順', $html);
        $this->assertStringContainsString('写真159件', $html);
        $this->assertStringContainsString('ACCU TEST', $html);
        $this->assertStringContainsString('アキュ・テスト', $html);
        $this->assertStringContainsString('NANODESU', $html);
        $this->assertStringContainsString('2026年7月中旬', $html);
        $this->assertStringContainsString('images/ball-no-image.svg', $html);
        $this->assertStringContainsString('未設定', $html);
        $this->assertStringNotContainsString('選択可</span>', $html);
    }

    public function test_catalog_edit_renders_source_and_jpba_selection_control(): void
    {
        $ball = new ApprovedBall([
            'name' => 'NO DOUBT HYBRID™',
            'name_kana' => 'ノーダウト・ハイブリッド™',
            'manufacturer' => 'サンブリッジ',
            'brand' => 'RADICAL',
            'catalog_status' => 'listed',
            'approved' => false,
            'source_url' => 'https://www.sunbridge-group.com/product/ball/all-balls/no-doubt-hybrid/',
        ]);
        $ball->id = 2;
        $ball->exists = true;

        $html = view('approved_balls.edit', compact('ball'))->render();

        $this->assertStringContainsString('ボール情報の編集', $html);
        $this->assertStringContainsString('NO DOUBT HYBRID™', $html);
        $this->assertStringContainsString('RADICAL', $html);
        $this->assertStringContainsString('JPBA大会で選択可能', $html);
        $this->assertStringContainsString('アブプールリスト反映後に設定', $html);
        $this->assertStringContainsString($ball->source_url, $html);
    }

    public function test_catalog_image_is_served_without_a_public_storage_link(): void
    {
        Storage::fake('public');
        $path = 'ball_catalog/abs/sample.png';
        Storage::disk('public')->put($path, 'sample-image-bytes');

        $ball = new ApprovedBall([
            'name' => 'ACCU TEST',
            'image_path' => $path,
        ]);
        $ball->id = 99;
        $ball->exists = true;

        $this->assertStringContainsString(
            '/ball-catalog/images/99',
            $ball->image_url
        );

        $response = app(ApprovedBallController::class)->image($ball);

        $this->assertSame(200, $response->getStatusCode());
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=604800', $cacheControl);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame('sample-image-bytes', $content);
    }
}
