<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use App\Models\ProBowlerSeedList;
use App\Models\ProBowlerSeedListPlayer;
use App\Models\TournamentResult;
use App\Services\ProBowlerSeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProBowlerSeedListController extends Controller
{
    public function index()
    {
        $availableRankingYears = TournamentResult::query()
            ->whereNotNull('ranking_year')
            ->select('ranking_year')
            ->distinct()
            ->orderByDesc('ranking_year')
            ->pluck('ranking_year')
            ->map(fn ($year) => (int) $year)
            ->filter(fn ($year) => $year > 0)
            ->values();

        // 通常運用は「当年シード = 前年度ポイントランキング」なので、
        // DBに前年度データがまだ無い場合でも前年度を選べるようにする。
        $defaultSeedYear = (int) now()->year;
        $defaultBaseRankingYear = $defaultSeedYear - 1;

        $rankingYears = collect([$defaultBaseRankingYear])
            ->merge($availableRankingYears)
            ->unique()
            ->sortDesc()
            ->values();

        $seedLists = ProBowlerSeedList::query()
            ->with(['players.bowler'])
            ->where('seed_list_type', 'tournament_seed')
            ->orderByDesc('seed_year')
            ->orderBy('gender')
            ->get();

        $rankingSnapshots = ProBowlerRankingSnapshot::query()
            ->where('ranking_type', 'points')
            ->where('ranking_scope', 'official_tournament')
            ->withCount('rows')
            ->orderByDesc('ranking_year')
            ->orderBy('gender')
            ->orderByDesc('as_of_date')
            ->limit(20)
            ->get();

        return view('pro_bowler_seed_lists.index', [
            'seedLists' => $seedLists,
            'rankingSnapshots' => $rankingSnapshots,
            'genderLabels' => $this->genderLabels(),
            'rankingYears' => $rankingYears,
            'availableRankingYears' => $availableRankingYears,
            'defaultBaseRankingYear' => $defaultBaseRankingYear,
            'defaultSeedYear' => $defaultSeedYear,
        ]);
    }

    public function show(ProBowlerSeedList $seedList)
    {
        $seedList->load(['players.bowler']);

        $rankingRowsByKey = $this->buildRankingRowsByKey($seedList->players);

        $players = $seedList->players
            ->sortBy(function (ProBowlerSeedListPlayer $player) {
                return sprintf(
                    '%08d-%08d-%08d',
                    (int) ($player->priority_order ?? 999999),
                    (int) ($player->seed_rank ?? 999999),
                    (int) $player->id
                );
            })
            ->values()
            ->map(function (ProBowlerSeedListPlayer $player) use ($rankingRowsByKey) {
                $rankingRow = $this->findRankingRowForSeedPlayer($player, $rankingRowsByKey);
                $licenseNo = $player->license_no ?: $player->bowler?->license_no;

                return [
                    'id' => $player->id,
                    'seed_rank' => $player->seed_rank,
                    'priority_order' => $player->priority_order,
                    'license_no' => $licenseNo,
                    'display_license_no' => $this->formatDisplayLicenseNo($licenseNo),
                    'name_kanji' => $player->bowler?->name_kanji ?: $rankingRow?->name_kanji,
                    'name_kana' => $player->bowler?->name_kana ?: $rankingRow?->name_kana,
                    'kibetsu' => $this->formatKibetsu($rankingRow?->kibetsu ?? $player->bowler?->kibetsu),
                    'points' => $this->formatPointsForDisplay($rankingRow?->points),
                    'prize_money' => $this->formatPrizeMoneyForDisplay($rankingRow?->prize_money),
                    'seed_category' => $player->seed_category,
                    'ranking_rank' => $player->ranking_rank,
                    'note' => $player->note,
                    'is_active' => (bool) $player->is_active,
                ];
            });

        $genderLabels = $this->genderLabels();

        $genderSwitchSeedLists = ProBowlerSeedList::query()
            ->where('seed_year', $seedList->seed_year)
            ->where('seed_list_type', $seedList->seed_list_type)
            ->orderByRaw("CASE WHEN gender = 'M' THEN 1 WHEN gender = 'F' THEN 2 ELSE 9 END")
            ->orderBy('gender')
            ->get()
            ->keyBy('gender');

        return view('pro_bowler_seed_lists.show', [
            'seedList' => $seedList,
            'players' => $players,
            'genderLabels' => $genderLabels,
            'genderSwitchSeedLists' => $genderSwitchSeedLists,
            'seedCategoryLabels' => $this->seedCategoryLabels(),
        ]);
    }

    /**
     * 前年度ポイントランキングから、翌年度の年度別シード一覧を自動生成する。
     *
     * 通常運用：
     * - 年度末に /rankings から公式最終ポイントランキングを snapshot 確定する
     * - 翌年度の seed_year / gender ごとに、確定 snapshot からシード登録する
     * - 公式 snapshot がまだ無い場合だけ、既存の tournament_results 集計から生成する
     */
    public function generateFromPointRanking(Request $request)
    {
        $validated = $request->validate([
            'seed_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'gender' => ['required', 'in:M,F'],
            'base_ranking_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'base_top_count' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $seedYear = (int) $validated['seed_year'];
        $gender = $validated['gender'];
        $baseRankingYear = (int) $validated['base_ranking_year'];
        $topCount = (int) $validated['base_top_count'];
        $genderLabel = $this->genderLabels()[$gender] ?? $gender;

        $officialSnapshot = ProBowlerRankingSnapshot::query()
            ->where('ranking_year', $baseRankingYear)
            ->where('gender', $gender)
            ->where('ranking_type', 'points')
            ->where('ranking_scope', 'official_tournament')
            ->where('is_final', true)
            ->withCount('rows')
            ->orderByDesc('as_of_date')
            ->orderByDesc('id')
            ->first();

        if ($officialSnapshot && (int) $officialSnapshot->rows_count > 0) {
            $seedList = app(ProBowlerSeedService::class)->createSeedListFromRanking(
                rankingSnapshot: $officialSnapshot,
                seedYear: $seedYear,
                gender: $gender,
                topCount: $topCount,
                options: [
                    'notes' => $validated['notes']
                        ?: "{$baseRankingYear}年公式最終ポイントランキング上位{$topCount}名から自動生成",
                    'source_url' => $officialSnapshot->source_url,
                    'as_of_date' => $officialSnapshot->as_of_date,
                    'is_active' => true,
                ]
            );

            return redirect()
                ->route('pro_bowler_seed_lists.show', $seedList)
                ->with('status', "{$baseRankingYear}年{$genderLabel}公式最終ポイントランキング上位{$seedList->players()->count()}名から、{$seedYear}年{$genderLabel}シードを生成しました。");
        }

        $rankingRows = $this->buildPointRankingRows(
            rankingYear: $baseRankingYear,
            gender: $gender,
            limit: $topCount
        );

        if (count($rankingRows) === 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'base_ranking_year' => '指定された年度・性別の確定ランキングが見つかりませんでした。先に「公式ランキング管理」で年度末ランキングを取り込むか、大会成績一覧でポイント再計算が済んでいるか確認してください。',
                ]);
        }

        $rankingSnapshot = null;

        DB::transaction(function () use ($validated, $rankingRows, $seedYear, $gender, $baseRankingYear, $topCount, &$rankingSnapshot) {
            $snapshotNotes = $validated['notes']
                ?: "{$baseRankingYear}年DB内ポイントランキング上位{$topCount}名から自動生成";

            $rankingSnapshot = ProBowlerRankingSnapshot::query()->firstOrNew([
                'ranking_year' => $baseRankingYear,
                'gender' => $gender,
                'ranking_type' => 'points',
                'ranking_scope' => 'official_tournament',
                'is_final' => true,
            ]);

            $rankingSnapshot->fill([
                'as_of_date' => now()->toDateString(),
                'source_url' => $this->buildPointRankingUrl($baseRankingYear, $gender),
                'notes' => $snapshotNotes,
            ]);
            $rankingSnapshot->save();

            ProBowlerRankingRow::query()
                ->where('ranking_snapshot_id', $rankingSnapshot->id)
                ->delete();

            foreach ($rankingRows as $index => $rankingRow) {
                $rank = $index + 1;

                ProBowlerRankingRow::query()->create([
                    'ranking_snapshot_id' => $rankingSnapshot->id,
                    'ranking_rank' => $rank,
                    'pro_bowler_id' => $rankingRow['pro_bowler_id'],
                    'license_no' => $rankingRow['license_no'],
                    'name_kanji' => $rankingRow['name_kanji'],
                    'name_kana' => $rankingRow['name_kana'] ?? null,
                    'kibetsu' => $rankingRow['kibetsu'] ?? null,
                    'organization_name' => $rankingRow['organization_name'] ?? null,
                    'equipment_contract' => $rankingRow['equipment_contract'] ?? null,
                    'points' => $rankingRow['total_points'],
                    'games' => $rankingRow['total_games'] ?? null,
                    'total_pin' => $rankingRow['total_pin'] ?? null,
                    'average' => $rankingRow['average'] ?? null,
                    'prize_money' => $rankingRow['prize_money'] ?? null,
                    'sort_order' => $rank,
                ]);
            }

            $seedList = ProBowlerSeedList::query()->firstOrNew([
                'seed_year' => $seedYear,
                'gender' => $gender,
                'seed_list_type' => 'tournament_seed',
            ]);

            $seedList->fill([
                'source_ranking_snapshot_id' => $rankingSnapshot->id,
                'base_ranking_year' => $baseRankingYear,
                'base_top_count' => $topCount,
                'as_of_date' => $rankingSnapshot->as_of_date,
                'is_active' => true,
                'source_url' => $rankingSnapshot->source_url,
                'notes' => $validated['notes']
                    ?: "{$baseRankingYear}年DB内ポイントランキング上位{$topCount}名から自動生成",
            ]);
            $seedList->save();

            ProBowlerSeedListPlayer::query()
                ->where('seed_list_id', $seedList->id)
                ->delete();

            foreach ($rankingRows as $index => $rankingRow) {
                $rank = $index + 1;

                ProBowlerSeedListPlayer::query()->create([
                    'seed_list_id' => $seedList->id,
                    'pro_bowler_id' => $rankingRow['pro_bowler_id'],
                    'license_no' => $rankingRow['license_no'],
                    'seed_category' => ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED,
                    'seed_rank' => $rank,
                    'ranking_snapshot_id' => $rankingSnapshot->id,
                    'ranking_rank' => $rank,
                    'source_tournament_id' => null,
                    'pro_bowler_title_id' => null,
                    'priority_order' => $rank,
                    'note' => 'points=' . $this->formatPointValue($rankingRow['total_points']),
                    'is_active' => true,
                ]);
            }
        });

        return redirect()
            ->route('pro_bowler_seed_lists.index')
            ->with('status', "{$baseRankingYear}年{$genderLabel}のDB内ポイントランキング上位" . count($rankingRows) . "名を保存し、{$seedYear}年{$genderLabel}シードを生成しました。");
    }

    /**
     * 例外対応用：ライセンスNo貼り付けで年度別シード一覧を直接作る。
     *
     * 通常は generateFromPointRanking() を使う。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'seed_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'gender' => ['required', 'in:M,F'],
            'base_ranking_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'base_top_count' => ['required', 'integer', 'min:1', 'max:100'],
            'source_url' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'license_rows' => ['required', 'string'],
        ]);

        $licenses = $this->parseLicenseRows(
            text: $validated['license_rows'],
            limit: (int) $validated['base_top_count']
        );

        if (count($licenses) === 0) {
            return back()
                ->withInput()
                ->withErrors(['license_rows' => 'ライセンスNoを1件以上入力してください。']);
        }

        DB::transaction(function () use ($validated, $licenses) {
            $seedList = ProBowlerSeedList::query()->firstOrNew([
                'seed_year' => (int) $validated['seed_year'],
                'gender' => $validated['gender'],
                'seed_list_type' => 'tournament_seed',
            ]);

            $seedList->fill([
                'source_ranking_snapshot_id' => null,
                'base_ranking_year' => (int) $validated['base_ranking_year'],
                'base_top_count' => (int) $validated['base_top_count'],
                'as_of_date' => now()->toDateString(),
                'is_active' => true,
                'source_url' => $validated['source_url'] ?? null,
                'notes' => $validated['notes'] ?: '手入力で年度別シード一覧を作成',
            ]);
            $seedList->save();

            ProBowlerSeedListPlayer::query()
                ->where('seed_list_id', $seedList->id)
                ->delete();

            foreach ($licenses as $index => $licenseNo) {
                $bowler = $this->findBowlerByLicenseNo($licenseNo, $validated['gender']);
                $rank = $index + 1;

                ProBowlerSeedListPlayer::query()->create([
                    'seed_list_id' => $seedList->id,
                    'pro_bowler_id' => $bowler?->id,
                    'license_no' => $bowler?->license_no ?: $licenseNo,
                    'seed_category' => ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED,
                    'seed_rank' => $rank,
                    'ranking_snapshot_id' => null,
                    'ranking_rank' => $rank,
                    'source_tournament_id' => null,
                    'pro_bowler_title_id' => null,
                    'priority_order' => $rank,
                    'note' => null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()
            ->route('pro_bowler_seed_lists.index')
            ->with('status', '年度別シード一覧を手入力で保存しました。');
    }

    public function destroy(ProBowlerSeedList $seedList)
    {
        DB::transaction(function () use ($seedList) {
            ProBowlerSeedListPlayer::query()
                ->where('seed_list_id', $seedList->id)
                ->delete();

            $seedList->delete();
        });

        return redirect()
            ->route('pro_bowler_seed_lists.index')
            ->with('status', '年度別シード一覧を削除しました。');
    }

    private function buildPointRankingRows(int $rankingYear, string $gender, int $limit): array
    {
        $columns = ['id', 'pro_bowler_license_no', 'points'];

        foreach (['pro_bowler_id', 'games', 'total_pin', 'prize_money'] as $optionalColumn) {
            if (Schema::hasColumn('tournament_results', $optionalColumn)) {
                $columns[] = $optionalColumn;
            }
        }

        $results = TournamentResult::query()
            ->where('ranking_year', $rankingYear)
            ->whereNotNull('points')
            ->where('points', '>', 0)
            ->whereHas('tournament', fn ($query) => $query->where('counts_for_official_points', true))
            ->get($columns);

        $rankings = [];

        foreach ($results as $result) {
            $bowler = $this->resolveBowlerForResult($result, $gender);

            if (!$bowler || !$this->bowlerMatchesGender($bowler, $gender)) {
                continue;
            }

            $key = 'bowler:' . $bowler->id;

            if (!isset($rankings[$key])) {
                $rankings[$key] = [
                    'pro_bowler_id' => $bowler->id,
                    'license_no' => $bowler->license_no,
                    'name_kanji' => $bowler->name_kanji,
                    'name_kana' => $bowler->name_kana ?? null,
                    'kibetsu' => $bowler->kibetsu ?? null,
                    'organization_name' => $bowler->organization_name
                        ?? $bowler->affiliation
                        ?? $bowler->belongs_to
                        ?? null,
                    'equipment_contract' => $bowler->equipment_contract
                        ?? $bowler->contract_maker
                        ?? null,
                    'total_points' => 0.0,
                    'total_games' => 0,
                    'total_pin' => 0,
                    'prize_money' => 0,
                    'average' => null,
                ];
            }

            $rankings[$key]['total_points'] += (float) $result->points;
            $rankings[$key]['total_games'] += (int) ($result->games ?? 0);
            $rankings[$key]['total_pin'] += (int) ($result->total_pin ?? 0);
            $rankings[$key]['prize_money'] += (int) ($result->prize_money ?? 0);
        }

        $rows = array_values($rankings);

        foreach ($rows as &$row) {
            $row['average'] = $row['total_games'] > 0
                ? round($row['total_pin'] / $row['total_games'], 2)
                : null;
        }
        unset($row);

        usort($rows, function (array $a, array $b) {
            if ((float) $a['total_points'] === (float) $b['total_points']) {
                if ((int) $a['total_pin'] === (int) $b['total_pin']) {
                    return strcmp((string) $a['license_no'], (string) $b['license_no']);
                }

                return (int) $b['total_pin'] <=> (int) $a['total_pin'];
            }

            return (float) $b['total_points'] <=> (float) $a['total_points'];
        });

        return array_slice($rows, 0, $limit);
    }

    private function resolveBowlerForResult(TournamentResult $result, string $gender): ?ProBowler
    {
        $proBowlerId = $result->pro_bowler_id ?? null;

        if ($proBowlerId) {
            $bowler = ProBowler::query()->find($proBowlerId);

            if ($bowler) {
                return $bowler;
            }
        }

        return $this->findBowlerByLicenseNo((string) ($result->pro_bowler_license_no ?? ''), $gender);
    }

    private function parseLicenseRows(string $text, int $limit): array
    {
        $licenses = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $licenseNo = $this->extractLicenseNo($line);

            if ($licenseNo === null) {
                continue;
            }

            $licenses[] = $licenseNo;

            if (count($licenses) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($licenses));
    }

    private function extractLicenseNo(string $line): ?string
    {
        $line = str_replace('　', ' ', trim($line));

        if ($line === '') {
            return null;
        }

        $line = preg_replace('/[,\t]+/u', ' ', $line);
        $tokens = preg_split('/\s+/u', $line) ?: [];

        foreach ($tokens as $token) {
            $token = strtoupper(trim($token));
            $token = preg_replace('/[^A-Z0-9]/', '', $token);

            if ($token === '') {
                continue;
            }

            if (preg_match('/^[A-Z]?\d{3,}$/', $token)) {
                return $token;
            }
        }

        return null;
    }

    private function findBowlerByLicenseNo(string $licenseNo, ?string $gender = null): ?ProBowler
    {
        $licenseNo = strtoupper(trim($licenseNo));

        if ($licenseNo === '') {
            return null;
        }

        $query = ProBowler::query()
            ->where(function ($q) use ($licenseNo) {
                $q->whereRaw('UPPER(license_no) = ?', [$licenseNo]);

                if (preg_match('/(\d{3,})$/', $licenseNo, $matches)) {
                    $digits = mb_substr($matches[1], -4);
                    $q->orWhereRaw('RIGHT(license_no, ?) = ?', [mb_strlen($digits), $digits]);

                    if (Schema::hasColumn('pro_bowlers', 'license_no_num') && ctype_digit($digits)) {
                        $q->orWhere('license_no_num', (int) ltrim($digits, '0'));
                    }
                }
            });

        if ($gender !== null) {
            $sexValues = $this->sexValuesForGender($gender);

            if ($sexValues !== []) {
                $query->whereIn('sex', $sexValues);
            }
        }

        return $query
            ->orderBy('id')
            ->first();
    }

    private function bowlerMatchesGender(ProBowler $bowler, string $gender): bool
    {
        $sexValues = array_map('strval', $this->sexValuesForGender($gender));

        return in_array((string) $bowler->sex, $sexValues, true);
    }

    private function sexValuesForGender(string $gender): array
    {
        return match ($gender) {
            'M' => [1, '1', 'M', 'm', 'male', '男性', '男子', '男'],
            'F' => [2, '2', 'F', 'f', 'female', '女性', '女子', '女'],
            default => [],
        };
    }

    private function buildPointRankingUrl(int $rankingYear, string $gender): string
    {
        return route('tournament_results.rankings', [
            'year' => $rankingYear,
            'gender' => $gender,
        ], false);
    }


    private function buildRankingRowsByKey($seedPlayers)
    {
        $pairs = collect($seedPlayers)
            ->filter(fn ($player) => $player->ranking_snapshot_id && $player->ranking_rank)
            ->map(fn ($player) => [
                'ranking_snapshot_id' => (int) $player->ranking_snapshot_id,
                'ranking_rank' => (int) $player->ranking_rank,
            ])
            ->unique(fn ($pair) => $pair['ranking_snapshot_id'] . ':' . $pair['ranking_rank'])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        return ProBowlerRankingRow::query()
            ->whereIn('ranking_snapshot_id', $pairs->pluck('ranking_snapshot_id')->unique()->values())
            ->whereIn('ranking_rank', $pairs->pluck('ranking_rank')->unique()->values())
            ->get()
            ->keyBy(fn (ProBowlerRankingRow $row) => $this->rankingRowKey((int) $row->ranking_snapshot_id, (int) $row->ranking_rank));
    }

    private function findRankingRowForSeedPlayer(ProBowlerSeedListPlayer $player, $rankingRowsByKey): ?ProBowlerRankingRow
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

    private function formatDisplayLicenseNo(?string $licenseNo): string
    {
        $licenseNo = strtoupper(trim((string) $licenseNo));

        if ($licenseNo === '') {
            return '-';
        }

        $last4 = mb_substr($licenseNo, -4);

        return $last4 !== '' ? $last4 : $licenseNo;
    }

    private function formatKibetsu(mixed $kibetsu): string
    {
        if ($kibetsu === null || $kibetsu === '') {
            return '-';
        }

        return ((int) $kibetsu) . '期';
    }

    private function formatPointsForDisplay(mixed $points): string
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

    private function formatPrizeMoneyForDisplay(mixed $prizeMoney): string
    {
        if ($prizeMoney === null || $prizeMoney === '') {
            return '-';
        }

        return number_format((int) $prizeMoney) . '円';
    }

    private function pointTextFromNote(?string $note): string
    {
        $note = trim((string) $note);

        if ($note === '') {
            return '-';
        }

        if (preg_match('/points=([0-9]+(?:\.[0-9]+)?)/', $note, $matches)) {
            return number_format((float) $matches[1], str_contains($matches[1], '.') ? 2 : 0);
        }

        return $note;
    }

    private function formatPointValue(float|int|string $value): string
    {
        $number = (float) $value;

        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function seedCategoryLabels(): array
    {
        return [
            ProBowlerSeedService::SEED_CATEGORY_TOURNAMENT_SEED => 'トーナメントシード',
            ProBowlerSeedService::SEED_CATEGORY_PERMANENT => '永久シード',
            ProBowlerSeedService::SEED_CATEGORY_SEMI_PERMANENT => '準永久シード',
            ProBowlerSeedService::SEED_CATEGORY_ALL_JAPAN => '全日本枠',
            ProBowlerSeedService::SEED_CATEGORY_CURRENT_YEAR_WINNER => '当年優勝者',
            ProBowlerSeedService::SEED_CATEGORY_PREVIOUS_YEAR_WINNER => '前年優勝者',
            ProBowlerSeedService::SEED_CATEGORY_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SEED_CATEGORY_MANUAL => '手動',
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
