<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentSeedPlayer;
use App\Services\ProBowlerSeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TournamentSeedPlayerController extends Controller
{
    public function index(Tournament $tournament)
    {
        $seedPlayers = TournamentSeedPlayer::query()
            ->with([
                'bowler',
                'seedListPlayer',
                'rankingSnapshot',
                'sourceTournament',
                'title',
            ])
            ->where('tournament_id', $tournament->id)
            ->where('is_active', true)
            ->orderByRaw('priority_order is null')
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        $priorityPlayers = $this->buildTournamentPriorityPlayers($tournament, $seedPlayers);

        return view('tournament_seed_players.index', [
            'tournament' => $tournament,
            'seedPlayers' => $seedPlayers,
            'priorityPlayers' => $priorityPlayers,
            'seedSourceOptions' => $this->seedSourceOptions(),
        ]);
    }

    public function store(Request $request, Tournament $tournament, ProBowlerSeedService $seedService)
    {
        $validated = $request->validate([
            'license_no' => ['required', 'string', 'max:50'],
            'seed_source_type' => ['required', 'string', Rule::in(array_keys($this->seedSourceOptions()))],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'display_label' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $licenseNo = $this->normalizeLicenseNo($validated['license_no']);
        $bowler = $this->findBowlerByLicenseNo($licenseNo);

        $seedService->addTournamentSeed(
            tournament: $tournament,
            bowler: $bowler,
            seedSourceType: $validated['seed_source_type'],
            attributes: [
                'license_no' => $licenseNo,
                'priority_order' => $validated['priority_order'] ?? null,
                'display_label' => $validated['display_label'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを追加しました。');
    }

    public function destroy(Tournament $tournament, TournamentSeedPlayer $seedPlayer)
    {
        if ((int) $seedPlayer->tournament_id !== (int) $tournament->id) {
            abort(404);
        }

        $seedPlayer->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを解除しました。');
    }

    private function buildTournamentPriorityPlayers(Tournament $tournament, $seedPlayers): array
    {
        $rows = [];

        foreach ($this->annualSeedRowsForTournament($tournament) as $row) {
            $key = $this->priorityKey(
                proBowlerId: $row->pro_bowler_id ? (int) $row->pro_bowler_id : null,
                licenseNo: $row->license_no ?? null
            );

            $rows[$key] = [
                'sort' => (int) ($row->priority_order ?? $row->seed_rank ?? $row->ranking_rank ?? 9999),
                'priority_order' => $row->priority_order ?? $row->seed_rank ?? $row->ranking_rank ?? null,
                'license_no' => $this->displayLicenseNo($row->license_no ?? null),
                'name' => $row->name_kanji ?? $row->name ?? $row->display_name ?? '-',
                'kana' => $row->name_kana ?? '',
                'source_label' => '年度別シード',
                'seed_label' => $this->seedCategoryLabel($row->seed_category ?? null),
                'ranking_rank' => $row->ranking_rank ?? null,
                'base_ranking_year' => $row->base_ranking_year ?? null,
                'note' => '前年度ランキング由来',
            ];
        }

        foreach ($seedPlayers as $seedPlayer) {
            $key = $this->priorityKey(
                proBowlerId: $seedPlayer->pro_bowler_id ? (int) $seedPlayer->pro_bowler_id : null,
                licenseNo: $seedPlayer->license_no
            );

            $sourceLabel = $this->seedSourceOptions()[$seedPlayer->seed_source_type] ?? $seedPlayer->seed_source_type;
            $bowlerName = $seedPlayer->bowler->name_kanji
                ?? $seedPlayer->bowler->name
                ?? $seedPlayer->bowler->display_name
                ?? '-';
            $bowlerKana = $seedPlayer->bowler->name_kana ?? '';

            if (isset($rows[$key])) {
                $rows[$key]['source_label'] .= ' / 大会別追加';
                $rows[$key]['seed_label'] = $seedPlayer->display_label ?: $rows[$key]['seed_label'];
                $rows[$key]['note'] = trim(($rows[$key]['note'] ?? '') . ' ' . ($seedPlayer->note ?? ''));
                if ($seedPlayer->priority_order !== null) {
                    $rows[$key]['sort'] = (int) $seedPlayer->priority_order;
                    $rows[$key]['priority_order'] = $seedPlayer->priority_order;
                }
                continue;
            }

            $rows[$key] = [
                'sort' => (int) ($seedPlayer->priority_order ?? 9000 + (int) $seedPlayer->id),
                'priority_order' => $seedPlayer->priority_order,
                'license_no' => $this->displayLicenseNo($seedPlayer->license_no),
                'name' => $bowlerName,
                'kana' => $bowlerKana,
                'source_label' => '大会別追加',
                'seed_label' => $seedPlayer->display_label ?: $sourceLabel,
                'ranking_rank' => $seedPlayer->ranking_rank,
                'base_ranking_year' => null,
                'note' => $seedPlayer->note ?? '',
            ];
        }

        $rows = array_values($rows);

        usort($rows, function (array $a, array $b) {
            return [$a['sort'], $a['license_no'], $a['name']] <=> [$b['sort'], $b['license_no'], $b['name']];
        });

        foreach ($rows as $index => &$row) {
            $row['priority_no'] = $index + 1;
        }

        return $rows;
    }

    private function annualSeedRowsForTournament(Tournament $tournament)
    {
        if (!Schema::hasTable('pro_bowler_seed_lists') || !Schema::hasTable('pro_bowler_seed_list_players')) {
            return collect();
        }

        $seedYear = $this->resolveTournamentYear($tournament);
        if ($seedYear === null) {
            return collect();
        }

        $gender = $this->normalizeGenderCode($tournament->gender ?? null);

        $query = DB::table('pro_bowler_seed_list_players as p')
            ->join('pro_bowler_seed_lists as l', 'l.id', '=', 'p.seed_list_id')
            ->leftJoin('pro_bowlers as b', 'b.id', '=', 'p.pro_bowler_id')
            ->where('l.seed_year', $seedYear)
            ->where('l.seed_list_type', 'tournament_seed')
            ->where('l.is_active', true)
            ->where('p.is_active', true)
            ->select([
                'p.id',
                'p.pro_bowler_id',
                'p.license_no',
                'p.seed_category',
                'p.seed_rank',
                'p.ranking_rank',
                'p.priority_order',
                'p.note',
                'l.base_ranking_year',
                'l.gender',
                'b.name_kanji',
                'b.name_kana',
            ]);

        if ($gender !== null) {
            $query->where(function ($q) use ($gender) {
                $q->where('l.gender', $gender)
                    ->orWhereNull('l.gender');
            });
        }

        return $query
            ->orderByRaw('p.priority_order is null')
            ->orderBy('p.priority_order')
            ->orderBy('p.seed_rank')
            ->orderBy('p.ranking_rank')
            ->orderBy('p.id')
            ->get();
    }

    private function seedSourceOptions(): array
    {
        return [
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24 => '前年度ランキング上位24名',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_RANKING => '当該年度ランキング',
            ProBowlerSeedService::SOURCE_PERMANENT_SEED => '永久シード',
            ProBowlerSeedService::SOURCE_SEMI_PERMANENT_SEED => '準永久シード',
            ProBowlerSeedService::SOURCE_ALL_JAPAN_CHAMPION => '全日本選手権者シード',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER => '当該年度優勝者シード',
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_WINNER => '前年度優勝者シード',
            ProBowlerSeedService::SOURCE_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SOURCE_MANUAL => '手動追加',
        ];
    }

    private function seedCategoryLabel(?string $seedCategory): string
    {
        return match ($seedCategory) {
            ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED => 'トーナメントシード',
            ProBowlerSeedService::SEED_CATEGORY_PERMANENT => '永久シード',
            ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT => '準永久シード',
            ProBowlerSeedService::SEED_CATEGORY_ALL_JAPAN => '全日本選手権者シード',
            ProBowlerSeedService::SEED_CATEGORY_CURRENT_YEAR_WINNER => '当該年度優勝者シード',
            ProBowlerSeedService::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => '前年度優勝者シード',
            ProBowlerSeedService::SEED_CATEGORY_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SEED_CATEGORY_MANUAL => '手動追加',
            default => $seedCategory ?: '-',
        };
    }

    private function findBowlerByLicenseNo(string $licenseNo): ?ProBowler
    {
        $candidates = array_values(array_filter([
            $licenseNo,
            $this->last4($licenseNo),
        ]));

        $licenseColumns = collect(['license_no', 'license_number', 'pro_bowler_license_no'])
            ->filter(fn (string $column) => Schema::hasColumn('pro_bowlers', $column))
            ->values();

        if ($licenseColumns->isEmpty()) {
            return null;
        }

        return ProBowler::query()
            ->where(function ($query) use ($licenseColumns, $candidates) {
                foreach ($licenseColumns as $column) {
                    foreach ($candidates as $candidate) {
                        $query->orWhere($column, $candidate);
                        $query->orWhere($column, 'like', '%' . $candidate);
                    }
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function resolveTournamentYear(Tournament $tournament): ?int
    {
        if (isset($tournament->year) && $tournament->year !== null && $tournament->year !== '') {
            return (int) $tournament->year;
        }

        if (isset($tournament->start_date) && $tournament->start_date !== null && $tournament->start_date !== '') {
            return (int) mb_substr((string) $tournament->start_date, 0, 4);
        }

        return null;
    }

    private function normalizeGenderCode($gender): ?string
    {
        $gender = trim((string) $gender);

        return match ($gender) {
            '1', 'M', 'm', 'male', 'Male', '男子', '男' => 'M',
            '2', 'F', 'f', 'female', 'Female', '女子', '女' => 'F',
            default => null,
        };
    }

    private function priorityKey(?int $proBowlerId, ?string $licenseNo): string
    {
        if ($proBowlerId !== null) {
            return 'pro:' . $proBowlerId;
        }

        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        return 'license:' . ($licenseNo ?: uniqid('unknown_', true));
    }

    private function displayLicenseNo(?string $licenseNo): string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        return $licenseNo ?: '-';
    }

    private function normalizeLicenseNo(?string $licenseNo): string
    {
        return strtoupper(trim((string) $licenseNo));
    }

    private function last4(?string $licenseNo): ?string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if ($licenseNo === '') {
            return null;
        }

        return mb_substr($licenseNo, -4);
    }
}
