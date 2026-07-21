<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Services\VenueMasterImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class VenueMasterImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('canonical_key')->nullable()->unique();
            $table->json('aliases')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('prefecture')->nullable();
            $table->string('tel')->nullable();
            $table->string('fax')->nullable();
            $table->string('website_url')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source_url')->nullable();
            $table->date('source_checked_at')->nullable();
            $table->unsignedSmallInteger('first_hosted_year')->nullable();
            $table->unsignedSmallInteger('last_hosted_year')->nullable();
            $table->timestamps();
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('venue_name')->nullable();
            $table->foreignId('venue_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('venues');

        parent::tearDown();
    }

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
