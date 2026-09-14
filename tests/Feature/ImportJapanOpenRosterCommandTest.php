<?php

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Services\JapanOpenFormatService;
use App\Services\JapanOpenRosterImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

test('japan open roster command validates before writing and repeated imports keep stable participants', function () {
    $year = 2084;
    app(JapanOpenFormatService::class)->setup([
        'year' => $year,
        'edition_no' => 106,
        'name' => '第106回テストジャパンオープン',
    ], true);

    $directory = storage_path('framework/testing/japan-open-roster-command');
    File::ensureDirectoryExists($directory);
    $header = "チームコード\tチーム名\t順番\tライセンスNo.\t選手名\tシフト\n";
    $men = $header.collect(range(1, 4))->map(
        fn (int $order): string => "M001\t男子テスト\t{$order}\t\t男子アマ{$order}\t"
    )->implode("\n")."\n";
    $women = $header.collect(range(1, 4))->map(
        fn (int $order): string => "W001\t女子テスト\t{$order}\t\t女子アマ{$order}\t"
    )->implode("\n")."\n";
    $menPath = $directory.'/men.tsv';
    $womenPath = $directory.'/women.tsv';
    File::put($menPath, $men);
    File::put($womenPath, $women);

    $this->artisan('jpba:import-japan-open-roster', [
        'year' => $year,
        '--men-file' => $menPath,
        '--women-file' => $womenPath,
        '--json' => true,
    ])->assertSuccessful();

    $teamIds = Tournament::query()
        ->where('year', $year)
        ->whereIn('competition_type', ['team'])
        ->pluck('id');
    expect(DB::table('tournament_participants')->whereIn('tournament_id', $teamIds)->count())->toBe(0);

    $this->artisan('jpba:import-japan-open-roster', [
        'year' => $year,
        '--men-file' => $menPath,
        '--women-file' => $womenPath,
        '--force' => true,
        '--json' => true,
    ])->assertSuccessful();

    expect(DB::table('tournament_participants')->whereIn('tournament_id', $teamIds)->count())->toBe(8)
        ->and(DB::table('tournament_competitor_groups')->whereIn('tournament_id', $teamIds)->count())->toBe(2);

    $participantIds = DB::table('tournament_participants')->whereIn('tournament_id', $teamIds)->orderBy('id')->pluck('id')->all();
    $this->artisan('jpba:import-japan-open-roster', [
        'year' => $year, '--men-file' => $menPath, '--women-file' => $womenPath,
        '--force' => true, '--json' => true,
    ])->assertSuccessful();
    expect(DB::table('tournament_participants')->whereIn('tournament_id', $teamIds)->orderBy('id')->pluck('id')->all())->toBe($participantIds);

    // An incomplete roster must fail even in dry-run, before touching existing rows.
    File::put($womenPath, "W001\t女子テスト\t1\t\t女子アマ1\t\n");
    $this->artisan('jpba:import-japan-open-roster', [
        'year' => $year, '--men-file' => $menPath, '--women-file' => $womenPath, '--json' => true,
    ])->assertFailed();
    expect(DB::table('tournament_participants')->whereIn('tournament_id', $teamIds)->orderBy('id')->pluck('id')->all())->toBe($participantIds);

    File::deleteDirectory($directory);
});

test('roster dry-run rejects wrong gender and two professionals in a doubles pair', function () {
    $report = app(JapanOpenFormatService::class)->setup(['year' => 2083], true);
    $tournament = Tournament::findOrFail($report['component_ids']['men_team']);
    ProBowler::create(['license_no' => 'M00001001', 'name_kanji' => '男子一', 'sex' => 1]);
    ProBowler::create(['license_no' => 'M00001002', 'name_kanji' => '男子二', 'sex' => 1]);
    ProBowler::create(['license_no' => 'F00001001', 'name_kanji' => '女子一', 'sex' => 2]);
    $service = app(JapanOpenRosterImportService::class);
    foreach (['F00001001', 'M00001002'] as $secondLicense) {
        $text = "A01\tテスト\t1\tM00001001\t男子一\nA01\tテスト\t2\t{$secondLicense}\t二\nA01\tテスト\t3\t\tアマ一\nA01\tテスト\t4\t\tアマ二";
        expect(fn () => $service->parse($tournament, $text))->toThrow(InvalidArgumentException::class);
    }
    expect(DB::table('tournament_participants')->where('tournament_id', $tournament->id)->count())->toBe(0);
});

test('roster command rolls back men if women component import fails', function () {
    $year = 2082;
    $report = app(JapanOpenFormatService::class)->setup(['year' => $year], true);
    Tournament::findOrFail($report['component_ids']['women_doubles'])->delete();
    $directory = storage_path('framework/testing/japan-open-roster-atomic');
    File::ensureDirectoryExists($directory);
    $text = collect(range(1, 4))->map(fn (int $i): string => "A01\tテスト\t{$i}\t\tアマ{$i}")->implode("\n");
    $path = $directory.'/roster.tsv';
    File::put($path, $text);
    try {
        $this->artisan('jpba:import-japan-open-roster', [
            'year' => $year, '--men-file' => $path, '--women-file' => $path, '--force' => true,
        ])->assertFailed();
        expect(DB::table('tournament_participants')->whereIn('tournament_id', $report['component_ids'])->count())->toBe(0)
            ->and(DB::table('tournament_competitor_groups')->whereIn('tournament_id', $report['component_ids'])->count())->toBe(0);
    } finally {
        File::deleteDirectory($directory);
    }
});

test('japan open setup retains operational snapshot history on repeat', function () {
    $service = app(JapanOpenFormatService::class);
    $report = $service->setup(['year' => 2081, 'final_format' => 'round_robin_stepladder'], true);
    $masters = Tournament::findOrFail($report['component_ids']['masters']);
    $snapshot = $masters->template_snapshot;
    $snapshot['japan_open']['source_checked_at'] = '2026-09-14';
    $snapshot['japan_open']['semifinal_sync'] = ['source_snapshot_id' => 123];
    $masters->update(['template_snapshot' => $snapshot]);
    $masters->edition->update(['status' => 'published']);
    $service->setup(['year' => 2081, 'final_format' => 'round_robin_stepladder'], true);
    expect(data_get($masters->fresh()->template_snapshot, 'japan_open.semifinal_sync.source_snapshot_id'))->toBe(123)
        ->and(data_get($masters->fresh()->template_snapshot, 'japan_open.source_checked_at'))->toBe('2026-09-14')
        ->and($masters->edition->fresh()->status)->toBe('published');
});
