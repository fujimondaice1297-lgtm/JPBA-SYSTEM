<?php

namespace Tests\Unit;

use App\Services\TournamentArchiveMetadataExtractor;
use Tests\TestCase;

class TournamentArchiveMetadataExtractorTest extends TestCase
{
    public function test_it_extracts_venue_and_organizer_from_legacy_body_html(): void
    {
        $body = '<p>開催要項</p><p>会　場</p><p>品川プリンスホテルボウリングセンター</p><p>主　催</p><p>テスト実行委員会</p>';
        $extractor = app(TournamentArchiveMetadataExtractor::class);

        $this->assertSame('品川プリンスホテルボウリングセンター', $extractor->venue($body));
        $this->assertSame('テスト実行委員会', $extractor->organizer($body));
    }

    public function test_it_skips_stage_headers_and_extracts_the_first_actual_venue(): void
    {
        $body = '<p>会 場</p><p>9/29(火) A会場:</p><p>宇都宮第二トーヨーボウル(BM40L 合成レーン)</p><p>10/21(水) B会場:</p><p>ハマボール</p>';

        $this->assertSame(
            '宇都宮第二トーヨーボウル(BM40L 合成レーン)',
            app(TournamentArchiveMetadataExtractor::class)->venue($body),
        );
    }

    public function test_it_skips_bracketed_stage_labels_before_a_venue(): void
    {
        $body = '<p>会場</p><p>[ 予選</p><p>第1会場 ]</p><p>大丸パークレーンズ(BW24L 合成レーン)</p>';

        $this->assertSame(
            '大丸パークレーンズ(BW24L 合成レーン)',
            app(TournamentArchiveMetadataExtractor::class)->venue($body),
        );
    }
}
