<?php

use App\Models\Tournament;
use App\Models\TournamentEdition;
use App\Models\TournamentSeries;
use App\Models\TournamentTemplate;
use App\Models\TournamentTemplateVersion;
use App\Models\Venue;
use App\Services\SeasonTrial2026CatalogService;
use App\Services\VenueNameNormalizer;
use Illuminate\Support\Facades\DB;

test('autumn import adds only empty tournament shells and preserves completed editions', function () {
    $service = app(SeasonTrial2026CatalogService::class);
    $catalog = $service->catalog();

    $series = TournamentSeries::query()->create([
        'name' => 'JPBAシーズントライアル',
        'code' => $catalog['series_code'],
        'recurrence_type' => 'seasonal',
        'is_active' => true,
    ]);
    $template = TournamentTemplate::query()->create([
        'tournament_series_id' => $series->id,
        'name' => 'シーズントライアル標準',
        'code' => $catalog['template_code'],
        'is_active' => true,
    ]);
    $version = TournamentTemplateVersion::query()->create([
        'tournament_template_id' => $template->id,
        'version' => 1,
        'status' => 'published',
        'settings' => [
            'tournament' => [
                'gender' => 'M',
                'official_type' => 'official',
                'title_category' => 'season_trial',
                'competition_type' => 'singles',
                'include_annual_seeds' => false,
                'counts_for_official_points' => true,
                'counts_for_average' => true,
                'counts_for_prize' => true,
                'title_scope' => 'season_trial',
                'result_flow_type' => 'prelim_to_semifinal_to_shootout_to_final',
                'shootout_qualifier_count' => 8,
                'ball_registration_limit' => 12,
            ],
            'stage_settings' => [],
            'point_distributions' => [],
            'prize_distributions' => [],
            'entry_rules' => [],
            'result_outputs' => [],
        ],
        'published_at' => now(),
    ]);

    $normalizer = app(VenueNameNormalizer::class);
    $venueIds = collect($catalog['editions'])
        ->flatMap(fn (array $edition) => $edition['events'])
        ->pluck('venue_name')
        ->unique()
        ->mapWithKeys(function (string $venueName) use ($normalizer): array {
            $venue = Venue::query()->create([
                'name' => $venueName,
                'canonical_key' => $normalizer->normalize($venueName),
                'aliases' => [],
                'is_active' => true,
            ]);

            return [$venueName => $venue->id];
        });

    foreach (array_slice($catalog['editions'], 0, 3) as $editionRow) {
        $edition = TournamentEdition::query()->create([
            'tournament_series_id' => $series->id,
            'year' => 2026,
            'season_key' => $editionRow['season_key'],
            'name' => $editionRow['name'],
            'status' => 'legacy-completed',
            'start_date' => '2025-12-30',
            'end_date' => '2025-12-31',
            'notes' => '保持する既存年度開催 '.$editionRow['season_key'],
        ]);

        foreach ($editionRow['events'] as $eventRow) {
            Tournament::query()->create([
                'tournament_series_id' => $series->id,
                'tournament_edition_id' => $edition->id,
                'name' => 'メリーランドカップ '.$editionRow['name'].' '.$eventRow['venue_code'].'会場',
                'setup_status' => 'completed',
                'start_date' => $eventRow['date'],
                'end_date' => $eventRow['date'],
                'year' => 2026,
                'venue_id' => $venueIds[$eventRow['venue_name']],
                'venue_name' => $eventRow['venue_name'],
                'gender' => 'M',
                'official_type' => 'official',
                'title_category' => 'season_trial',
                'counts_for_official_points' => true,
                'title_scope' => 'season_trial',
            ]);
        }
    }

    $dryRun = $service->import();

    expect($dryRun['edition_create_count'])->toBe(1)
        ->and($dryRun['edition_update_count'])->toBe(0)
        ->and($dryRun['tournament_create_count'])->toBe(4)
        ->and($dryRun['tournament_existing_count'])->toBe(12)
        ->and($dryRun['conflict_count'])->toBe(0);

    $executed = $service->import(true);

    expect($executed['created_tournament_ids'])->toHaveCount(4)
        ->and($executed['protected_data_unchanged'])->toBeTrue();

    foreach (['winter', 'spring', 'summer'] as $seasonKey) {
        $completedEdition = TournamentEdition::query()
            ->where('season_key', $seasonKey)
            ->firstOrFail();

        expect($completedEdition->status)->toBe('legacy-completed')
            ->and($completedEdition->start_date->format('Y-m-d'))->toBe('2025-12-30')
            ->and($completedEdition->end_date->format('Y-m-d'))->toBe('2025-12-31')
            ->and($completedEdition->notes)->toBe('保持する既存年度開催 '.$seasonKey);
    }

    $autumnTournaments = Tournament::query()
        ->whereIn('id', $executed['created_tournament_ids'])
        ->orderBy('id')
        ->get();

    expect($autumnTournaments)->toHaveCount(4)
        ->and($autumnTournaments->pluck('setup_status')->unique()->all())->toBe(['draft'])
        ->and(DB::table('tournament_entries')->whereIn('tournament_id', $executed['created_tournament_ids'])->count())->toBe(0)
        ->and(DB::table('tournament_participants')->whereIn('tournament_id', $executed['created_tournament_ids'])->count())->toBe(0)
        ->and(DB::table('game_scores')->whereIn('tournament_id', $executed['created_tournament_ids'])->count())->toBe(0)
        ->and(DB::table('tournament_results')->whereIn('tournament_id', $executed['created_tournament_ids'])->count())->toBe(0);

    $rerun = $service->import();

    expect($rerun['edition_create_count'])->toBe(0)
        ->and($rerun['edition_update_count'])->toBe(0)
        ->and($rerun['tournament_create_count'])->toBe(0)
        ->and($rerun['tournament_existing_count'])->toBe(16)
        ->and($rerun['conflict_count'])->toBe(0);
});
