<?php

namespace App\Services;

use App\Models\TournamentArchive;
use App\Models\TournamentFile;
use Illuminate\Support\Collection;

class OilPatternCatalogService
{
    public function __construct(private readonly TournamentArchiveMetadataExtractor $metadata) {}

    /** @return Collection<int,array<string,mixed>> */
    public function rows(bool $publicOnly = true): Collection
    {
        $archiveQuery = TournamentArchive::query()->with('tournament');
        if ($publicOnly) {
            $archiveQuery->publiclyVisible();
        }

        $archiveRows = $archiveQuery->get()->flatMap(function (TournamentArchive $archive): array {
            $venue = $archive->venue_name ?: $archive->tournament?->venue_name ?: $this->metadata->venue($archive->body_html);
            $organizer = $archive->organizer_name ?: $archive->tournament?->host ?: $this->metadata->organizer($archive->body_html);

            return collect($archive->assets ?: [])->map(function (array $asset, int $index) use ($archive, $venue, $organizer): ?array {
                if (($asset['type'] ?? '') !== 'oil_pattern' || empty($asset['path'])) {
                    return null;
                }

                return [
                    'key' => 'archive-'.$archive->id.'-'.$index,
                    'source_type' => 'archive',
                    'source_id' => $archive->id,
                    'classification' => $archive->classification,
                    'classification_label' => $archive->classification_label,
                    'year' => (int) $archive->year,
                    'start_on' => $archive->start_on,
                    'tournament_title' => $archive->title,
                    'venue_name' => $venue,
                    'organizer_name' => $organizer,
                    'pattern_title' => $this->patternTitle($asset['title'] ?? null),
                    'public_path' => ltrim((string) $asset['path'], '/'),
                    'is_public' => (bool) $archive->is_public,
                ];
            })->filter()->values()->all();
        });

        $fileQuery = TournamentFile::query()->with('tournament')->where('type', 'oil_pattern');
        if ($publicOnly) {
            $fileQuery->where('visibility', 'public');
        }
        $currentRows = $fileQuery->get()->map(function (TournamentFile $file): ?array {
            if (! $file->tournament) {
                return null;
            }

            return [
                'key' => 'current-'.$file->id,
                'source_type' => 'current',
                'source_id' => $file->tournament_id,
                'classification' => $file->tournament->official_type === 'approved' ? 'approved_event' : 'official_tournament',
                'classification_label' => $file->tournament->official_type === 'approved' ? '承認イベント' : '公認トーナメント',
                'year' => (int) ($file->tournament->year ?: $file->tournament->start_date?->year),
                'start_on' => $file->tournament->start_date,
                'tournament_title' => $file->tournament->name,
                'venue_name' => $file->tournament->venue_name,
                'organizer_name' => $file->tournament->host,
                'pattern_title' => trim((string) $file->title) ?: 'オイルパターン',
                'public_path' => 'storage/'.ltrim($file->file_path, '/'),
                'is_public' => $file->visibility === 'public',
            ];
        })->filter();

        return $archiveRows
            ->concat($currentRows)
            ->unique(fn (array $row): string => $row['source_type'].'|'.$row['source_id'].'|'.$row['public_path'])
            ->sortByDesc(fn (array $row): string => sprintf('%04d|%s|%s', $row['year'], $row['start_on']?->format('Y-m-d') ?? '0000-00-00', $row['tournament_title']))
            ->values();
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>  $filters
     * @return Collection<int,array<string,mixed>>
     */
    public function filter(Collection $rows, array $filters): Collection
    {
        $year = (int) ($filters['year'] ?? 0);
        $classification = in_array(($filters['classification'] ?? ''), ['official_tournament', 'approved_event'], true)
            ? (string) $filters['classification']
            : '';
        $venue = mb_strtolower(trim((string) ($filters['venue'] ?? '')));
        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));

        return $rows->filter(function (array $row) use ($year, $classification, $venue, $keyword): bool {
            if ($year > 0 && $row['year'] !== $year) {
                return false;
            }
            if ($classification !== '' && $row['classification'] !== $classification) {
                return false;
            }
            if ($venue !== '' && ! str_contains(mb_strtolower((string) $row['venue_name']), $venue)) {
                return false;
            }
            if ($keyword !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $row['tournament_title'], $row['pattern_title'], $row['venue_name'], $row['organizer_name'],
                ]));
                if (! str_contains($haystack, $keyword)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function patternTitle(mixed $title): string
    {
        $title = trim((string) $title);
        if ($title === '' || ! preg_match('/(?:oil|オイル|レーンコンディション)/iu', $title)) {
            return 'オイルパターン';
        }

        return $title;
    }
}
