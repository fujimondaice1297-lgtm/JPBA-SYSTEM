<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
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

        return view('pro_bowler_seed_lists.index', [
            'seedLists' => $seedLists,
            'genderLabels' => $this->genderLabels(),
            'rankingYears' => $rankingYears,
            'availableRankingYears' => $availableRankingYears,
            'defaultBaseRankingYear' => $defaultBaseRankingYear,
            'defaultSeedYear' => $defaultSeedYear,
        ]);
    }

    /**
     * 前年度ポイントランキングから、翌年度の年度別シード一覧を自動生成する。
     *
     * 通常運用：
     * - 大会ごとにポイントを tournament_results.points へ反映
     * - 年度末に ranking_year のポイント合計でランキングを確定
     * - 翌年度の seed_year / gender ごとに上位24名をシード登録
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

        $rankingRows = $this->buildPointRankingRows(
            rankingYear: $baseRankingYear,
            gender: $gender,
            limit: $topCount
        );

        if (count($rankingRows) === 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'base_ranking_year' => '指定された年度・性別のポイントランキング対象者が見つかりませんでした。',
                ]);
        }

        DB::transaction(function () use ($validated, $rankingRows, $seedYear, $gender, $baseRankingYear, $topCount) {
            $seedList = ProBowlerSeedList::query()->firstOrNew([
                'seed_year' => $seedYear,
                'gender' => $gender,
                'seed_list_type' => 'tournament_seed',
            ]);

            $seedList->fill([
                'source_ranking_snapshot_id' => null,
                'base_ranking_year' => $baseRankingYear,
                'base_top_count' => $topCount,
                'as_of_date' => now()->toDateString(),
                'is_active' => true,
                'source_url' => route('tournament_results.rankings', ['year' => $baseRankingYear], false),
                'notes' => $validated['notes']
                    ?: "{$baseRankingYear}年ポイントランキング上位{$topCount}名から自動生成",
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
                    'ranking_snapshot_id' => null,
                    'ranking_rank' => $rank,
                    'source_tournament_id' => null,
                    'pro_bowler_title_id' => null,
                    'priority_order' => $rank,
                    'note' => 'points=' . $rankingRow['total_points'],
                    'is_active' => true,
                ]);
            }
        });

        $genderLabel = $this->genderLabels()[$gender] ?? $gender;

        return redirect()
            ->route('pro_bowler_seed_lists.index')
            ->with('status', "{$baseRankingYear}年{$genderLabel}ポイントランキング上位" . count($rankingRows) . "名から、{$seedYear}年{$genderLabel}シードを生成しました。");
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

        if (Schema::hasColumn('tournament_results', 'pro_bowler_id')) {
            $columns[] = 'pro_bowler_id';
        }

        $results = TournamentResult::query()
            ->where('ranking_year', $rankingYear)
            ->whereNotNull('points')
            ->where('points', '>', 0)
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
                    'total_points' => 0,
                ];
            }

            $rankings[$key]['total_points'] += (int) $result->points;
        }

        $rows = array_values($rankings);

        usort($rows, function (array $a, array $b) {
            if ($a['total_points'] === $b['total_points']) {
                return strcmp((string) $a['license_no'], (string) $b['license_no']);
            }

            return $b['total_points'] <=> $a['total_points'];
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

    private function genderLabels(): array
    {
        return [
            'M' => '男子',
            'F' => '女子',
        ];
    }
}
