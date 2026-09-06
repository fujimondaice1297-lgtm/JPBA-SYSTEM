<?php

namespace Tests\Feature;

use App\Models\TournamentArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOfficialArchivePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tournament_archive_is_searchable_and_viewable(): void
    {
        $archive = TournamentArchive::query()->create([
            'year' => 2016,
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
