<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VenueMasterImportService
{
    private const REQUIRED_COLUMNS = [
        'canonical_key',
        'aliases',
        'is_active',
        'source_url',
        'source_checked_at',
        'first_hosted_year',
        'last_hosted_year',
    ];

    public function __construct(private readonly VenueNameNormalizer $normalizer) {}

    public function import(bool $write = false): array
    {
        $this->assertSchemaReady();
        $payload = $this->loadDataset();
        $report = [
            'mode' => $write ? 'executed' : 'dry-run',
            'dataset' => $payload['dataset'],
            'source_index_url' => $payload['source_index_url'],
            'dataset_count' => count($payload['venues']),
            'created_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'linked_tournament_count' => 0,
            'created' => [],
            'updated' => [],
            'linked_tournaments' => [],
            'warnings' => [],
            'excluded_closed_venues' => $payload['excluded_closed_venues'] ?? [],
            'excluded_scopes' => $payload['excluded_scopes'] ?? [],
        ];

        $runner = function () use ($payload, $write, &$report): void {
            foreach ($payload['venues'] as $sourceRow) {
                $venue = $this->findVenue($sourceRow);
                $attributes = $this->attributesFor($sourceRow, $venue);

                if (! $venue) {
                    $report['created_count']++;
                    $report['created'][] = $sourceRow['name'];

                    if ($write) {
                        Venue::query()->create($attributes);
                    }

                    continue;
                }

                $dirty = [];
                foreach ($attributes as $key => $value) {
                    if (! $this->valuesEqual($venue->getAttribute($key), $value)) {
                        $dirty[$key] = $value;
                    }
                }

                if ($dirty === []) {
                    $report['unchanged_count']++;

                    continue;
                }

                $report['updated_count']++;
                $report['updated'][] = [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'fields' => array_keys($dirty),
                ];

                if ($write) {
                    $venue->fill($dirty)->save();
                }
            }

            $this->linkExistingTournaments($payload['venues'], $write, $report);
        };

        if ($write) {
            DB::transaction($runner);
        } else {
            $runner();
        }

        return $report;
    }

    private function loadDataset(): array
    {
        $path = database_path('data/jpba_venues_2022_2026.json');

        if (! is_file($path)) {
            throw new RuntimeException("Venue dataset was not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $venues = $payload['venues'] ?? null;

        if (! is_array($venues) || count($venues) !== 58) {
            throw new RuntimeException('Venue dataset must contain exactly 58 active domestic venues.');
        }

        $keys = [];
        foreach ($venues as $row) {
            foreach (['name', 'canonical_key', 'address', 'source_url'] as $required) {
                if (blank($row[$required] ?? null)) {
                    throw new RuntimeException("Venue dataset has a blank {$required} value.");
                }
            }

            $normalized = $this->normalizer->normalize($row['name']);
            if ($normalized !== $row['canonical_key']) {
                throw new RuntimeException("Venue canonical key mismatch: {$row['name']}");
            }

            if (isset($keys[$row['canonical_key']])) {
                throw new RuntimeException("Duplicate venue canonical key: {$row['canonical_key']}");
            }
            $keys[$row['canonical_key']] = true;
        }

        return $payload;
    }

    private function findVenue(array $sourceRow): ?Venue
    {
        $venue = Venue::query()->where('canonical_key', $sourceRow['canonical_key'])->first();

        if ($venue) {
            return $venue;
        }

        $lookupKeys = collect([$sourceRow['name'], ...($sourceRow['aliases'] ?? [])])
            ->map(fn ($name) => $this->normalizer->normalize($name))
            ->filter()
            ->unique()
            ->all();

        return Venue::query()->get()->first(function (Venue $candidate) use ($lookupKeys) {
            $candidateKeys = collect([$candidate->name, ...($candidate->aliases ?? [])])
                ->map(fn ($name) => $this->normalizer->normalize($name));

            return $candidateKeys->intersect($lookupKeys)->isNotEmpty();
        });
    }

    private function attributesFor(array $sourceRow, ?Venue $venue): array
    {
        [$prefecture, $city] = $this->splitAddress($sourceRow['address']);
        $sourceAliases = array_values(array_filter($sourceRow['aliases'] ?? []));

        if (! $venue) {
            return [
                'name' => $sourceRow['name'],
                'canonical_key' => $sourceRow['canonical_key'],
                'aliases' => $sourceAliases,
                'postal_code' => $sourceRow['postal_code'] ?? null,
                'address' => $sourceRow['address'],
                'prefecture' => $prefecture,
                'city' => $city,
                'tel' => $sourceRow['tel'] ?? null,
                'fax' => $sourceRow['fax'] ?? null,
                'website_url' => $sourceRow['website_url'] ?? null,
                'note' => $sourceRow['note'] ?? null,
                'is_active' => true,
                'source_url' => $sourceRow['source_url'],
                'source_checked_at' => $sourceRow['source_checked_at'],
                'first_hosted_year' => $sourceRow['first_source_year'] ?? null,
                'last_hosted_year' => $sourceRow['last_source_year'] ?? null,
            ];
        }

        $aliases = collect($venue->aliases ?? [])
            ->merge($sourceAliases)
            ->when(
                $this->normalizer->normalize($venue->name) !== $sourceRow['canonical_key'],
                fn ($items) => $items->push($sourceRow['name'])
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $attributes = [
            'canonical_key' => $sourceRow['canonical_key'],
            'aliases' => $aliases,
            'source_url' => $sourceRow['source_url'],
            'source_checked_at' => $sourceRow['source_checked_at'],
            'first_hosted_year' => $this->minimumYear($venue->first_hosted_year, $sourceRow['first_source_year'] ?? null),
            'last_hosted_year' => $this->maximumYear($venue->last_hosted_year, $sourceRow['last_source_year'] ?? null),
        ];

        foreach ([
            'postal_code' => $sourceRow['postal_code'] ?? null,
            'address' => $sourceRow['address'],
            'prefecture' => $prefecture,
            'city' => $city,
            'tel' => $sourceRow['tel'] ?? null,
            'fax' => $sourceRow['fax'] ?? null,
            'website_url' => $sourceRow['website_url'] ?? null,
            'note' => $sourceRow['note'] ?? null,
        ] as $key => $value) {
            $attributes[$key] = filled($venue->getAttribute($key)) ? $venue->getAttribute($key) : $value;
        }

        return $attributes;
    }

    private function linkExistingTournaments(array $sourceRows, bool $write, array &$report): void
    {
        if (! Schema::hasTable('tournaments')
            || ! Schema::hasColumn('tournaments', 'venue_id')
            || ! Schema::hasColumn('tournaments', 'venue_name')) {
            return;
        }

        $sourceByKey = [];
        foreach ($sourceRows as $row) {
            foreach ([$row['name'], ...($row['aliases'] ?? [])] as $name) {
                $sourceByKey[$this->normalizer->normalize($name)] = $row['canonical_key'];
            }
        }

        $venueIdsByKey = Venue::query()
            ->get()
            ->flatMap(function (Venue $venue) {
                return collect([$venue->name, ...($venue->aliases ?? [])])
                    ->mapWithKeys(fn ($name) => [$this->normalizer->normalize($name) => $venue->id]);
            });

        $tournaments = DB::table('tournaments')
            ->whereNull('venue_id')
            ->whereNotNull('venue_name')
            ->where('venue_name', '<>', '')
            ->get(['id', 'name', 'venue_name']);

        foreach ($tournaments as $tournament) {
            $nameKey = $this->normalizer->normalize($tournament->venue_name);
            $canonicalKey = $sourceByKey[$nameKey] ?? null;
            $venueId = $canonicalKey ? $venueIdsByKey->get($canonicalKey) : null;

            if (! $venueId && ! $write && $canonicalKey) {
                $report['linked_tournament_count']++;
                $report['linked_tournaments'][] = [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'venue_name' => $tournament->venue_name,
                ];

                continue;
            }

            if (! $venueId) {
                continue;
            }

            $report['linked_tournament_count']++;
            $report['linked_tournaments'][] = [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'venue_name' => $tournament->venue_name,
                'venue_id' => $venueId,
            ];

            if ($write) {
                DB::table('tournaments')->where('id', $tournament->id)->update(['venue_id' => $venueId]);
            }
        }
    }

    private function splitAddress(string $address): array
    {
        if (preg_match('/^(北海道|東京都|(?:京都|大阪)府|.{2,3}県)(.*)$/u', $address, $matches) !== 1) {
            return [null, null];
        }

        $prefecture = $matches[1];
        $rest = $matches[2];
        $city = null;

        foreach (['/^(.+?郡.+?[町村])/u', '/^(.+?市.+?区)/u', '/^(.+?[市区町村])/u'] as $pattern) {
            if (preg_match($pattern, $rest, $cityMatch) === 1) {
                $city = $cityMatch[1];
                break;
            }
        }

        return [$prefecture, $city];
    }

    private function minimumYear(mixed $existing, mixed $incoming): ?int
    {
        $years = array_filter([(int) $existing, (int) $incoming]);

        return $years === [] ? null : min($years);
    }

    private function maximumYear(mixed $existing, mixed $incoming): ?int
    {
        $years = array_filter([(int) $existing, (int) $incoming]);

        return $years === [] ? null : max($years);
    }

    private function valuesEqual(mixed $current, mixed $incoming): bool
    {
        if (is_array($current) || is_array($incoming)) {
            return array_values((array) $current) === array_values((array) $incoming);
        }

        if ($current instanceof \DateTimeInterface) {
            $current = $current->format('Y-m-d');
        }

        return (string) $current === (string) $incoming;
    }

    private function assertSchemaReady(): void
    {
        if (! Schema::hasTable('venues')) {
            throw new RuntimeException('venues table does not exist.');
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, Schema::getColumnListing('venues')));
        if ($missing !== []) {
            throw new RuntimeException('Run migrations before importing venues. Missing: '.implode(', ', $missing));
        }
    }
}
