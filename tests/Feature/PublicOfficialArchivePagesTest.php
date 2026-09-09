<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentArchive;
use App\Models\TournamentFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOfficialArchivePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tournament_archive_is_searchable_and_viewable(): void
    {
        $archive = TournamentArchive::query()->create([
            'year' => 2016,
            'classification' => 'official_tournament',
            'title' => '第39回 テスト大会',
            'start_on' => '2016-12-21',
            'end_on' => '2016-12-24',
            'status' => 'completed',
            'body_html' => '<p>大会情報</p>',
            'assets' => [['path' => 'documents/test.pdf', 'type' => 'result', 'title' => '最終成績']],
            'source_key' => 'test-tournament-2016',
            'is_public' => true,
        ]);

        $this->get(route('public.tournament_archives.index', ['year' => 2016, 'keyword' => 'テスト']))
            ->assertOk()
            ->assertSee('第39回 テスト大会');

        $this->get(route('public.tournament_archives.show', $archive))
            ->assertOk()
            ->assertSee('最終成績')
            ->assertSee('大会情報');
    }

    public function test_approved_events_are_filterable_by_classification_and_venue(): void
    {
        TournamentArchive::query()->create([
            'year' => 2026,
            'classification' => 'approved_event',
            'title' => '地域チャリティートーナメント',
            'start_on' => '2026-08-22',
            'end_on' => '2026-08-23',
            'venue_name' => 'テストボウル長野',
            'organizer_name' => '地域実行委員会',
            'approval_number' => 'A-123',
            'status' => 'completed',
            'body_html' => '<p>承認イベント情報</p>',
            'assets' => [],
            'source_key' => 'test-approved-event-2026',
            'is_public' => true,
        ]);

        $this->get(route('public.tournament_archives.index', [
            'classification' => 'approved_event',
            'venue' => '長野',
        ]))
            ->assertOk()
            ->assertSee('地域チャリティートーナメント')
            ->assertSee('承認イベント')
            ->assertSee('テストボウル長野');
    }

    public function test_oil_pattern_catalog_combines_archive_and_current_tournament_files(): void
    {
        TournamentArchive::query()->create([
            'year' => 2019,
            'classification' => 'official_tournament',
            'title' => '過去オイル大会',
            'start_on' => '2019-12-01',
            'venue_name' => '過去ボウル',
            'status' => 'completed',
            'body_html' => '<p>大会情報</p>',
            'assets' => [['path' => 'documents/test-oil.pdf', 'type' => 'oil_pattern', 'title' => '予選オイルパターン']],
            'source_key' => 'test-oil-archive',
            'is_public' => true,
        ]);
        $tournament = Tournament::query()->create([
            'name' => '現行オイル大会',
            'year' => 2026,
            'start_date' => '2026-10-01',
            'venue_name' => '現行ボウル',
            'gender' => 'M',
            'official_type' => 'official',
        ]);
        TournamentFile::query()->create([
            'tournament_id' => $tournament->id,
            'type' => 'oil_pattern',
            'title' => '決勝オイルパターン',
            'file_path' => 'tournament/oil.pdf',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);

        $this->get(route('public.oil_patterns.index'))
            ->assertOk()
            ->assertSee('過去オイル大会')
            ->assertSee('予選オイルパターン')
            ->assertSee('現行オイル大会')
            ->assertSee('決勝オイルパターン');

        $this->get(route('public.oil_patterns.index', ['year' => 2019, 'venue' => '過去ボウル']))
            ->assertOk()
            ->assertSee('過去オイル大会')
            ->assertDontSee('現行オイル大会');
    }

    public function test_records_hub_and_qualification_lists_are_public(): void
    {
        $this->get(route('public.records.index'))
            ->assertOk()
            ->assertSee('シード・資格・公認記録');

        $this->get(route('public.records.seed'))->assertOk()->assertSee('トーナメントシード');
        $this->get(route('public.records.a_class.m'))->assertOk()->assertSee('男子 永久A級ライセンス保持者');
        $this->get(route('public.records.a_class.f'))->assertOk()->assertSee('女子 永久A級ライセンス保持者');
    }
}
