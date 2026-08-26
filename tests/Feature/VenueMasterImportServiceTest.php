<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Services\VenueMasterImportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VenueMasterImportServiceTest extends TestCase
{
    public function test_import_is_dry_run_by_default_idempotent_and_links_existing_tournament(): void
    {
        DB::table('tournaments')->insert([
            'name' => 'シーズントライアル',
            'venue_name' => 'サンスクエアボウル',
        ]);

        $service = app(VenueMasterImportService::class);
        $dryRun = $service->import();

        $this->assertSame('dry-run', $dryRun['mode']);
        $this->assertSame(58, $dryRun['created_count']);
        $this->assertSame(1, $dryRun['linked_tournament_count']);
        $this->assertSame(0, Venue::query()->count());

        $executed = $service->import(true);

        $this->assertSame(58, $executed['created_count']);
        $this->assertSame(58, Venue::query()->count());
        $this->assertNotNull(DB::table('tournaments')->value('venue_id'));
        $this->assertFalse(Venue::query()->whereIn('name', ['スポルト名古屋', '星が丘ボウル', '牧野松園ボウル'])->exists());

        $venue = Venue::query()->where('name', 'サンスクエアボウル')->firstOrFail();
        $venue->update(['address' => '手動で確認した住所']);

        $rerun = $service->import(true);

        $this->assertSame(0, $rerun['created_count']);
        $this->assertSame('手動で確認した住所', $venue->fresh()->address);
        $this->assertSame(58, Venue::query()->count());
    }
}
