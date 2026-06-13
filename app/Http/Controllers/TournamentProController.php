<?php

namespace App\Http\Controllers;

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
            'sectionLabels' => $this->sectionLabels(),
            'sections' => $sections,
            'seedLists' => $seedLists,
        ]);
    }

    private function buildSections(Collection $seedLists): array
    {
        $sections = [];

        foreach (array_keys($this->genderLabels()) as $gender) {
            $sections[$gender] = [];

            foreach (array_keys($this->sectionLabels()) as $sectionKey) {
                $sections[$gender][$sectionKey] = collect();
            }
        }

        foreach ($seedLists as $seedList) {
            $gender = in_array($seedList->gender, ['M', 'F'], true) ? $seedList->gender : 'M';

            if (! isset($sections[$gender])) {
                $sections[$gender] = [];
            }

            foreach (array_keys($this->sectionLabels()) as $sectionKey) {
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

                $sectionKey = $this->sectionKeyForPlayer($player);
                $sections[$gender][$sectionKey]->push($this->formatPlayerRow($player, $seedList));
            }
        }

        return $sections;
    }

    private function sectionKeyForPlayer(ProBowlerSeedListPlayer $player): string
    {
        $category = strtoupper((string) $player->seed_category);
        $note = mb_strtolower((string) $player->note);

        if ($category === ProBowlerSeedService::SEED_CATEGORY_PERMANENT) {
            return 'permanent_seed';
        }

        if ($category === ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT) {
            return 'semi_permanent_seed';
        }

        if (str_contains($note, '第2') || str_contains($note, '第二') || str_contains($note, 'second')) {
            return 'second_seed';
        }

        if ($category === ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED && (int) ($player->seed_rank ?? 0) > 24) {
            return 'second_seed';
        }

        return 'first_seed';
    }

    private function formatPlayerRow(ProBowlerSeedListPlayer $player, ProBowlerSeedList $seedList): array
    {
        $bowler = $player->bowler;

        return [
            'id' => $player->id,
            'seed_year' => $seedList->seed_year,
            'gender' => $seedList->gender,
            'seed_rank' => $player->seed_rank,
            'priority_order' => $player->priority_order,
            'license_no' => $player->license_no ?: $bowler?->license_no,
            'display_license_no' => $this->formatLicenseNo($player->license_no ?: $bowler?->license_no),
            'name_kanji' => $bowler?->name_kanji ?: '選手未特定',
            'name_kana' => $bowler?->name_kana ?: '-',
            'district' => $bowler?->district?->label ?? '-',
            'kibetsu' => $bowler?->kibetsu ? ($bowler->kibetsu . '期') : '-',
            'seed_category' => $player->seed_category,
            'seed_category_label' => $this->seedCategoryLabel($player->seed_category),
            'note' => $player->note ?: '-',
        ];
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

    private function sectionLabels(): array
    {
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
