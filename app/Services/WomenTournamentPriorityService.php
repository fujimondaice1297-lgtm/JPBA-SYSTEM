<?php

namespace App\Services;

use App\Models\ProBowler;
use Illuminate\Support\Collection;
use RuntimeException;

final class WomenTournamentPriorityService
{
    public const PERIOD_LABELS = [
        'upper' => '上半期',
        'lower' => '下半期',
    ];

    /** @return array<int,int> */
    public function years(): array
    {
        $years = collect(glob(database_path('data/jpba_official_*_women_priority_*.json')) ?: [])
            ->map(function (string $path): ?int {
                return preg_match('/jpba_official_(\d{4})_women_priority_/i', basename($path), $matches) === 1
                    ? (int) $matches[1]
                    : null;
            })
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years === [] ? [(int) now()->year] : $years;
    }

    /** @return array<string,mixed> */
    public function priority(int $year, string $period): array
    {
        $period = array_key_exists($period, self::PERIOD_LABELS) ? $period : 'lower';
        $path = database_path("data/jpba_official_{$year}_women_priority_{$period}.json");

        if (! is_file($path)) {
            return $this->emptyPriority($year, $period);
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! is_array($payload['rows'] ?? null)) {
            throw new RuntimeException("Invalid women tournament priority data: {$path}");
        }

        $this->assertPayload($payload, $year, $period, $path);

        $bowlers = ProBowler::query()
            ->whereIn('license_no', collect($payload['rows'])->pluck('license_no')->filter()->all())
            ->get([
                'id',
                'license_no',
                'name_kanji',
                'kibetsu',
                'organization_name',
                'equipment_contract',
            ])
            ->keyBy(fn (ProBowler $bowler): string => strtoupper((string) $bowler->license_no));

        $rows = collect($payload['rows'])
            ->map(fn (array $row): array => $this->decorateRow($row, $bowlers))
            ->values();

        $categoryGroups = $rows
            ->groupBy('category')
            ->map(function (Collection $group): array {
                return [
                    'key' => (string) $group->first()['category'],
                    'label' => (string) $group->first()['category_label'],
                    'count' => $group->count(),
                    'rows' => $group->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'year' => $year,
            'period' => $period,
            'period_label' => self::PERIOD_LABELS[$period],
            'title' => (string) ($payload['title'] ?? "{$year}年度".self::PERIOD_LABELS[$period].'女子トーナメント出場優先順位'),
            'as_of_date' => $payload['as_of_date'] ?? null,
            'row_count' => $rows->count(),
            'matched_count' => $rows->whereNotNull('pro_bowler_id')->count(),
            'unmatched_count' => $rows->whereNull('pro_bowler_id')->count(),
            'entry_only_count' => $rows->where('entry_only', true)->count(),
            'scored_qualifier_count' => $rows
                ->where('category', 'priority_tournament')
                ->where('entry_only', false)
                ->count(),
            'tournament_third_count' => $rows
                ->where('category', 'tournament_third')
                ->count(),
            'category_groups' => $categoryGroups,
            'rows' => $rows->all(),
        ];
    }

    /** @param Collection<string,ProBowler> $bowlers */
    private function decorateRow(array $row, Collection $bowlers): array
    {
        $licenseNo = strtoupper(trim((string) ($row['license_no'] ?? '')));
        $bowler = $bowlers->get($licenseNo);

        return $row + [
            'pro_bowler_id' => $bowler?->id,
            'display_name' => $bowler?->name_kanji ?: $licenseNo,
            'kibetsu' => $bowler?->kibetsu,
            'affiliation' => collect([
                $bowler?->organization_name,
                $bowler?->equipment_contract,
            ])->filter()->unique()->implode('/'),
        ];
    }

    private function assertPayload(array $payload, int $year, string $period, string $path): void
    {
        if ((int) ($payload['year'] ?? 0) !== $year || (string) ($payload['period'] ?? '') !== $period) {
            throw new RuntimeException("Women tournament priority metadata mismatch: {$path}");
        }

        $rows = collect($payload['rows']);
        $ranks = $rows->pluck('priority_rank')->map(fn ($rank): int => (int) $rank)->all();
        $expectedRanks = range(1, $rows->count());
        $licenses = $rows->pluck('license_no')->filter();

        if ($ranks !== $expectedRanks || $licenses->count() !== $licenses->unique()->count()) {
            throw new RuntimeException("Women tournament priority rows are not continuous and unique: {$path}");
        }
    }

    /** @return array<string,mixed> */
    private function emptyPriority(int $year, string $period): array
    {
        return [
            'year' => $year,
            'period' => $period,
            'period_label' => self::PERIOD_LABELS[$period],
            'title' => "{$year}年度".self::PERIOD_LABELS[$period].'女子トーナメント出場優先順位',
            'as_of_date' => null,
            'row_count' => 0,
            'matched_count' => 0,
            'unmatched_count' => 0,
            'entry_only_count' => 0,
            'scored_qualifier_count' => 0,
            'tournament_third_count' => 0,
            'category_groups' => [],
            'rows' => [],
        ];
    }
}
