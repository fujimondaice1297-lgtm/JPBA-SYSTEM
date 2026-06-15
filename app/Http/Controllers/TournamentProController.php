<?php

namespace App\Http\Controllers;

use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerSeedList;
use App\Models\ProBowlerSeedListPlayer;
use App\Services\ProBowlerSeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TournamentProController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = (int) now()->year;
        $selectedYear = (int) $request->query('year', $currentYear);
        $selectedGender = $request->query('gender');

        if (! in_array($selectedGender, ['M', 'F'], true)) {
            $selectedGender = null;
        }

        $availableYears = ProBowlerSeedList::query()
            ->where('seed_list_type', 'tournament_seed')
            ->select('seed_year')
            ->distinct()
            ->orderByDesc('seed_year')
            ->pluck('seed_year')
            ->map(fn ($year) => (int) $year)
            ->filter(fn ($year) => $year > 0)
            ->values();

        if (! $availableYears->contains($currentYear)) {
            $availableYears = collect([$currentYear])
                ->merge($availableYears)
                ->unique()
                ->sortDesc()
                ->values();
        }

        $seedLists = ProBowlerSeedList::query()
            ->with(['players.bowler.district'])
            ->where('seed_list_type', 'tournament_seed')
            ->where('is_active', true)
            ->where('seed_year', $selectedYear)
            ->when($selectedGender, fn ($query) => $query->where('gender', $selectedGender))
            ->orderBy('gender')
            ->get();

        $sections = $this->buildSections($seedLists);

        return view('tournament_pro.index', [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'selectedGender' => $selectedGender,
            'genderLabels' => $this->genderLabels(),
            'sectionLabelsByGender' => $this->sectionLabelsByGender(),
            'sections' => $sections,
            'seedLists' => $seedLists,
        ]);
    }

    private function buildSections(Collection $seedLists): array
    {
        $sections = [];

        foreach (array_keys($this->genderLabels()) as $gender) {
            $sections[$gender] = [];

            foreach (array_keys($this->sectionLabelsForGender($gender)) as $sectionKey) {
                $sections[$gender][$sectionKey] = collect();
            }
        }

        $rankingRowsByKey = $this->buildRankingRowsByKey($seedLists);

        foreach ($seedLists as $seedList) {
            $gender = in_array($seedList->gender, ['M', 'F'], true) ? $seedList->gender : 'M';

            if (! isset($sections[$gender])) {
                $sections[$gender] = [];
            }

            foreach (array_keys($this->sectionLabelsForGender($gender)) as $sectionKey) {
                $sections[$gender][$sectionKey] ??= collect();
            }

            $players = $seedList->players
                ->sortBy(function (ProBowlerSeedListPlayer $player) {
                    return sprintf(
                        '%08d-%08d-%08d',
                        (int) ($player->priority_order ?? 999999),
                        (int) ($player->seed_rank ?? 999999),
                        (int) $player->id
                    );
                })
                ->values();

            foreach ($players as $player) {
                if (! $player->is_active) {
                    continue;
                }

                $sectionKey = $this->sectionKeyForPlayer($player, $gender);

                if (! isset($sections[$gender][$sectionKey])) {
                    $sections[$gender][$sectionKey] = collect();
                }

                $sections[$gender][$sectionKey]->push($this->formatPlayerRow($player, $seedList, $rankingRowsByKey));
            }
        }

        return $sections;
    }

    private function sectionKeyForPlayer(ProBowlerSeedListPlayer $player, string $gender): string
    {
        $category = strtoupper((string) $player->seed_category);
        $note = mb_strtolower((string) $player->note);
        $seedRank = (int) ($player->seed_rank ?? 0);

        if ($category === ProBowlerSeedService::SEED_CATEGORY_PERMANENT) {
            return 'permanent_seed';
        }

        if ($category === ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT) {
            return 'semi_permanent_seed';
        }

        if ($gender === 'M') {
            return 'top_24_seed';
        }

        if (str_contains($note, '第2') || str_contains($note, '第二') || str_contains($note, 'second')) {
            return 'second_seed';
        }

        if ($category === ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED && $seedRank >= 19) {
            return 'second_seed';
        }

        return 'first_seed';
    }

    private function formatPlayerRow(ProBowlerSeedListPlayer $player, ProBowlerSeedList $seedList, Collection $rankingRowsByKey): array
    {
        $bowler = $player->bowler;
        $rankingRow = $this->findRankingRowForSeedPlayer($player, $rankingRowsByKey);

        return [
            'id' => $player->id,
            'seed_year' => $seedList->seed_year,
            'gender' => $seedList->gender,
            'seed_rank' => $player->seed_rank,
            'priority_order' => $player->priority_order,
            'license_no' => $player->license_no ?: $bowler?->license_no,
            'display_license_no' => $this->formatLicenseNo($player->license_no ?: $bowler?->license_no),
            'name_kanji' => $bowler?->name_kanji ?: '選手未特定',
            'name_kana' => $bowler?->name_kana ?: ($rankingRow?->name_kana ?: '-'),
            'district' => $bowler?->district?->label ?? '-',
            'kibetsu' => $this->formatKibetsu($rankingRow?->kibetsu ?? $bowler?->kibetsu),
            'points' => $this->formatPoints($rankingRow?->points),
            'prize_money' => $this->formatPrizeMoney($rankingRow?->prize_money),
            'seed_category' => $player->seed_category,
            'seed_category_label' => $this->seedCategoryLabel($player->seed_category),
            'note' => $player->note ?: '-',
        ];
    }


    private function buildRankingRowsByKey(Collection $seedLists): Collection
    {
        $pairs = collect();

        foreach ($seedLists as $seedList) {
            foreach ($seedList->players as $player) {
                if ($player->ranking_snapshot_id && $player->ranking_rank) {
                    $pairs->push([
                        'ranking_snapshot_id' => (int) $player->ranking_snapshot_id,
                        'ranking_rank' => (int) $player->ranking_rank,
                    ]);
                }
            }
        }

        $pairs = $pairs->unique(fn ($pair) => $pair['ranking_snapshot_id'] . ':' . $pair['ranking_rank'])->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $snapshotIds = $pairs->pluck('ranking_snapshot_id')->unique()->values();
        $rankingRanks = $pairs->pluck('ranking_rank')->unique()->values();

        return ProBowlerRankingRow::query()
            ->whereIn('ranking_snapshot_id', $snapshotIds)
            ->whereIn('ranking_rank', $rankingRanks)
            ->get()
            ->keyBy(fn (ProBowlerRankingRow $row) => $this->rankingRowKey((int) $row->ranking_snapshot_id, (int) $row->ranking_rank));
    }

    private function findRankingRowForSeedPlayer(ProBowlerSeedListPlayer $player, Collection $rankingRowsByKey): ?ProBowlerRankingRow
    {
        if (! $player->ranking_snapshot_id || ! $player->ranking_rank) {
            return null;
        }

        return $rankingRowsByKey->get($this->rankingRowKey((int) $player->ranking_snapshot_id, (int) $player->ranking_rank));
    }

    private function rankingRowKey(int $rankingSnapshotId, int $rankingRank): string
    {
        return $rankingSnapshotId . ':' . $rankingRank;
    }

    private function formatKibetsu(mixed $kibetsu): string
    {
        if ($kibetsu === null || $kibetsu === '') {
            return '-';
        }

        return ((int) $kibetsu) . '期';
    }

    private function formatPoints(mixed $points): string
    {
        if ($points === null || $points === '') {
            return '-';
        }

        $number = (float) $points;

        if (floor($number) === $number) {
            return number_format((int) $number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ','), '0'), '.');
    }

    private function formatPrizeMoney(mixed $prizeMoney): string
    {
        if ($prizeMoney === null || $prizeMoney === '') {
            return '-';
        }

        return number_format((int) $prizeMoney) . '円';
    }

    private function formatLicenseNo(?string $licenseNo): string
    {
        $licenseNo = strtoupper(trim((string) $licenseNo));

        if ($licenseNo === '') {
            return '-';
        }

        $last4 = mb_substr($licenseNo, -4);

        return $last4 !== '' ? $last4 : $licenseNo;
    }

    private function seedCategoryLabel(?string $category): string
    {
        return match ((string) $category) {
            ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED => 'トーナメントシード',
            ProBowlerSeedService::SEED_CATEGORY_PERMANENT => '永久シード',
            ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT => '準永久シード',
            ProBowlerSeedService::SEED_CATEGORY_ALL_JAPAN => '全日本枠',
            ProBowlerSeedService::SEED_CATEGORY_CURRENT_YEAR_WINNER => '当年優勝者',
            ProBowlerSeedService::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => '前年優勝者',
            ProBowlerSeedService::SEED_CATEGORY_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SEED_CATEGORY_MANUAL => '手動',
            default => (string) ($category ?: '-'),
        };
    }

    private function sectionLabelsByGender(): array
    {
        return [
            'M' => $this->sectionLabelsForGender('M'),
            'F' => $this->sectionLabelsForGender('F'),
        ];
    }

    private function sectionLabelsForGender(string $gender): array
    {
        if ($gender === 'M') {
            return [
                'top_24_seed' => '上位24名',
                'permanent_seed' => '永久シード',
                'semi_permanent_seed' => '準永久シード',
            ];
        }

        return [
            'first_seed' => '第1シード',
            'second_seed' => '第2シード',
            'permanent_seed' => '永久シード',
            'semi_permanent_seed' => '準永久シード',
        ];
    }

    private function genderLabels(): array
    {
        return [
            'M' => '男子',
            'F' => '女子',
        ];
    }
}
