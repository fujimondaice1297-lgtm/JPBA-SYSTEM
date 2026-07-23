<?php

namespace App\Http\Controllers;

use App\Models\PointDistribution;
use App\Models\PrizeDistribution;
use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentMatchScoreSheet;
use App\Models\TournamentResult;
use App\Models\TournamentResultPublication;
use App\Models\TournamentResultSnapshot;
use App\Services\MatchScoreSheetImageService;
use App\Services\MixedOrientationPdfService;
use App\Services\RoundRobinService;
use App\Services\ShootoutBracketImageService;
use App\Services\ShootoutService;
use App\Services\SingleEliminationBracketImageService;
use App\Services\SingleEliminationService;
use App\Services\StepLadderBracketImageService;
use App\Services\StepLadderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentResultController extends Controller
{
    /* ---- 大会成績トップ（大会検索） ---- */
    public function list(Request $request)
    {
        $q = Tournament::query();
        if ($request->filled('year')) {
            $q->where('year', $request->year);
        }
        if ($request->filled('name')) {
            $q->where('name', 'like', '%'.$request->name.'%');
        }
        $tournaments = $q->orderByDesc('year')->orderBy('start_date')->get();

        // 既存のビュー（tournament_results/index.blade.php）が $tournaments を期待
        return view('tournament_results.index', compact('tournaments'));
    }

    /** 大会ごとの成績一覧（←“成績一覧” 入口） */
    public function index(Tournament $tournament)
    {
        $rankCol = collect(['ranking', 'rank', 'position', 'placing', 'result_rank', 'order_no'])
            ->first(fn ($c) => Schema::hasColumn('tournament_results', $c));

        $q = TournamentResult::with(['bowler', 'player'])
            ->where('tournament_id', $tournament->id);

        $rankCol ? $q->orderBy($rankCol) : $q->orderBy('id');
        $results = $q->get();
        $resultIndexSnapshot = null;
        $currentPublication = $this->currentResultPublication((int) $tournament->id);

        $snapshotIndexData = $this->buildResultIndexSnapshotRows($tournament, $results);
        if (is_array($snapshotIndexData)) {
            $results = $snapshotIndexData['rows'];
            $resultIndexSnapshot = $snapshotIndexData['snapshot'];
        }

        if ($results->isEmpty()) {
            $hasSnapshots = TournamentResultSnapshot::query()
                ->where('tournament_id', $tournament->id)
                ->where('is_current', true)
                ->exists();

            if ($hasSnapshots) {
                return redirect()
                    ->route('tournaments.result_snapshots.index', $tournament)
                    ->with('info', 'このページは最終成績のみ表示します。現在は途中成績スナップショットが反映済みのため、正式成績反映ページへ移動しました。');
            }
        }

        return view('tournament_results.show', compact('tournament', 'results', 'resultIndexSnapshot', 'currentPublication'));
    }

    private function buildResultIndexSnapshotRows(Tournament $tournament, Collection $currentResults): ?array
    {
        $currentResultCount = $currentResults->count();
        $snapshots = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->where('is_current', true)
            ->whereIn('result_code', ['prelim_total', 'semifinal_total', 'round_robin_total', 'shootout_final'])
            ->orderByDesc('id')
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        $seasonTrialRows = $this->buildSeasonTrialResultIndexRows($tournament, $currentResults, $snapshots);
        if (is_array($seasonTrialRows)) {
            return $seasonTrialRows;
        }

        $candidates = $snapshots->map(function ($snapshot) {
            $rowCount = DB::table('tournament_result_snapshot_rows')
                ->where('snapshot_id', $snapshot->id)
                ->count();

            return [
                'snapshot' => $snapshot,
                'row_count' => $rowCount,
                'priority' => match ((string) ($snapshot->result_code ?? '')) {
                    'prelim_total' => 1,
                    'semifinal_total' => 2,
                    'round_robin_total' => 3,
                    'shootout_final' => 4,
                    default => 9,
                },
            ];
        })
            ->filter(fn (array $candidate): bool => (int) $candidate['row_count'] > $currentResultCount)
            ->sort(fn (array $a, array $b): int => ((int) $b['row_count'] <=> (int) $a['row_count'])
                ?: ((int) $a['priority'] <=> (int) $b['priority']))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $snapshot = $candidates->first()['snapshot'];
        $rows = $this->loadResultIndexSnapshotRows($snapshot)
            ->map(fn ($row) => $this->makeResultIndexSnapshotPreviewRow($tournament, $row));

        return [
            'snapshot' => $snapshot,
            'rows' => $rows,
        ];
    }

    /* ---- 以降はあなたの現状ロジックを維持 ---- */

    private function buildSeasonTrialResultIndexRows(Tournament $tournament, Collection $currentResults, Collection $snapshots): ?array
    {
        if (! $this->isSeasonTrialTournament($tournament)) {
            return null;
        }

        $prelimSnapshot = $snapshots->first(fn ($snapshot): bool => (string) ($snapshot->result_code ?? '') === 'prelim_total');
        if (! $prelimSnapshot) {
            return null;
        }

        $prelimRows = $this->loadResultIndexSnapshotRows($prelimSnapshot);
        if ($prelimRows->count() <= $currentResults->count()) {
            return null;
        }

        $semifinalSnapshot = $snapshots->first(fn ($snapshot): bool => (string) ($snapshot->result_code ?? '') === 'semifinal_total');
        $semifinalRows = $semifinalSnapshot ? $this->loadResultIndexSnapshotRows($semifinalSnapshot) : collect();

        $rows = [];
        $usedKeys = [];
        $maxCurrentRank = 0;

        foreach ($currentResults->sortBy(fn ($result) => $this->resultIndexRankValue($result))->values() as $result) {
            $rank = $this->resultIndexRankValue($result);
            if ($rank > 0) {
                $maxCurrentRank = max($maxCurrentRank, $rank);
            }

            $key = $this->resultIndexIdentityKey($result);
            if ($key !== '') {
                $usedKeys[$key] = true;
            }

            $rows[] = $result;
        }

        $maxSemifinalRank = 0;
        foreach ($semifinalRows as $row) {
            $rank = $this->resultIndexRankValue($row);
            if ($rank > 0) {
                $maxSemifinalRank = max($maxSemifinalRank, $rank);
            }

            if ($rank > 0 && $rank <= $maxCurrentRank) {
                continue;
            }

            $key = $this->resultIndexIdentityKey($row);
            if ($key !== '' && isset($usedKeys[$key])) {
                continue;
            }

            if ($key !== '') {
                $usedKeys[$key] = true;
            }

            $stepPoints = $this->numericOrNull($row->points ?? null);
            $rows[] = $this->makeResultIndexSnapshotPreviewRow($tournament, $row, [
                'points' => $stepPoints ?? 0,
                'award_points' => 0,
                'step_points' => $stepPoints ?? 0,
                'prize_money' => $this->numericOrNull($row->prize_money ?? null),
            ]);
        }

        $prelimStartRank = max($maxCurrentRank, $maxSemifinalRank);
        foreach ($prelimRows as $row) {
            $rank = $this->resultIndexRankValue($row);
            if ($rank > 0 && $rank <= $prelimStartRank) {
                continue;
            }

            $key = $this->resultIndexIdentityKey($row);
            if ($key !== '' && isset($usedKeys[$key])) {
                continue;
            }

            if ($key !== '') {
                $usedKeys[$key] = true;
            }

            $rows[] = $this->makeResultIndexSnapshotPreviewRow($tournament, $row, [
                'points' => 0,
                'award_points' => 0,
                'step_points' => 0,
                'prize_money' => null,
            ]);
        }

        if (count($rows) <= $currentResults->count()) {
            return null;
        }

        return [
            'snapshot' => $prelimSnapshot,
            'rows' => collect($rows)
                ->sortBy(fn ($row) => $this->resultIndexRankValue($row) ?: PHP_INT_MAX)
                ->values(),
        ];
    }

    private function loadResultIndexSnapshotRows(object $snapshot): Collection
    {
        return DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshot->id)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->get();
    }

    private function makeResultIndexSnapshotPreviewRow(Tournament $tournament, object $row, array $overrides = []): \stdClass
    {
        $result = new \stdClass;
        $result->id = null;
        $result->is_snapshot_preview = true;
        $result->snapshot_row_id = $row->id ?? null;
        $result->tournament_id = $tournament->id;
        $result->ranking_year = $tournament->year;
        $result->ranking = $overrides['ranking'] ?? ($row->ranking ?? null);
        $result->points = $overrides['points'] ?? ($row->points ?? 0);
        $result->award_points = $overrides['award_points'] ?? null;
        $result->step_points = $overrides['step_points'] ?? null;
        $result->total_pin = $overrides['total_pin'] ?? ($row->total_pin ?? 0);
        $result->games = $overrides['games'] ?? ($row->games ?? null);
        $result->average = $overrides['average'] ?? ($row->average ?? null);
        $result->prize_money = array_key_exists('prize_money', $overrides) ? $overrides['prize_money'] : ($row->prize_money ?? null);
        $result->pro_bowler_id = $row->pro_bowler_id ?? null;
        $result->pro_bowler_license_no = $row->pro_bowler_license_no ?? null;
        $result->amateur_name = $row->amateur_name ?? null;
        $result->display_name = $row->display_name ?? null;
        $result->player = null;
        $result->bowler = null;

        return $result;
    }

    private function isSeasonTrialTournament(Tournament $tournament): bool
    {
        if ((string) ($tournament->title_category ?? '') === 'season_trial') {
            return true;
        }

        return str_contains((string) ($tournament->name ?? ''), 'シーズントライアル');
    }

    private function resultIndexRankValue(object $row): int
    {
        foreach (['ranking', 'rank', 'position', 'placing', 'result_rank', 'order_no'] as $key) {
            if (isset($row->{$key}) && is_numeric($row->{$key})) {
                return (int) $row->{$key};
            }
        }

        return 0;
    }

    private function resultIndexIdentityKey(object $row): string
    {
        $proBowlerId = $row->pro_bowler_id
            ?? optional($row->player ?? null)->id
            ?? optional($row->bowler ?? null)->id
            ?? null;

        if ($proBowlerId !== null && (int) $proBowlerId > 0) {
            return 'pro:'.(int) $proBowlerId;
        }

        $license = $row->pro_bowler_license_no
            ?? optional($row->player ?? null)->license_no
            ?? optional($row->bowler ?? null)->license_no
            ?? null;

        $licenseDigits = preg_replace('/\D+/', '', (string) $license);
        if (is_string($licenseDigits) && $licenseDigits !== '') {
            return 'lic:'.$licenseDigits;
        }

        $name = (string) ($row->display_name ?? $row->amateur_name ?? '');
        $name = preg_replace('/\s+/u', '', trim($name)) ?? '';

        return $name !== '' ? 'name:'.$name : '';
    }

    private function numericOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    public function create()
    {
        $tournaments = Tournament::all();
        $players = ProBowler::all();

        return view('tournament_results.create', compact('tournaments', 'players'));
    }

    private function resolvePoints(int $tournamentId, int $rank): int
    {
        return (int) (PointDistribution::where('tournament_id', $tournamentId)
            ->where('rank', $rank)
            ->value('points') ?? 0);
    }

    private function resolvePrize(int $tournamentId, int $rank): int
    {
        return (int) (PrizeDistribution::where('tournament_id', $tournamentId)
            ->where('rank', $rank)
            ->value('amount') ?? 0);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'player_mode' => ['required', 'in:pro,ama'],
            'pro_key' => ['required_if:player_mode,pro', 'nullable', 'string', 'max:255'],
            'amateur_name' => ['required_if:player_mode,ama', 'nullable', 'string', 'max:255'],
            'ranking' => ['required', 'integer', 'min:1', 'max:10000'],
            'total_pin' => ['required', 'integer', 'min:0', 'max:200000'],
            'games' => ['required', 'integer', 'min:1', 'max:200'],
            'ranking_year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ], [], [
            'player_mode' => '選手区分',
            'pro_key' => 'プロ選手（ライセンス/氏名）',
            'amateur_name' => 'アマチュア選手名',
        ]);

        $this->assertResultIsEditable((int) $v['tournament_id']);

        $pro = null;
        $license = null;

        if ($v['player_mode'] === 'pro') {
            $pro = $this->resolvePro($v['pro_key'] ?? '');
            if (! $pro) {
                return back()
                    ->withErrors(['pro_key' => '該当するプロが見つかりません。ライセンスNo（例: M0123 / F0456 / m000123 等）または氏名を正確に入力してください。'])
                    ->withInput();
            }
            $license = $pro->license_no ?? null;
        }

        $games = max(1, (int) $v['games']);
        $average = round(((int) $v['total_pin']) / $games, 2);
        $points = $pro ? $this->resolvePoints((int) $v['tournament_id'], (int) $v['ranking']) : 0;
        $prize = $pro ? $this->resolvePrize((int) $v['tournament_id'], (int) $v['ranking']) : 0;

        $data = [
            'tournament_id' => (int) $v['tournament_id'],
            'ranking_year' => (int) $v['ranking_year'],
            'ranking' => (int) $v['ranking'],
            'total_pin' => (int) $v['total_pin'],
            'games' => (int) $v['games'],
            'average' => $average,
            'points' => $points,
            'prize_money' => $prize,
            'amateur_name' => $pro ? null : ($v['amateur_name'] ?? null),
        ];

        if ($pro) {
            $data['pro_bowler_license_no'] = $license;
            if (Schema::hasColumn('tournament_results', 'pro_bowler_id')) {
                $data['pro_bowler_id'] = $pro->id;
            }
        }

        TournamentResult::create($data);

        return redirect()
            ->route('tournaments.results.index', (int) $v['tournament_id'])
            ->with('success', '大会成績を登録しました。');
    }

    /**
     * ライセンス or 氏名からプロを一意特定（ゼロ詰めゆらぎ対応）
     */
    private function resolvePro(?string $key): ?ProBowler
    {
        if (! $key) {
            return null;
        }
        $k = trim($key);

        // 1) まずは完全一致（大文字で）
        $exactByLicense = ProBowler::whereRaw('upper(license_no) = ?', [strtoupper($k)])->first();
        if ($exactByLicense) {
            return $exactByLicense;
        }

        // 2) "M/F + 0* + 数字" を標準化して探す（M1278 と M01278 の相互ヒット）
        if (preg_match('/^([MF])\s*0*?(\d+)$/i', $k, $m)) {
            $letter = strtoupper($m[1]);
            $digits = ltrim($m[2], '0');   // 標準化（頭の0除去）
            if ($digits === '') {
                $digits = '0';
            }

            // a) 正規化版（M1278）
            $hit = ProBowler::whereRaw('upper(license_no) = ?', [$letter.$digits])->first();
            if ($hit) {
                return $hit;
            }

            // b) よくあるゼロ詰め（3～6桁まで面倒見ます）
            foreach ([3, 4, 5, 6] as $len) {
                $candidate = $letter.str_pad($digits, $len, '0', STR_PAD_LEFT);
                $hit = ProBowler::whereRaw('upper(license_no) = ?', [$candidate])->first();
                if ($hit) {
                    return $hit;
                }
            }

            // c) 最後の手段：同じ先頭文字の候補を取り出し、ゼロ無視で照合
            $candidates = ProBowler::whereRaw('upper(left(license_no,1)) = ?', [$letter])->get();
            foreach ($candidates as $p) {
                $pDigits = preg_replace('/^[MF]0*/i', '', $p->license_no);
                if ((string) $pDigits === (string) $digits) {
                    return $p;
                }
            }
        }

        // 3) 氏名（漢字・フリガナ）
        $byExact = ProBowler::where('name_kanji', $k)->orWhere('name_kana', $k)->get();
        if ($byExact->count() === 1) {
            return $byExact->first();
        }
        if ($byExact->count() > 1) {
            return null;
        }

        $byLike = ProBowler::where('name_kanji', 'like', $k.'%')
            ->orWhere('name_kana', 'like', $k.'%')
            ->limit(2)->get();

        return $byLike->count() === 1 ? $byLike->first() : null;
    }

    public function edit($id)
    {
        $result = TournamentResult::findOrFail($id);
        $this->assertResultIsEditable((int) $result->tournament_id);
        $players = ProBowler::all();
        $tournaments = Tournament::all();

        return view('tournament_results.edit', compact('result', 'players', 'tournaments'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'pro_bowler_license_no' => 'required',
            'tournament_id' => 'required',
            'ranking' => 'required|integer',
            'total_pin' => 'required|integer',
            'games' => 'required|integer|min:1',
            'ranking_year' => 'required|integer',
        ]);

        $result = TournamentResult::findOrFail($id);
        $this->assertResultIsEditable((int) $result->tournament_id);

        if ((int) $request->tournament_id !== (int) $result->tournament_id) {
            $this->assertResultIsEditable((int) $request->tournament_id);
        }

        $average = round($request->total_pin / max(1, $request->games), 2);
        $point = $this->resolvePoints((int) $request->tournament_id, (int) $request->ranking);
        $prize = $this->resolvePrize((int) $request->tournament_id, (int) $request->ranking);

        $result->update([
            'pro_bowler_license_no' => $request->pro_bowler_license_no,
            'tournament_id' => $request->tournament_id,
            'ranking' => $request->ranking,
            'points' => $point,
            'total_pin' => $request->total_pin,
            'games' => $request->games,
            'average' => $average,
            'prize_money' => $prize,
            'ranking_year' => $request->ranking_year,
        ]);

        return redirect()
            ->route('tournaments.results.index', (int) $result->tournament_id)
            ->with('success', '成績を更新しました。');
    }

    public function createForTournament(Tournament $tournament)
    {
        $players = ProBowler::all();

        return view('tournament_results.create_for_tournament', compact('tournament', 'players'));
    }

    public function storeForTournament(Request $request, Tournament $tournament)
    {
        $this->assertResultIsEditable((int) $tournament->id);

        $validated = $request->validate([
            'results' => 'required|array',
            'results.*.pro_bowler_license_no' => 'required',
            'results.*.ranking' => 'required|integer',
            'results.*.total_pin' => 'required|integer',
            'results.*.games' => 'required|integer|min:1',
            'results.*.ranking_year' => 'required|integer',
        ]);

        foreach ($validated['results'] as $data) {
            $average = round($data['total_pin'] / $data['games'], 2);
            $point = $this->resolvePoints((int) $tournament->id, (int) $data['ranking']);
            $prize = $this->resolvePrize((int) $tournament->id, (int) $data['ranking']);

            TournamentResult::create([
                'pro_bowler_license_no' => $data['pro_bowler_license_no'],
                'tournament_id' => $tournament->id,
                'ranking' => $data['ranking'],
                'points' => $point,
                'total_pin' => $data['total_pin'],
                'games' => $data['games'],
                'average' => $average,
                'prize_money' => $prize,
                'ranking_year' => $data['ranking_year'],
            ]);
        }

        return redirect()->route('tournaments.results.index', $tournament)->with('success', '成績を登録しました。');
    }

    public function batchCreate(Request $request)
    {
        $tournamentId = $request->query('tournament_id');
        $tournaments = Tournament::all();
        $players = ProBowler::all();

        return view('tournament_results.batch_create', compact('tournaments', 'players', 'tournamentId'));
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'ranking_year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        $tid = (int) $request->input('tournament_id');
        $year = (int) $request->input('ranking_year');

        $this->assertResultIsEditable($tid);

        // 旧: results[ { pro_bowler_license_no, ranking, total_pin, games } ... ]
        $legacyRows = $request->input('results', []);

        // 新: rows[ { player_mode, pro_key, amateur_name, ranking, total_pin, games } ... ]
        $newRows = $request->input('rows', []);

        // どちらか一方（両方来てもOK）
        $rows = [];

        // 旧 → 正規化
        foreach ($legacyRows as $r) {
            if (empty($r['pro_bowler_license_no']) && empty($r['ranking']) && empty($r['total_pin']) && empty($r['games'])) {
                continue;
            }
            $rows[] = [
                'player_mode' => 'pro',
                'pro_key' => $r['pro_bowler_license_no'] ?? null,
                'amateur_name' => null,
                'ranking' => (int) ($r['ranking'] ?? 0),
                'total_pin' => (int) ($r['total_pin'] ?? 0),
                'games' => (int) ($r['games'] ?? 0),
            ];
        }

        // 新 → そのまま
        foreach ($newRows as $r) {
            if (empty($r['pro_key']) && empty($r['amateur_name']) &&
                empty($r['ranking']) && empty($r['total_pin']) && empty($r['games'])) {
                continue;
            }
            $rows[] = [
                'player_mode' => $r['player_mode'] ?? 'pro',
                'pro_key' => $r['pro_key'] ?? null,
                'amateur_name' => $r['amateur_name'] ?? null,
                'ranking' => (int) ($r['ranking'] ?? 0),
                'total_pin' => (int) ($r['total_pin'] ?? 0),
                'games' => (int) ($r['games'] ?? 0),
            ];
        }

        foreach ($rows as $entry) {
            $isPro = ($entry['player_mode'] ?? 'pro') === 'pro';
            $pro = null;
            $license = null;

            if ($isPro) {
                $pro = $this->resolvePro($entry['pro_key'] ?? '');
                if (! $pro) {
                    return back()->withErrors(['rows' => '不明なプロ選手があります（'.$entry['pro_key'].'）。'])->withInput();
                }
                $license = $pro->license_no ?? null;
            }

            $games = max(1, (int) $entry['games']);
            $average = round(((int) $entry['total_pin']) / $games, 2);
            $points = $isPro ? $this->resolvePoints($tid, (int) $entry['ranking']) : 0;
            $prize = $isPro ? $this->resolvePrize($tid, (int) $entry['ranking']) : 0;

            $data = [
                'tournament_id' => $tid,
                'ranking_year' => $year,
                'ranking' => (int) $entry['ranking'],
                'total_pin' => (int) $entry['total_pin'],
                'games' => (int) $entry['games'],
                'average' => $average,
                'points' => $points,
                'prize_money' => $prize,
                'amateur_name' => $isPro ? null : ($entry['amateur_name'] ?? null),
            ];

            if ($isPro) {
                // 既存互換：ライセンスNo主体
                $data['pro_bowler_license_no'] = $license;
                // あればIDも
                if (Schema::hasColumn('tournament_results', 'pro_bowler_id')) {
                    $data['pro_bowler_id'] = $pro->id;
                }
            }

            TournamentResult::create($data);
        }

        return redirect()->route('tournaments.results.index', $tid)
            ->with('success', '一括登録を完了しました。');
    }

    public function rankings(Request $request)
    {
        $year = (int) $request->query('year', date('Y'));
        $gender = $this->normalizeRankingGender($request->query('gender'));
        $genderLabel = $this->rankingGenderLabel($gender);

        $years = TournamentResult::select('ranking_year')
            ->whereNotNull('ranking_year')
            ->distinct()
            ->orderByDesc('ranking_year')
            ->pluck('ranking_year');

        $moneyRanks = $this->applyRankingGenderFilter(
            TournamentResult::query()
                ->where('ranking_year', $year)
                ->whereHas('tournament', fn ($query) => $query->where('counts_for_prize', true)),
            $gender
        )
            ->whereNotNull('pro_bowler_license_no')
            ->where('pro_bowler_license_no', '<>', '')
            ->select('pro_bowler_license_no', DB::raw('SUM(COALESCE(prize_money, 0)) as total_prize_money'))
            ->groupBy('pro_bowler_license_no')
            ->orderByDesc('total_prize_money')
            ->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no', $item->pro_bowler_license_no)->first())->name_kanji;

                return $item;
            });

        $pointRanks = $this->applyRankingGenderFilter(
            TournamentResult::query()
                ->where('ranking_year', $year)
                ->whereHas('tournament', fn ($query) => $query->includedInAnnualPointRanking()),
            $gender
        )
            ->whereNotNull('pro_bowler_license_no')
            ->where('pro_bowler_license_no', '<>', '')
            ->select('pro_bowler_license_no', DB::raw('SUM(COALESCE(points, 0)) as total_points'))
            ->groupBy('pro_bowler_license_no')
            ->orderByDesc('total_points')
            ->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no', $item->pro_bowler_license_no)->first())->name_kanji;

                return $item;
            });

        $averageRanks = $this->applyRankingGenderFilter(
            TournamentResult::query()
                ->where('ranking_year', $year)
                ->whereHas('tournament', fn ($query) => $query->where('counts_for_average', true)),
            $gender
            )
            ->whereNotNull('pro_bowler_license_no')
            ->where('pro_bowler_license_no', '<>', '')
            ->select(
                'pro_bowler_license_no',
                DB::raw('SUM(COALESCE(total_pin, 0)) as total_pin'),
                DB::raw('SUM(COALESCE(games, 0)) as total_games'),
            )
            ->groupBy('pro_bowler_license_no')
            ->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no', $item->pro_bowler_license_no)->first())->name_kanji;
                $item->avg_average = $this->officialAverage(
                    (int) $item->total_pin,
                    (int) $item->total_games,
                );

                return $item;
            })
            ->sortByDesc('avg_average')
            ->values();

        return view('tournament_results.rankings', compact('year', 'years', 'gender', 'genderLabel', 'moneyRanks', 'pointRanks', 'averageRanks'));
    }

    private function officialAverage(int $totalPin, int $games): float
    {
        if ($games <= 0) {
            return 0.0;
        }

        return floor((($totalPin / $games) * 100) + 0.0000001) / 100;
    }

    private function normalizeRankingGender(?string $gender): ?string
    {
        $gender = strtoupper(trim((string) $gender));

        return in_array($gender, ['M', 'F'], true) ? $gender : null;
    }

    private function rankingGenderLabel(?string $gender): string
    {
        return match ($gender) {
            'M' => '男子',
            'F' => '女子',
            default => '全体',
        };
    }

    private function applyRankingGenderFilter($query, ?string $gender)
    {
        if (in_array($gender, ['M', 'F'], true)) {
            $query->whereRaw("upper(coalesce(pro_bowler_license_no, '')) like ?", [$gender.'%']);
        }

        return $query;
    }

    public function exportTournamentPdf(Tournament $tournament)
    {
        $rankCol = collect(['ranking', 'rank', 'position', 'placing', 'result_rank', 'order_no'])
            ->first(fn ($c) => Schema::hasColumn('tournament_results', $c));

        $query = TournamentResult::with(['tournament', 'player', 'bowler'])
            ->where('tournament_id', $tournament->id);

        $rankCol ? $query->orderBy($rankCol) : $query->orderBy('id');

        $results = $query->get();
        $this->attachBowlerPeriodLabels($results);
        $this->attachResultPdfDisplayFields($results);
        $prizeDistributionMap = PrizeDistribution::query()
            ->where('tournament_id', $tournament->id)
            ->orderBy('rank')
            ->pluck('amount', 'rank')
            ->map(fn ($amount): int => max(0, (int) $amount))
            ->all();

        $scoreSnapshots = $this->loadPdfScoreSnapshots($tournament);
        $roundRobinPdf = $this->buildRoundRobinPdfData($tournament);

        $singleEliminationPdf = $this->buildSingleEliminationPdfData($tournament);
        $singleEliminationBracketImage = null;

        if (is_array($singleEliminationPdf) && ! empty($singleEliminationPdf['bracket']['rounds'] ?? [])) {
            try {
                /** @var SingleEliminationBracketImageService $singleEliminationBracketImageService */
                $singleEliminationBracketImageService = app(SingleEliminationBracketImageService::class);
                $singleEliminationBracketImage = $singleEliminationBracketImageService->generateDataUri($tournament, $singleEliminationPdf);
            } catch (\Throwable $e) {
                report($e);
                $singleEliminationBracketImage = null;
            }
        }

        $shootoutPdf = $this->buildShootoutPdfData($tournament);
        $shootoutBracketImage = null;

        if (is_array($shootoutPdf) && ! empty($shootoutPdf['seed_rows'])) {
            try {
                /** @var ShootoutBracketImageService $bracketImageService */
                $bracketImageService = app(ShootoutBracketImageService::class);
                $shootoutBracketImage = $bracketImageService->generateDataUri($tournament, $shootoutPdf);
            } catch (\Throwable $e) {
                report($e);
                $shootoutBracketImage = null;
            }
        }

        $stepLadderPdf = $this->buildStepLadderPdfData($tournament);
        $stepLadderBracketImage = null;

        if (is_array($stepLadderPdf)
            && empty($stepLadderPdf['missing_seed_snapshot'])
            && ! empty($stepLadderPdf['seeds'] ?? [])
        ) {
            try {
                /** @var StepLadderBracketImageService $stepLadderBracketImageService */
                $stepLadderBracketImageService = app(StepLadderBracketImageService::class);
                $stepLadderBracketImage = $stepLadderBracketImageService->generateDataUri($tournament, $stepLadderPdf);
            } catch (\Throwable $e) {
                report($e);
                $stepLadderBracketImage = null;
            }
        }

        $matchScoreSheets = $this->loadPublishedMatchScoreSheets($tournament);
        $matchScoreSheetImages = [];
        $selectionScoreSections = $this->buildSelectionPdfScoreSections($tournament);
        $singleEliminationMatchSummary = $this->buildSingleEliminationMatchSummary($tournament);

        if ($matchScoreSheets->isNotEmpty()) {
            try {
                /** @var MatchScoreSheetImageService $scoreSheetImageService */
                $scoreSheetImageService = app(MatchScoreSheetImageService::class);
                $matchScoreSheetImages = $scoreSheetImageService->generateDataUris($matchScoreSheets);
            } catch (\Throwable $e) {
                report($e);
                $matchScoreSheetImages = [];
            }
        }
        $officialTitleClass = $this->resolvePdfTitleClass((string) $tournament->name);
        $isSeasonTrialPdf = str_contains((string) $tournament->name, 'シーズントライアル')
            || (string) ($tournament->title_category ?? '') === 'season_trial';
        $pdfData = compact(
            'tournament',
            'results',
            'scoreSnapshots',
            'roundRobinPdf',
            'singleEliminationPdf',
            'singleEliminationBracketImage',
            'shootoutPdf',
            'shootoutBracketImage',
            'stepLadderPdf',
            'stepLadderBracketImage',
            'matchScoreSheets',
            'matchScoreSheetImages',
            'selectionScoreSections',
            'singleEliminationMatchSummary',
            'officialTitleClass',
            'prizeDistributionMap'
        );
        $downloadName = "{$tournament->year}_{$tournament->name}_results.pdf";

        if (! $isSeasonTrialPdf && ! empty($roundRobinPdf['players'] ?? [])) {
            return $this->makeOfficialStandardTournamentPdf($pdfData, $downloadName);
        }

        return $this->makePdfWithJapaneseFont(
            'tournament_results.pdf',
            $pdfData,
            $downloadName,
            $isSeasonTrialPdf ? 'portrait' : 'landscape'
        );
    }

    private function resolvePdfTitleClass(string $tournamentName): string
    {
        $displayName = str_replace('𠮷', '吉', trim($tournamentName));
        $width = function_exists('mb_strwidth')
            ? mb_strwidth($displayName, 'UTF-8')
            : strlen($displayName);

        if ($width >= 68) {
            return 'official-title-extra-long';
        }

        return $width >= 58 ? 'official-title-long' : '';
    }

    public function exportPdf(Request $request)
    {
        if ($request->filled('tournament_id')) {
            $tournament = Tournament::findOrFail((int) $request->input('tournament_id'));

            return $this->exportTournamentPdf($tournament);
        }

        $results = TournamentResult::with(['tournament', 'player', 'bowler'])
            ->orderBy('ranking_year', 'desc')
            ->get();

        $this->attachBowlerPeriodLabels($results);
        $this->attachResultPdfDisplayFields($results);

        return $this->makePdfWithJapaneseFont(
            'tournament_results.pdf',
            compact('results'),
            'tournament_results.pdf'
        );
    }

    public function exportSnapshotPdf(Tournament $tournament, TournamentResultSnapshot $snapshot)
    {
        if ((int) $snapshot->tournament_id !== (int) $tournament->id) {
            abort(404);
        }

        $pdfData = $this->buildSnapshotScorePdfData($tournament, $snapshot);
        $safeTournamentName = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', (string) $tournament->name);
        $safeTournamentName = $safeTournamentName !== '' ? $safeTournamentName : 'tournament';
        $safeResultName = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '_', (string) $snapshot->result_name);
        $safeResultName = $safeResultName !== '' ? $safeResultName : 'result';
        $downloadName = sprintf(
            '%s_%s_%s.pdf',
            $tournament->year ?: now()->format('Y'),
            $safeTournamentName,
            $safeResultName
        );

        return $this->makePdfWithJapaneseFont(
            'tournament_results.pdfs.snapshot_score',
            $pdfData,
            $downloadName,
            $pdfData['orientation'] ?? 'portrait'
        );
    }

    public function destroy(Tournament $tournament, TournamentResult $result)
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'この操作は許可されていません。');
        }

        $this->assertResultIsEditable((int) $tournament->id);

        $result->delete();

        return redirect()
            ->route('tournaments.results.index', $tournament) // モデルで渡すとキレイ
            ->with('success', '成績を削除しました。');
    }

    private function currentResultPublication(int $tournamentId): ?TournamentResultPublication
    {
        if (! Schema::hasTable('tournament_result_publications')) {
            return null;
        }

        return TournamentResultPublication::query()
            ->where('tournament_id', $tournamentId)
            ->where('status', TournamentResultPublication::STATUS_CURRENT)
            ->latest('revision')
            ->first();
    }

    private function assertResultIsEditable(int $tournamentId): void
    {
        if ($this->currentResultPublication($tournamentId) !== null) {
            abort(409, '公式結果は確定済みです。元の成績表を訂正し、公式結果の確定・公開画面から改訂版を公開してください。');
        }
    }

    private function buildStepLadderPdfData(Tournament $tournament): ?array
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? ''));
        if (! in_array($flowType, ['prelim_to_rr_to_final', 'prelim_to_quarterfinal_to_rr_to_final'], true)) {
            return null;
        }

        try {
            /** @var StepLadderService $stepLadderService */
            $stepLadderService = app(StepLadderService::class);

            return $stepLadderService->build([
                'tournament_id' => (int) $tournament->id,
                'upto_game' => 2,
                'shift' => '',
                'gender' => in_array($tournament->gender, ['M', 'F'], true) ? $tournament->gender : '',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function buildRoundRobinPdfData(Tournament $tournament): ?array
    {
        $hasRoundRobinSnapshot = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->where('result_code', 'round_robin_total')
            ->where(function ($query): void {
                $query->where('is_current', true)
                    ->orWhere('is_published', true);
            })
            ->exists();

        if (! $hasRoundRobinSnapshot) {
            return null;
        }

        $maxGame = (int) DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'ラウンドロビン')
            ->max('game_number');

        if ($maxGame <= 0) {
            return null;
        }

        try {
            /** @var RoundRobinService $roundRobinService */
            $roundRobinService = app(RoundRobinService::class);
            $payload = $roundRobinService->build([
                'tournament_id' => (int) $tournament->id,
                'upto_game' => $maxGame,
                'shift' => '',
                'gender' => in_array($tournament->gender, ['M', 'F'], true) ? $tournament->gender : '',
            ]);

            return ! empty($payload['players'] ?? []) ? $payload : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function loadPublishedMatchScoreSheets(Tournament $tournament)
    {
        return TournamentMatchScoreSheet::query()
            ->with([
                'players' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('id');
                },
                'players.frames' => function ($query) {
                    $query->orderBy('frame_no');
                },
            ])
            ->where('tournament_id', $tournament->id)
            ->where('is_published', true)
            ->orderBy('match_order')
            ->orderBy('game_number')
            ->orderBy('id')
            ->get();
    }

    private function buildSingleEliminationPdfData(Tournament $tournament): ?array
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? ''));
        if (! str_contains($flowType, 'single_elimination')) {
            return null;
        }

        $qualifierCount = (int) ($tournament->single_elimination_qualifier_count ?? 0);
        if ($qualifierCount < 2) {
            return null;
        }

        $seedSourceResultCode = trim((string) ($tournament->single_elimination_seed_source_result_code ?? ''))
            ?: $this->defaultSingleEliminationSeedSourceResultCode($flowType);

        $seedSnapshot = $this->findCurrentSnapshotByCode((int) $tournament->id, $seedSourceResultCode);
        if (! $seedSnapshot) {
            return null;
        }

        $seedEntries = $this->buildSingleEliminationSeedEntriesFromSnapshot((int) $seedSnapshot->id, $qualifierCount);
        if (count($seedEntries) < $qualifierCount) {
            return null;
        }

        $seedSettings = $this->normalizeTournamentArraySetting($tournament->single_elimination_seed_settings ?? []);
        $seedPolicy = trim((string) ($tournament->single_elimination_seed_policy ?? '')) ?: 'standard';
        $laneSettings = [];
        if (isset($seedSettings['lane_settings']) && is_array($seedSettings['lane_settings'])) {
            $laneSettings = $seedSettings['lane_settings'];
        } elseif (isset($seedSettings['single_elimination_lane_settings']) && is_array($seedSettings['single_elimination_lane_settings'])) {
            $laneSettings = $seedSettings['single_elimination_lane_settings'];
        }

        /** @var SingleEliminationService $singleEliminationService */
        $singleEliminationService = app(SingleEliminationService::class);

        $bracket = $singleEliminationService->buildBracket(
            qualifierCount: $qualifierCount,
            seedPolicy: $seedPolicy,
            seedSettings: $seedSettings,
            seedEntries: $seedEntries
        );

        $bracket = $singleEliminationService->applyMatchScores(
            bracket: $bracket,
            matchScores: $this->loadSingleEliminationMatchScores((int) $tournament->id)
        );

        $winnerName = $this->resolveSingleEliminationWinnerName($bracket);
        $finalStandings = [];
        try {
            $finalStandings = $singleEliminationService->buildFinalStandingRows($bracket);
        } catch (\Throwable) {
            $finalStandings = [];
        }

        $summary = (array) ($bracket['summary'] ?? []);
        $summary['completed_match_count'] = $this->countCompletedSingleEliminationMatches($bracket);
        $summary['winner_name'] = $winnerName;

        return [
            'seed_source_result_code' => $seedSourceResultCode,
            'seed_snapshot_id' => (int) $seedSnapshot->id,
            'seed_rows' => $seedEntries,
            'bracket' => $bracket,
            'summary' => $summary,
            'final_standings' => $finalStandings,
            'lane_settings' => $laneSettings,
            'meta' => [
                'seed_source_name' => $this->singleEliminationSeedSourceName($seedSourceResultCode),
                'seed_policy' => $seedPolicy,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildSingleEliminationSeedEntriesFromSnapshot(int $snapshotId, int $qualifierCount): array
    {
        $rows = DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->limit($qualifierCount)
            ->get();

        $entries = [];
        foreach ($rows as $index => $row) {
            $seed = $index + 1;
            $displayName = trim((string) ($row->display_name ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row->amateur_name ?? ''));
            }
            if ($displayName === '') {
                $displayName = trim((string) ($row->pro_bowler_license_no ?? ('seed'.$seed)));
            }

            $entries[] = [
                'seed' => $seed,
                'display_name' => $displayName,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
                'pro_bowler_license_no' => $row->pro_bowler_license_no ?? null,
                'amateur_name' => $row->amateur_name ?? null,
                'source_row_id' => $row->id ?? null,
                'participant_key' => $this->singleEliminationParticipantKeyFromSnapshotRow($row, $seed),
                'source_ranking' => $row->ranking ?? $seed,
                'total_pin' => $row->total_pin ?? null,
                'games' => $row->games ?? null,
                'average' => $row->average ?? null,
                'term_label' => $this->resolveBowlerPeriodLabelFromSnapshotRow($row),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function loadSingleEliminationMatchScores(int $tournamentId): array
    {
        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'トーナメント')
            ->where('entry_number', 'like', 'SE:%')
            ->orderBy('game_number')
            ->orderBy('entry_number')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $entryNumber = trim((string) ($row->entry_number ?? ''));
            if (! preg_match('/^SE:(R\d+-M\d+):([AB])$/i', $entryNumber, $m)) {
                continue;
            }

            $scores[strtoupper($m[1])][$m[2]] = [
                'score' => $row->score !== null ? (int) $row->score : null,
                'row_id' => (int) ($row->id ?? 0),
                'license_number' => $row->license_number ?? null,
                'name' => $row->name ?? null,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
            ];
        }

        return $scores;
    }

    private function resolveSingleEliminationWinnerName(array $bracket): string
    {
        $rounds = array_values((array) ($bracket['rounds'] ?? []));
        if (empty($rounds)) {
            return '';
        }

        $finalRound = (array) $rounds[count($rounds) - 1];
        $matches = array_values((array) ($finalRound['matches'] ?? []));
        if (empty($matches)) {
            return '';
        }

        $finalMatch = (array) $matches[count($matches) - 1];
        if (empty($finalMatch['is_complete'])) {
            return '';
        }

        $winnerNode = (array) ($finalMatch['winner_node'] ?? []);

        return trim((string) ($winnerNode['display_name'] ?? $winnerNode['label'] ?? ''));
    }

    private function countCompletedSingleEliminationMatches(array $bracket): int
    {
        $count = 0;
        foreach ((array) ($bracket['rounds'] ?? []) as $round) {
            foreach ((array) ($round['matches'] ?? []) as $match) {
                $match = (array) $match;
                if (! empty($match['is_bye'])) {
                    continue;
                }
                if (! empty($match['is_complete']) && ! empty($match['winner_node'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function singleEliminationParticipantKeyFromSnapshotRow(object $row, int $seed): string
    {
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:'.$proBowlerId;
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '') {
            return 'license:'.strtoupper($license);
        }

        $displayName = preg_replace('/\s+/u', '', trim((string) ($row->display_name ?? $row->amateur_name ?? '')));
        if (is_string($displayName) && $displayName !== '') {
            return 'name:'.$displayName;
        }

        return 'seed:'.$seed;
    }

    private function defaultSingleEliminationSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_single_elimination_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_single_elimination_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    private function singleEliminationSeedSourceName(string $resultCode): string
    {
        return match ($resultCode) {
            'quarterfinal_total' => '準々決勝通算成績',
            'semifinal_total' => '準決勝通算成績',
            'prelim_total' => '予選通算成績',
            default => $resultCode,
        };
    }

    private function normalizeTournamentArraySetting($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildShootoutPdfData(Tournament $tournament): ?array
    {
        $flowType = trim((string) ($tournament->result_flow_type ?? ''));
        if (! in_array($flowType, [
            'prelim_to_shootout_to_final',
            'prelim_to_quarterfinal_to_shootout_to_final',
            'prelim_to_semifinal_to_shootout_to_final',
        ], true)) {
            return null;
        }

        $seedSourceResultCode = trim((string) ($tournament->shootout_seed_source_result_code ?? ''))
            ?: $this->defaultShootoutSeedSourceResultCode($flowType);

        $seedSnapshot = $this->findCurrentSnapshotByCode((int) $tournament->id, $seedSourceResultCode);
        if (! $seedSnapshot) {
            return null;
        }

        $seedEntries = $this->buildShootoutSeedEntriesFromSnapshot((int) $seedSnapshot->id, 8);
        if (count($seedEntries) < 8) {
            return null;
        }

        /** @var ShootoutService $shootoutService */
        $shootoutService = app(ShootoutService::class);
        $shootout = $shootoutService->buildStandard8(
            seedEntries: $seedEntries,
            matchScores: $this->loadShootoutMatchScores((int) $tournament->id)
        );

        $finalRankBySeed = [];
        try {
            foreach ($shootoutService->buildFinalStandings($shootout) as $standing) {
                $node = (array) ($standing['node'] ?? []);
                $seed = (int) ($node['seed'] ?? $node['min_seed'] ?? 0);
                if ($seed >= 1 && $seed <= 8) {
                    $finalRankBySeed[$seed] = (int) ($standing['ranking'] ?? 0);
                }
            }
        } catch (\Throwable) {
            $finalRankBySeed = [];
        }

        return [
            'seed_source_result_code' => $seedSourceResultCode,
            'seed_snapshot_id' => (int) $seedSnapshot->id,
            'seed_rows' => array_values((array) ($shootout['seed_rows'] ?? [])),
            'matches' => array_values((array) ($shootout['matches'] ?? [])),
            'summary' => (array) ($shootout['summary'] ?? []),
            'final_rank_by_seed' => $finalRankBySeed,
        ];
    }

    private function findCurrentSnapshotByCode(int $tournamentId, string $resultCode): ?object
    {
        $gender = DB::table('tournaments')
            ->where('id', $tournamentId)
            ->value('gender');
        $gender = is_string($gender) ? strtoupper(trim($gender)) : '';

        $hasCurrentPublication = Schema::hasTable('tournament_result_publications')
            && DB::table('tournament_result_publications')
                ->where('tournament_id', $tournamentId)
                ->where('status', 'current')
                ->exists();

        $query = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournamentId)
            ->where('result_code', $resultCode)
            ->where($hasCurrentPublication ? 'is_published' : 'is_current', true)
            ->whereNull('shift');

        if ($gender !== '') {
            $query
                ->where(function ($query) use ($gender) {
                    $query
                        ->whereNull('gender')
                        ->orWhereRaw('upper(gender) = ?', [$gender]);
                })
                ->orderByRaw('case when upper(coalesce(gender, \'\')) = ? then 0 when gender is null then 1 else 2 end', [$gender]);
        } else {
            $query->whereNull('gender');
        }

        return $query
            ->orderByDesc('reflected_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildShootoutSeedEntriesFromSnapshot(int $snapshotId, int $qualifierCount): array
    {
        $rows = DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshotId)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->limit($qualifierCount)
            ->get();

        $entries = [];
        foreach ($rows as $index => $row) {
            $seed = $index + 1;
            $displayName = trim((string) ($row->display_name ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row->amateur_name ?? ''));
            }
            if ($displayName === '') {
                $displayName = trim((string) ($row->pro_bowler_license_no ?? ('seed'.$seed)));
            }

            $entries[] = [
                'seed' => $seed,
                'display_name' => $displayName,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
                'pro_bowler_license_no' => $row->pro_bowler_license_no ?? null,
                'amateur_name' => $row->amateur_name ?? null,
                'source_row_id' => $row->id ?? null,
                'participant_key' => $this->shootoutParticipantKeyFromSnapshotRow($row, $seed),
                'source_ranking' => $row->ranking ?? null,
                'total_pin' => $row->total_pin ?? null,
                'games' => $row->games ?? null,
                'average' => $row->average ?? null,
                'term_label' => $this->resolveBowlerPeriodLabelFromSnapshotRow($row),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function loadShootoutMatchScores(int $tournamentId): array
    {
        $rows = DB::table('game_scores')
            ->where('tournament_id', $tournamentId)
            ->where('stage', 'シュートアウト')
            ->where('entry_number', 'like', 'SO:%')
            ->orderBy('game_number')
            ->orderBy('entry_number')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $entryNumber = trim((string) ($row->entry_number ?? ''));
            if (! preg_match('/^SO:(SO[123]):([ABCD])$/', $entryNumber, $m)) {
                continue;
            }

            $scores[$m[1]][$m[2]] = [
                'score' => (int) ($row->score ?? 0),
                'row_id' => (int) ($row->id ?? 0),
                'license_number' => $row->license_number ?? null,
                'name' => $row->name ?? null,
                'pro_bowler_id' => $row->pro_bowler_id ?? null,
            ];
        }

        foreach ($this->loadShootoutMatchScoresFromScoreSheets($tournamentId) as $matchKey => $slotScores) {
            foreach ($slotScores as $slotCode => $scoreRow) {
                $scores[$matchKey][$slotCode] = $scoreRow;
            }
        }

        return $scores;
    }

    /**
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function loadShootoutMatchScoresFromScoreSheets(int $tournamentId): array
    {
        $scoreSheets = TournamentMatchScoreSheet::query()
            ->with(['players'])
            ->where('tournament_id', $tournamentId)
            ->where('sheet_type', 'shootout')
            ->where('is_published', true)
            ->orderBy('match_order')
            ->orderBy('id')
            ->get();

        $scores = [];

        foreach ($scoreSheets as $scoreSheet) {
            $matchKey = $this->normalizeShootoutMatchKeyFromScoreSheet($scoreSheet);
            if ($matchKey === null) {
                continue;
            }

            foreach ($scoreSheet->players->values() as $index => $player) {
                $slotCode = $this->normalizeShootoutSlotCode($player->player_slot ?? null, $index);
                if ($slotCode === null) {
                    continue;
                }

                if ($player->final_score === null || ! is_numeric($player->final_score)) {
                    continue;
                }

                $scores[$matchKey][$slotCode] = [
                    'score' => (int) $player->final_score,
                    'score_sheet_id' => (int) $scoreSheet->id,
                    'score_sheet_player_id' => (int) $player->id,
                    'license_number' => $player->pro_bowler_license_no ?? null,
                    'name' => $player->display_name ?? null,
                    'pro_bowler_id' => $player->pro_bowler_id ?? null,
                    'is_winner' => (bool) $player->is_winner,
                    'source' => 'score_sheet',
                ];
            }
        }

        return $scores;
    }

    private function normalizeShootoutMatchKeyFromScoreSheet(TournamentMatchScoreSheet $scoreSheet): ?string
    {
        $text = trim(implode(' ', array_filter([
            (string) ($scoreSheet->match_code ?? ''),
            (string) ($scoreSheet->match_label ?? ''),
            (string) ($scoreSheet->stage_code ?? ''),
        ], fn (string $value): bool => trim($value) !== '')));

        if ($text === '') {
            return null;
        }

        $normalized = function_exists('mb_convert_kana')
            ? mb_convert_kana($text, 'as', 'UTF-8')
            : $text;
        $upper = strtoupper($normalized);

        if (preg_match('/SO\s*:?\s*SO?\s*([123])/', $upper, $matches)
            || preg_match('/\bSO\s*([123])\b/', $upper, $matches)
        ) {
            return 'SO'.$matches[1];
        }

        if (str_contains($upper, 'FINAL') || str_contains($text, '優勝')) {
            return 'SO3';
        }

        if (str_contains($upper, '2ND') || str_contains($upper, 'SECOND') || str_contains($text, '２') || str_contains($text, '2')) {
            return 'SO2';
        }

        if (str_contains($upper, '1ST') || str_contains($upper, 'FIRST') || str_contains($text, '１') || str_contains($text, '1')) {
            return 'SO1';
        }

        return null;
    }

    private function normalizeShootoutSlotCode(mixed $slot, int $fallbackIndex): ?string
    {
        $slotCode = strtoupper(trim((string) ($slot ?? '')));
        $slotCode = function_exists('mb_convert_kana')
            ? strtoupper(mb_convert_kana($slotCode, 'as', 'UTF-8'))
            : $slotCode;

        if (preg_match('/^[ABCD]$/', $slotCode)) {
            return $slotCode;
        }

        $fallback = chr(65 + $fallbackIndex);

        return preg_match('/^[ABCD]$/', $fallback) ? $fallback : null;
    }

    private function shootoutParticipantKeyFromSnapshotRow(object $row, int $seed): string
    {
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            return 'pro_bowler:'.$proBowlerId;
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '') {
            return 'license:'.strtoupper($license);
        }

        $displayName = preg_replace('/\s+/u', '', trim((string) ($row->display_name ?? $row->amateur_name ?? '')));
        if (is_string($displayName) && $displayName !== '') {
            return 'name:'.$displayName;
        }

        return 'seed:'.$seed;
    }

    private function resolveBowlerPeriodLabelFromSnapshotRow(object $row): ?string
    {
        $periodColumn = $this->detectProBowlerPeriodColumn();
        if (! $periodColumn) {
            return null;
        }

        $bowler = null;
        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            $bowler = ProBowler::query()
                ->select(['id', 'license_no', $periodColumn])
                ->find($proBowlerId);
        }

        if (! $bowler) {
            $license = strtoupper(trim((string) ($row->pro_bowler_license_no ?? '')));
            if ($license !== '') {
                $bowler = ProBowler::query()
                    ->select(['id', 'license_no', $periodColumn])
                    ->whereRaw('upper(license_no) = ?', [$license])
                    ->first();
            }
        }

        return $bowler ? $this->formatBowlerPeriodLabel($bowler->{$periodColumn} ?? null) : null;
    }

    private function defaultShootoutSeedSourceResultCode(string $flowType): string
    {
        return match ($flowType) {
            'prelim_to_quarterfinal_to_shootout_to_final' => 'quarterfinal_total',
            'prelim_to_semifinal_to_shootout_to_final' => 'semifinal_total',
            default => 'prelim_total',
        };
    }

    /**
     * @return array<int,array{stage:string,rows:array<int,array<string,mixed>>}>
     */
    private function buildSelectionPdfScoreSections(Tournament $tournament): array
    {
        $hasPublishedSelection = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->where('result_code', 'selection_final')
            ->where('is_published', true)
            ->exists();

        if (! $hasPublishedSelection) {
            return [];
        }

        $scoreRows = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'like', '選抜%')
            ->orderBy('stage')
            ->orderBy('id')
            ->get();

        if ($scoreRows->isEmpty()) {
            return [];
        }

        $sections = [];
        foreach ($scoreRows->groupBy('stage') as $stage => $stageRows) {
            $participants = [];
            foreach ($stageRows as $scoreRow) {
                $identity = (int) ($scoreRow->pro_bowler_id ?? 0) > 0
                    ? 'pro:'.(int) $scoreRow->pro_bowler_id
                    : 'license:'.strtoupper(trim((string) ($scoreRow->license_number ?? '')));
                if ($identity === 'license:') {
                    $identity = 'name:'.trim((string) ($scoreRow->name ?? ''));
                }

                $participants[$identity] ??= [
                    'first_id' => (int) $scoreRow->id,
                    'license' => $this->licenseLastDigits((string) ($scoreRow->license_number ?? '')),
                    'name' => trim((string) ($scoreRow->name ?? '')) ?: '-',
                    'scores' => [],
                    'total_pin' => 0,
                ];

                $gameNumber = (int) ($scoreRow->game_number ?? 0);
                if ($gameNumber > 0) {
                    $participants[$identity]['scores'][$gameNumber] = (int) $scoreRow->score;
                }
            }

            foreach ($participants as &$participant) {
                ksort($participant['scores']);
                $participant['total_pin'] = array_sum($participant['scores']);
                $participant['average'] = count($participant['scores']) > 0
                    ? $participant['total_pin'] / count($participant['scores'])
                    : 0.0;
            }
            unset($participant);

            usort($participants, fn (array $left, array $right): int => $right['total_pin'] <=> $left['total_pin']
                ?: $left['first_id'] <=> $right['first_id']);

            foreach ($participants as $index => &$participant) {
                $participant['ranking'] = $index + 1;
            }
            unset($participant);

            $sections[] = [
                'stage' => (string) $stage,
                'rows' => array_values($participants),
            ];
        }

        return $sections;
    }

    /**
     * @return array<int,array{code:string,label:string,players:array<int,array<string,mixed>>}>
     */
    private function buildSingleEliminationMatchSummary(Tournament $tournament): array
    {
        $scoreRows = DB::table('game_scores')
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'トーナメント')
            ->where('entry_number', 'like', 'SE:%')
            ->orderBy('id')
            ->get();

        if ($scoreRows->isEmpty()) {
            return [];
        }

        $matches = [];
        foreach ($scoreRows as $scoreRow) {
            $entryNumber = trim((string) ($scoreRow->entry_number ?? ''));
            $matchCode = preg_replace('/:[AB]$/', '', $entryNumber) ?: $entryNumber;
            $playerCode = preg_match('/:(A|B)$/', $entryNumber, $match) === 1
                ? $match[1]
                : $entryNumber;

            $matches[$matchCode] ??= [
                'code' => $matchCode,
                'first_id' => (int) $scoreRow->id,
                'players' => [],
            ];
            $matches[$matchCode]['players'][$playerCode] ??= [
                'license' => $this->licenseLastDigits((string) ($scoreRow->license_number ?? '')),
                'name' => trim((string) ($scoreRow->name ?? '')) ?: '-',
                'scores' => [],
            ];
            $matches[$matchCode]['players'][$playerCode]['scores'][] = [
                'game_number' => (int) ($scoreRow->game_number ?? 0),
                'score' => (int) $scoreRow->score,
            ];
        }

        uasort($matches, fn (array $left, array $right): int => $left['first_id'] <=> $right['first_id']);

        $result = [];
        foreach ($matches as $match) {
            $players = [];
            $winningTotal = null;
            foreach ($match['players'] as $player) {
                usort($player['scores'], fn (array $left, array $right): int => $left['game_number'] <=> $right['game_number']);
                $player['scores'] = array_column($player['scores'], 'score');
                $player['total_pin'] = array_sum($player['scores']);
                $winningTotal = $winningTotal === null ? $player['total_pin'] : max($winningTotal, $player['total_pin']);
                $players[] = $player;
            }
            foreach ($players as &$player) {
                $player['is_winner'] = $player['total_pin'] === $winningTotal;
            }
            unset($player);

            $result[] = [
                'code' => $match['code'],
                'label' => $this->singleEliminationMatchLabel($match['code']),
                'players' => $players,
            ];
        }

        return $result;
    }

    private function singleEliminationMatchLabel(string $matchCode): string
    {
        $code = preg_replace('/^SE:/', '', $matchCode) ?: $matchCode;
        if ($code === 'FINAL') {
            return '優勝決定戦';
        }
        if (preg_match('/^SF-M(\d+)$/', $code, $match) === 1) {
            return '準決勝 第'.$match[1].'試合';
        }
        if (preg_match('/^QF-M(\d+)$/', $code, $match) === 1) {
            return '準々決勝 第'.$match[1].'試合';
        }
        if (preg_match('/^R(\d+)-M(\d+)$/', $code, $match) === 1) {
            return '第'.$match[1].'回戦 第'.$match[2].'試合';
        }

        return $code;
    }

    private function loadPdfScoreSnapshots(Tournament $tournament): array
    {
        $hasCurrentPublication = Schema::hasTable('tournament_result_publications')
            && DB::table('tournament_result_publications')
                ->where('tournament_id', $tournament->id)
                ->where('status', 'current')
                ->exists();

        // 新しい確定フローでは公開時に固定したsnapshotだけを使う。
        // 確定履歴がまだない既存大会は、従来どおりcurrentを使って表示を維持する。
        $snapshotRows = DB::table('tournament_result_snapshots')
            ->where('tournament_id', $tournament->id)
            ->where($hasCurrentPublication ? 'is_published' : 'is_current', true)
            ->whereIn('result_code', ['prelim_total', 'semifinal_total', 'round_robin_total'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('result_code')
            ->map(fn ($rows) => $rows->first());

        $orderedCodes = ['round_robin_total', 'semifinal_total', 'prelim_total'];
        if ($snapshotRows->isEmpty()) {
            $finalSnapshot = DB::table('tournament_result_snapshots')
                ->where('tournament_id', $tournament->id)
                ->where($hasCurrentPublication ? 'is_published' : 'is_current', true)
                ->where('result_code', 'final')
                ->orderByDesc('id')
                ->first();

            if ($finalSnapshot !== null) {
                $snapshotRows = collect(['final' => $finalSnapshot]);
                $orderedCodes = ['final'];
            }
        }
        $snapshots = [];

        foreach ($orderedCodes as $code) {
            $snapshot = $snapshotRows->get($code);
            if (! $snapshot) {
                continue;
            }

            $rows = DB::table('tournament_result_snapshot_rows')
                ->where('snapshot_id', $snapshot->id)
                ->orderBy('ranking')
                ->orderByDesc('total_pin')
                ->orderBy('id')
                ->get();

            $isPreliminary = $this->isPreliminarySnapshot($snapshot);
            $stageName = $this->resolveSnapshotStageName($snapshot);
            $totalGames = max(0, (int) ($snapshot->games_count ?? 0));
            $carryGames = max(0, (int) ($snapshot->carry_game_count ?? 0));
            $stageGames = $isPreliminary ? $totalGames : max(0, $totalGames - $carryGames);

            if ($stageGames <= 0) {
                $stageGames = $totalGames > 0 ? $totalGames : 0;
            }

            $scoreMatrix = $this->buildSnapshotScoreMatrix($tournament, $rows, $stageName, $stageGames, $carryGames);
            $participantProfiles = $this->buildSnapshotParticipantProfiles($rows, $tournament);
            $carryRankMap = (! $isPreliminary && $carryGames > 0) ? $this->buildCarryRankMap($rows) : [];

            $snapshots[] = [
                'snapshot' => $snapshot,
                'rows' => $rows,
                'is_preliminary' => $isPreliminary,
                'stage_name' => $stageName,
                'total_games' => $totalGames,
                'carry_games' => $carryGames,
                'stage_games' => $stageGames,
                'score_matrix' => $scoreMatrix,
                'participant_profiles' => $participantProfiles,
                'carry_rank_map' => $carryRankMap,
            ];
        }

        return $snapshots;
    }

    private function attachResultPdfDisplayFields($results): void
    {
        $licenses = $results
            ->map(fn ($result) => $result->pro_bowler_license_no
                ?? optional($result->player)->license_no
                ?? optional($result->bowler)->license_no
                ?? null)
            ->filter()
            ->map(fn ($license) => strtoupper(trim((string) $license)))
            ->unique()
            ->values();

        $ids = $results
            ->map(fn ($result) => $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($licenses->isEmpty() && $ids->isEmpty()) {
            return;
        }

        $proBowlers = ProBowler::query()
            ->select(['id', 'license_no', 'organization_name', 'equipment_contract'])
            ->where(function ($q) use ($licenses, $ids) {
                if ($licenses->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('upper(license_no)'), $licenses->all());
                }

                if ($ids->isNotEmpty()) {
                    $q->orWhereIn('id', $ids->all());
                }
            })
            ->get();

        $byId = $proBowlers->keyBy('id');
        $byLicense = $proBowlers->keyBy(fn ($bowler) => strtoupper(trim((string) $bowler->license_no)));

        foreach ($results as $result) {
            $existingAffiliation = trim((string) ($result->affiliation_display ?? ''));
            if ($existingAffiliation !== '') {
                $result->setAttribute('pdf_affiliation_display', $this->normalizeAffiliationDisplay($existingAffiliation));

                continue;
            }

            $bowler = null;
            $proBowlerId = $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null;
            if ($proBowlerId && $byId->has((int) $proBowlerId)) {
                $bowler = $byId->get((int) $proBowlerId);
            }

            if (! $bowler) {
                $license = strtoupper(trim((string) (
                    $result->pro_bowler_license_no
                    ?? optional($result->player)->license_no
                    ?? optional($result->bowler)->license_no
                    ?? ''
                )));

                if ($license !== '' && $byLicense->has($license)) {
                    $bowler = $byLicense->get($license);
                }
            }

            $organization = $bowler ? trim((string) ($bowler->organization_name ?? '')) : '';
            $equipment = $bowler ? trim((string) ($bowler->equipment_contract ?? '')) : '';

            $result->setAttribute('pdf_affiliation_display', $this->buildAffiliationDisplay($organization, $equipment));
        }
    }

    private function buildAffiliationDisplay(?string $organization, ?string $equipment): string
    {
        $organizationParts = $this->splitAffiliationParts((string) $organization);
        $equipmentParts = $this->splitAffiliationParts((string) $equipment);

        $parts = [];

        foreach ($organizationParts as $part) {
            if ($part !== '' && ! in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        foreach ($equipmentParts as $part) {
            if ($part === '') {
                continue;
            }

            $alreadyExists = collect($parts)->contains(fn ($existing) => $this->sameAffiliationToken($existing, $part));
            if (! $alreadyExists) {
                $parts[] = $part;
            }
        }

        return ! empty($parts) ? implode('/', $parts) : '-';
    }

    private function normalizeAffiliationDisplay(string $value): string
    {
        $parts = $this->splitAffiliationParts($value);
        $normalized = [];

        foreach ($parts as $part) {
            $alreadyExists = collect($normalized)->contains(fn ($existing) => $this->sameAffiliationToken($existing, $part));
            if (! $alreadyExists) {
                $normalized[] = $part;
            }
        }

        return ! empty($normalized) ? implode('/', $normalized) : '-';
    }

    private function splitAffiliationParts(string $value): array
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return [];
        }

        $value = str_replace(['／', '｜', '|'], '/', $value);

        return collect(explode('/', $value))
            ->map(fn ($part) => $this->normalizeAffiliationToken($part))
            ->filter(fn ($part) => $part !== '')
            ->values()
            ->all();
    }

    private function normalizeAffiliationToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['･', '・', '　'], ['・', '・', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $upper = strtoupper($value);

        if (str_contains($upper, 'HI-SP') || str_contains($value, 'ハイ・スポーツ') || str_contains($value, 'ハイスポーツ')) {
            return 'HI-SP';
        }

        if (str_contains($upper, 'SUNBRIDGE') || str_contains($value, 'サンブリッジ') || str_contains($value, 'ｻﾝﾌﾞﾘｯｼﾞ')) {
            return 'サンブリッジ';
        }

        return $value;
    }

    private function sameAffiliationToken(string $a, string $b): bool
    {
        return $this->normalizeAffiliationToken($a) === $this->normalizeAffiliationToken($b);
    }

    private function attachBowlerPeriodLabels($results): void
    {
        $periodColumn = $this->detectProBowlerPeriodColumn();

        if (! $periodColumn) {
            foreach ($results as $result) {
                $result->setAttribute('bowler_period_label', null);
            }

            return;
        }

        $licenses = $results
            ->map(fn ($result) => $result->pro_bowler_license_no
                ?? optional($result->player)->license_no
                ?? optional($result->bowler)->license_no
                ?? null)
            ->filter()
            ->map(fn ($license) => strtoupper(trim((string) $license)))
            ->unique()
            ->values();

        $ids = $results
            ->map(fn ($result) => $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $proQuery = ProBowler::query()
            ->select(array_values(array_unique(array_filter([
                'id',
                'license_no',
                $periodColumn,
            ]))));

        $proQuery->where(function ($q) use ($licenses, $ids) {
            if ($licenses->isNotEmpty()) {
                $q->orWhereIn(DB::raw('upper(license_no)'), $licenses->all());
            }

            if ($ids->isNotEmpty()) {
                $q->orWhereIn('id', $ids->all());
            }
        });

        $proBowlers = $proQuery->get();

        $byId = $proBowlers->keyBy('id');
        $byLicense = $proBowlers->keyBy(fn ($bowler) => strtoupper(trim((string) $bowler->license_no)));

        foreach ($results as $result) {
            $bowler = null;

            $proBowlerId = $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null;
            if ($proBowlerId && $byId->has((int) $proBowlerId)) {
                $bowler = $byId->get((int) $proBowlerId);
            }

            if (! $bowler) {
                $license = strtoupper(trim((string) (
                    $result->pro_bowler_license_no
                    ?? optional($result->player)->license_no
                    ?? optional($result->bowler)->license_no
                    ?? ''
                )));

                if ($license !== '' && $byLicense->has($license)) {
                    $bowler = $byLicense->get($license);
                }
            }

            $periodValue = $bowler ? ($bowler->{$periodColumn} ?? null) : null;

            $result->setAttribute('bowler_period_label', $this->formatBowlerPeriodLabel($periodValue));
        }
    }

    private function detectProBowlerPeriodColumn(): ?string
    {
        $candidates = [
            'kibetsu',
            'period_no',
            'period',
            'term_no',
            'term',
            'generation_no',
            'generation',
            'kisei',
            'pro_period',
            'pro_term',
            'license_period',
            'license_term',
            'entry_period',
            'entered_period',
            'jpba_period',
            'jpba_term',
        ];

        foreach ($candidates as $column) {
            if (Schema::hasColumn('pro_bowlers', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function formatBowlerPeriodLabel($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $label = trim((string) $value);

        if ($label === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9０-９]+/u', '', $label) ?? '';
        if ($digits !== '') {
            return mb_convert_kana($digits, 'n', 'UTF-8');
        }

        return $label;
    }

    private function buildSnapshotScorePdfData(Tournament $tournament, TournamentResultSnapshot $snapshot): array
    {
        $rows = DB::table('tournament_result_snapshot_rows')
            ->where('snapshot_id', $snapshot->id)
            ->orderBy('ranking')
            ->orderByDesc('total_pin')
            ->orderBy('id')
            ->get();

        $isPreliminary = $this->isPreliminarySnapshot($snapshot);
        $stageName = $this->resolveSnapshotStageName($snapshot);
        $totalGames = max(0, (int) ($snapshot->games_count ?? 0));
        $carryGames = max(0, (int) ($snapshot->carry_game_count ?? 0));
        $stageGames = $isPreliminary ? $totalGames : max(0, $totalGames - $carryGames);

        if ($stageGames <= 0) {
            $stageGames = $totalGames > 0 ? $totalGames : 0;
        }

        $scoreMatrix = $this->buildSnapshotScoreMatrix($tournament, $rows, $stageName, $stageGames, $carryGames);
        $seriesBlocks = $this->buildSeriesBlocks($scoreMatrix, $rows, $stageGames);
        $participantProfiles = $this->buildSnapshotParticipantProfiles($rows, $tournament);

        $carryRankMap = [];
        if (! $isPreliminary && $carryGames > 0) {
            $carryRankMap = $this->buildCarryRankMap($rows);
        }

        $orientation = $this->resolveSnapshotPdfOrientation($isPreliminary, $stageGames, $totalGames);

        return [
            'tournament' => $tournament,
            'snapshot' => $snapshot,
            'rows' => $rows,
            'isPreliminary' => $isPreliminary,
            'stageName' => $stageName,
            'totalGames' => $totalGames,
            'carryGames' => $carryGames,
            'stageGames' => $stageGames,
            'scoreMatrix' => $scoreMatrix,
            'seriesBlocks' => $seriesBlocks,
            'participantProfiles' => $participantProfiles,
            'carryRankMap' => $carryRankMap,
            'orientation' => $orientation,
            'generatedAt' => now(),
        ];
    }

    private function isPreliminarySnapshot(object $snapshot): bool
    {
        $code = (string) ($snapshot->result_code ?? '');
        $stage = (string) ($snapshot->stage_name ?? '');

        return str_starts_with($code, 'prelim_') || $stage === '予選';
    }

    private function resolveSnapshotStageName(object $snapshot): string
    {
        $stage = trim((string) ($snapshot->stage_name ?? ''));
        if ($stage !== '') {
            return $stage;
        }

        $code = (string) ($snapshot->result_code ?? '');

        if (str_starts_with($code, 'semifinal_')) {
            return '準決勝';
        }
        if (str_starts_with($code, 'quarterfinal_')) {
            return '準々決勝';
        }
        if (str_starts_with($code, 'final_')) {
            return '決勝';
        }

        return '予選';
    }

    private function resolveSnapshotPdfOrientation(bool $isPreliminary, int $stageGames, int $totalGames): string
    {
        if ($isPreliminary) {
            return $totalGames <= 8 ? 'portrait' : 'landscape';
        }

        return ($stageGames <= 4 && $totalGames <= 12) ? 'portrait' : 'landscape';
    }

    private function buildSnapshotScoreMatrix(Tournament $tournament, $rows, string $stageName, int $stageGames, int $carryGames = 0): array
    {
        if ($stageGames <= 0 || $rows->isEmpty()) {
            return [];
        }

        $scoreQuery = function (int $fromGame, int $toGame) use ($tournament, $stageName) {
            return DB::table('game_scores')
                ->where('tournament_id', $tournament->id)
                ->where('stage', $stageName)
                ->whereBetween('game_number', [$fromGame, $toGame])
                ->orderBy('game_number')
                ->get();
        };

        /*
         * THE OPEN の準決勝は、速報・正式反映では通算20Gとして扱うが、
         * game_scores 側の準決勝スコアは 17G〜20G に保存されている。
         * PDF詳細表では 1G〜4G として表示する必要があるため、
         * 非予選かつ carryGames がある場合は carryGames+1 から取得し、
         * 表示用のゲーム番号へ引き直す。
         *
         * 旧データで準決勝が 1G〜4G に保存されている場合も壊さないよう、
         * 17G〜20G が無ければ 1G〜4G をフォールバックで読む。
         */
        $dbGameOffset = 0;
        if ($carryGames > 0 && $stageName !== '予選') {
            $scoreRows = $scoreQuery($carryGames + 1, $carryGames + $stageGames);
            $dbGameOffset = $scoreRows->isNotEmpty() ? $carryGames : 0;

            if ($scoreRows->isEmpty()) {
                $scoreRows = $scoreQuery(1, $stageGames);
            }
        } else {
            $scoreRows = $scoreQuery(1, $stageGames);
        }

        $scoreBuckets = [];
        foreach ($scoreRows as $scoreRow) {
            $gameNumber = (int) $scoreRow->game_number;
            $displayGameNumber = $dbGameOffset > 0 && $gameNumber > $dbGameOffset
                ? $gameNumber - $dbGameOffset
                : $gameNumber;

            if ($displayGameNumber < 1 || $displayGameNumber > $stageGames) {
                continue;
            }

            foreach ($this->snapshotScoreKeys($scoreRow) as $key) {
                if ($key === '') {
                    continue;
                }

                $scoreBuckets[$key][$displayGameNumber] = (int) $scoreRow->score;
            }
        }

        $matrix = [];
        foreach ($rows as $row) {
            $scores = [];
            foreach ($this->snapshotRowKeys($row) as $key) {
                if ($key !== '' && isset($scoreBuckets[$key])) {
                    $scores = $scoreBuckets[$key];
                    break;
                }
            }

            for ($game = 1; $game <= $stageGames; $game++) {
                $matrix[$row->id][$game] = $scores[$game] ?? null;
            }
        }

        return $matrix;
    }

    private function snapshotRowKeys(object $row): array
    {
        $keys = [];

        $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            $keys[] = 'pro:'.$proBowlerId;
        }

        $entryNumber = trim((string) ($row->entry_number ?? ''));
        if ($entryNumber !== '') {
            $keys[] = 'entry:'.$this->normalizeSnapshotKey($entryNumber);
        }

        $license = trim((string) ($row->pro_bowler_license_no ?? ''));
        if ($license !== '' && $license !== 'アマ') {
            $keys[] = 'license:'.$this->normalizeSnapshotKey($license);
            $license4 = $this->licenseLastDigits($license);
            if ($license4 !== '') {
                $keys[] = 'license4:'.$license4;
            }
        }

        $name = trim((string) ($row->display_name ?? $row->amateur_name ?? ''));
        if ($name !== '') {
            $keys[] = 'name:'.$this->normalizeSnapshotName($name);
        }

        return array_values(array_unique($keys));
    }

    private function snapshotScoreKeys(object $scoreRow): array
    {
        $keys = [];

        $proBowlerId = (int) ($scoreRow->pro_bowler_id ?? 0);
        if ($proBowlerId > 0) {
            $keys[] = 'pro:'.$proBowlerId;
        }

        $entryNumber = trim((string) ($scoreRow->entry_number ?? ''));
        if ($entryNumber !== '') {
            $keys[] = 'entry:'.$this->normalizeSnapshotKey($entryNumber);
        }

        $license = trim((string) ($scoreRow->license_number ?? ''));
        if ($license !== '' && $license !== 'アマ') {
            $keys[] = 'license:'.$this->normalizeSnapshotKey($license);
            $license4 = $this->licenseLastDigits($license);
            if ($license4 !== '') {
                $keys[] = 'license4:'.$license4;
            }
        }

        $name = trim((string) ($scoreRow->name ?? ''));
        if ($name !== '') {
            $keys[] = 'name:'.$this->normalizeSnapshotName($name);
        }

        return array_values(array_unique($keys));
    }

    private function normalizeSnapshotKey(string $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', trim($value)) ?? trim($value));
    }

    private function normalizeSnapshotName(string $name): string
    {
        return preg_replace('/[\s　]+/u', '', trim($name)) ?? trim($name);
    }

    private function licenseLastDigits(string $license): string
    {
        $digits = preg_replace('/\D+/', '', $license) ?? '';

        if ($digits === '') {
            return '';
        }

        return ltrim(substr($digits, -4), '0') ?: '0';
    }

    private function buildSeriesBlocks(array $scoreMatrix, $rows, int $stageGames): array
    {
        $blocks = [];

        if ($stageGames <= 0) {
            return $blocks;
        }

        for ($start = 1; $start <= $stageGames; $start += 4) {
            $end = min($start + 3, $stageGames);
            $rankMap = $this->buildBlockRankMap($scoreMatrix, $rows, $start, $end);

            $blocks[] = [
                'start' => $start,
                'end' => $end,
                'games' => range($start, $end),
                'label' => $this->seriesBlockLabel($start, $end, $stageGames),
                'rank_map' => $rankMap,
            ];
        }

        return $blocks;
    }

    private function seriesBlockLabel(int $start, int $end, int $stageGames): string
    {
        if ($stageGames === 8 && $start === 1 && $end === 4) {
            return '前半';
        }
        if ($stageGames === 8 && $start === 5 && $end === 8) {
            return '後半';
        }

        return sprintf('%dG-%dG', $start, $end);
    }

    private function buildBlockRankMap(array $scoreMatrix, $rows, int $start, int $end): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $total = 0;
            $played = 0;

            for ($game = $start; $game <= $end; $game++) {
                $score = $scoreMatrix[$row->id][$game] ?? null;
                if ($score !== null) {
                    $total += (int) $score;
                    $played++;
                }
            }

            if ($played > 0) {
                $totals[] = [
                    'row_id' => $row->id,
                    'total' => $total,
                    'ranking' => (int) ($row->ranking ?? 0),
                ];
            }
        }

        usort($totals, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: ($a['ranking'] <=> $b['ranking']));

        $rankMap = [];
        $rank = 0;
        $previous = null;
        foreach ($totals as $index => $item) {
            if ($previous === null || $item['total'] !== $previous) {
                $rank = $index + 1;
                $previous = $item['total'];
            }
            $rankMap[$item['row_id']] = $rank;
        }

        return $rankMap;
    }

    private function buildCarryRankMap($rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $carry = (int) ($row->carry_pin ?? 0);
            if ($carry > 0) {
                $totals[] = [
                    'row_id' => $row->id,
                    'total' => $carry,
                    'ranking' => (int) ($row->ranking ?? 0),
                ];
            }
        }

        usort($totals, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: ($a['ranking'] <=> $b['ranking']));

        $rankMap = [];
        $rank = 0;
        $previous = null;
        foreach ($totals as $index => $item) {
            if ($previous === null || $item['total'] !== $previous) {
                $rank = $index + 1;
                $previous = $item['total'];
            }
            $rankMap[$item['row_id']] = $rank;
        }

        return $rankMap;
    }

    private function buildSnapshotParticipantProfiles($rows, Tournament $tournament): array
    {
        $rowsCollection = collect($rows);

        $periodColumn = $this->detectProBowlerPeriodColumn();
        $throwColumn = $this->detectProBowlerThrowColumn();

        $proOrganizationColumns = [
            'organization_name',
            'affiliation_name',
            'belonging_name',
            'belonging',
            'belongs_to',
            'center_name',
            'shop_name',
            'company_name',
            'workplace_name',
        ];

        $proEquipmentColumns = [
            'equipment_contract',
            'equipment_contract_name',
            'goods_contract',
            '用品契約',
            'ball_contract',
            'sponsor_name',
            'sponsor',
        ];

        $participantOrganizationColumns = [
            'display_affiliation_name',
            'affiliation_name',
            'organization_name',
            'belonging_name',
            'belonging',
            'center_name',
        ];

        $participantEquipmentColumns = [
            'display_equipment_contract',
            'equipment_contract',
            'equipment_contract_name',
            'goods_contract',
            'sponsor_name',
        ];

        $participantThrowColumns = [
            'display_dominant_arm',
            'dominant_arm',
            'throwing_hand',
            'throw_hand',
            'handedness',
        ];

        $existingColumns = function (string $table, array $columns): array {
            if (! Schema::hasTable($table)) {
                return [];
            }

            return collect($columns)
                ->filter(fn ($column) => Schema::hasColumn($table, $column))
                ->values()
                ->all();
        };

        $firstValue = function (?object $source, array $columns): string {
            if (! $source) {
                return '';
            }

            foreach ($columns as $column) {
                $value = trim((string) ($source->{$column} ?? ''));
                if ($value !== '' && $value !== '-') {
                    return $value;
                }
            }

            return '';
        };

        $proSelect = array_values(array_unique(array_filter(array_merge(
            ['id', 'license_no', 'license_no_num', 'name_kanji'],
            [$periodColumn, $throwColumn],
            $existingColumns('pro_bowlers', $proOrganizationColumns),
            $existingColumns('pro_bowlers', $proEquipmentColumns)
        ))));

        $participantSelect = array_values(array_unique(array_filter(array_merge(
            [
                'id',
                'tournament_id',
                'pro_bowler_id',
                'pro_bowler_license_no',
                'display_name',
                'participant_type',
                'amateur_bowler_id',
            ],
            $existingColumns('tournament_participants', $participantOrganizationColumns),
            $existingColumns('tournament_participants', $participantEquipmentColumns),
            $existingColumns('tournament_participants', $participantThrowColumns)
        ))));

        $proBowlerIds = $rowsCollection
            ->pluck('pro_bowler_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $rowLicenses = $rowsCollection
            ->pluck('pro_bowler_license_no')
            ->map(fn ($license) => trim((string) $license))
            ->filter(fn ($license) => $license !== '' && $license !== 'アマ')
            ->unique()
            ->values();

        $rowLicenseLast4s = $rowLicenses
            ->map(fn ($license) => $this->licenseLastDigits($license))
            ->filter()
            ->unique()
            ->values();

        $rowNames = $rowsCollection
            ->map(fn ($row) => trim((string) ($row->display_name ?? $row->amateur_name ?? '')))
            ->filter()
            ->unique()
            ->values();

        $proBowlers = collect();
        if (! empty($proSelect) && ($proBowlerIds->isNotEmpty() || $rowLicenses->isNotEmpty() || $rowLicenseLast4s->isNotEmpty() || $rowNames->isNotEmpty())) {
            $proQuery = ProBowler::query()->select($proSelect);
            $proQuery->where(function ($query) use ($proBowlerIds, $rowLicenses, $rowLicenseLast4s, $rowNames): void {
                $hasCondition = false;

                if ($proBowlerIds->isNotEmpty()) {
                    $query->whereIn('id', $proBowlerIds->all());
                    $hasCondition = true;
                }

                if (Schema::hasColumn('pro_bowlers', 'license_no') && $rowLicenses->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('license_no', $rowLicenses->all());
                    $hasCondition = true;
                }

                if (Schema::hasColumn('pro_bowlers', 'license_no_num') && $rowLicenseLast4s->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('license_no_num', $rowLicenseLast4s->all());
                    $hasCondition = true;
                }

                if (Schema::hasColumn('pro_bowlers', 'name_kanji') && $rowNames->isNotEmpty()) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('name_kanji', $rowNames->all());
                }
            });

            $proBowlers = $proQuery->get();
        }

        $proById = $proBowlers->keyBy('id');
        $proByLicense = [];
        $proByLast4 = [];
        $proByName = [];

        foreach ($proBowlers as $bowler) {
            $license = trim((string) ($bowler->license_no ?? ''));
            if ($license !== '') {
                $proByLicense[$this->normalizeSnapshotKey($license)] = $bowler;
                $last4 = $this->licenseLastDigits($license);
                if ($last4 !== '') {
                    $proByLast4[$last4] = $bowler;
                }
            }

            $licenseNoNum = trim((string) ($bowler->license_no_num ?? ''));
            if ($licenseNoNum !== '') {
                $proByLast4[$this->licenseLastDigits($licenseNoNum)] = $bowler;
            }

            $name = trim((string) ($bowler->name_kanji ?? ''));
            if ($name !== '') {
                $proByName[$this->normalizeSnapshotName($name)] = $bowler;
            }
        }

        $participants = collect();
        if (! empty($participantSelect) && Schema::hasTable('tournament_participants')) {
            $participants = DB::table('tournament_participants')
                ->where('tournament_id', $tournament->id)
                ->select($participantSelect)
                ->get();
        }

        $participantByProId = [];
        $participantByLicense = [];
        $participantByLast4 = [];
        $participantByName = [];

        foreach ($participants as $participant) {
            $participantProId = (int) ($participant->pro_bowler_id ?? 0);
            if ($participantProId > 0) {
                $participantByProId[$participantProId] = $participant;
            }

            $license = trim((string) ($participant->pro_bowler_license_no ?? ''));
            if ($license !== '') {
                $participantByLicense[$this->normalizeSnapshotKey($license)] = $participant;
                $last4 = $this->licenseLastDigits($license);
                if ($last4 !== '') {
                    $participantByLast4[$last4] = $participant;
                }
            }

            $name = trim((string) ($participant->display_name ?? ''));
            if ($name !== '') {
                $participantByName[$this->normalizeSnapshotName($name)] = $participant;
            }
        }

        $findParticipant = function (object $row) use ($participantByProId, $participantByLicense, $participantByLast4, $participantByName): ?object {
            $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
            if ($proBowlerId > 0 && isset($participantByProId[$proBowlerId])) {
                return $participantByProId[$proBowlerId];
            }

            $license = trim((string) ($row->pro_bowler_license_no ?? ''));
            if ($license !== '' && $license !== 'アマ') {
                $licenseKey = $this->normalizeSnapshotKey($license);
                if (isset($participantByLicense[$licenseKey])) {
                    return $participantByLicense[$licenseKey];
                }

                $last4 = $this->licenseLastDigits($license);
                if ($last4 !== '' && isset($participantByLast4[$last4])) {
                    return $participantByLast4[$last4];
                }
            }

            $name = trim((string) ($row->display_name ?? $row->amateur_name ?? ''));
            if ($name !== '') {
                $nameKey = $this->normalizeSnapshotName($name);
                if (isset($participantByName[$nameKey])) {
                    return $participantByName[$nameKey];
                }
            }

            return null;
        };

        $findBowler = function (object $row, ?object $participant = null) use ($proById, $proByLicense, $proByLast4, $proByName): ?object {
            $participantProId = (int) ($participant->pro_bowler_id ?? 0);
            if ($participantProId > 0 && $proById->has($participantProId)) {
                return $proById->get($participantProId);
            }

            $proBowlerId = (int) ($row->pro_bowler_id ?? 0);
            if ($proBowlerId > 0 && $proById->has($proBowlerId)) {
                return $proById->get($proBowlerId);
            }

            foreach ([$row->pro_bowler_license_no ?? '', $participant->pro_bowler_license_no ?? ''] as $license) {
                $license = trim((string) $license);
                if ($license === '' || $license === 'アマ') {
                    continue;
                }

                $licenseKey = $this->normalizeSnapshotKey($license);
                if (isset($proByLicense[$licenseKey])) {
                    return $proByLicense[$licenseKey];
                }

                $last4 = $this->licenseLastDigits($license);
                if ($last4 !== '' && isset($proByLast4[$last4])) {
                    return $proByLast4[$last4];
                }
            }

            foreach ([$row->display_name ?? '', $participant->display_name ?? ''] as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $nameKey = $this->normalizeSnapshotName($name);
                if (isset($proByName[$nameKey])) {
                    return $proByName[$nameKey];
                }
            }

            return null;
        };

        $amateurProfiles = $this->buildSnapshotAmateurProfileLookup($tournament);

        $profiles = [];
        foreach ($rows as $row) {
            $license = trim((string) ($row->pro_bowler_license_no ?? ''));
            $entryNumber = trim((string) ($row->entry_number ?? ''));
            $amateurName = trim((string) ($row->amateur_name ?? ''));
            $displayName = trim((string) ($row->display_name ?? ''));
            $participant = $findParticipant($row);

            $isAmateur = $license === 'アマ'
                || str_starts_with(strtoupper($entryNumber), 'AM-')
                || $amateurName !== ''
                || (string) ($participant->participant_type ?? '') === 'amateur';

            $amateurProfile = $isAmateur
                ? $this->findSnapshotAmateurProfile($amateurProfiles, $entryNumber, $displayName, $amateurName)
                : null;

            if ($isAmateur) {
                $throwValue = $amateurProfile['dominant_arm'] ?? '';
                if ($throwValue === '') {
                    $throwValue = $firstValue($participant, $participantThrowColumns);
                }

                $affiliationName = trim((string) ($amateurProfile['affiliation_name'] ?? ''));
                $equipmentContract = trim((string) ($amateurProfile['equipment_contract'] ?? ''));

                if ($affiliationName === '') {
                    $affiliationName = $firstValue($participant, $participantOrganizationColumns);
                }
                if ($equipmentContract === '') {
                    $equipmentContract = $firstValue($participant, $participantEquipmentColumns);
                }

                $affiliation = $this->buildAffiliationDisplay($affiliationName, $equipmentContract);

                $profiles[$row->id] = [
                    'period' => '選手',
                    'throw' => $this->formatThrowingHandLabel($throwValue),
                    'affiliation' => $affiliation !== '' ? $affiliation : '-',
                    'license_display' => 'アマ',
                ];

                continue;
            }

            $bowler = $findBowler($row, $participant);

            $period = $bowler && $periodColumn ? $this->formatBowlerPeriodLabel($bowler->{$periodColumn} ?? null) : null;

            $throwValue = $firstValue($participant, $participantThrowColumns);
            if ($throwValue === '' && $bowler && $throwColumn) {
                $throwValue = trim((string) ($bowler->{$throwColumn} ?? ''));
            }

            $organization = $firstValue($participant, $participantOrganizationColumns);
            $equipment = $firstValue($participant, $participantEquipmentColumns);

            if ($organization === '') {
                $organization = $firstValue($bowler, $proOrganizationColumns);
            }
            if ($equipment === '') {
                $equipment = $firstValue($bowler, $proEquipmentColumns);
            }

            $affiliation = $this->buildAffiliationDisplay($organization, $equipment);

            $profiles[$row->id] = [
                'period' => $period ?? '',
                'throw' => $this->formatThrowingHandLabel($throwValue),
                'affiliation' => $affiliation !== '' ? $affiliation : '-',
                'license_display' => $this->formatSnapshotLicenseForPdf($license),
            ];
        }

        return $profiles;
    }

    private function buildSnapshotAmateurProfileLookup(Tournament $tournament): array
    {
        if (! Schema::hasTable('amateur_bowlers') || ! Schema::hasColumn('tournament_participants', 'amateur_bowler_id')) {
            return [];
        }

        $rows = DB::table('tournament_participants as tp')
            ->leftJoin('amateur_bowlers as ab', 'ab.id', '=', 'tp.amateur_bowler_id')
            ->where('tp.tournament_id', $tournament->id)
            ->where('tp.participant_type', 'amateur')
            ->select([
                'tp.pro_bowler_license_no',
                'tp.display_name',
                'tp.display_dominant_arm',
                'tp.display_affiliation_name',
                'tp.display_equipment_contract',
                'ab.name as master_name',
                'ab.dominant_arm as master_dominant_arm',
                'ab.affiliation_name as master_affiliation_name',
                'ab.equipment_contract as master_equipment_contract',
            ])
            ->get();

        $lookup = [];

        foreach ($rows as $row) {
            $profile = [
                'dominant_arm' => trim((string) ($row->display_dominant_arm ?? '')) ?: trim((string) ($row->master_dominant_arm ?? '')),
                'affiliation_name' => trim((string) ($row->display_affiliation_name ?? '')) ?: trim((string) ($row->master_affiliation_name ?? '')),
                'equipment_contract' => trim((string) ($row->display_equipment_contract ?? '')) ?: trim((string) ($row->master_equipment_contract ?? '')),
            ];

            $entryNumber = trim((string) ($row->pro_bowler_license_no ?? ''));
            if ($entryNumber !== '') {
                $lookup['entry:'.$this->normalizeSnapshotKey($entryNumber)] = $profile;
            }

            foreach ([$row->display_name ?? '', $row->master_name ?? ''] as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $lookup['name:'.$this->normalizeSnapshotName($name)] = $profile;
                }
            }
        }

        return $lookup;
    }

    private function findSnapshotAmateurProfile(array $lookup, string $entryNumber, string $displayName, string $amateurName): ?array
    {
        $entryNumber = trim($entryNumber);
        if ($entryNumber !== '') {
            $key = 'entry:'.$this->normalizeSnapshotKey($entryNumber);
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        foreach ([$displayName, $amateurName] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $key = 'name:'.$this->normalizeSnapshotName($name);
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private function detectProBowlerThrowColumn(): ?string
    {
        $candidates = [
            'dominant_hand',
            'throwing_hand',
            'throw_hand',
            'handedness',
            'hand',
            'dominant_arm',
        ];

        foreach ($candidates as $column) {
            if (Schema::hasColumn('pro_bowlers', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function formatThrowingHandLabel($value): string
    {
        $label = trim((string) $value);
        if ($label === '') {
            return '';
        }

        $upper = strtoupper($label);

        return match ($upper) {
            'R', 'RIGHT', '右投げ', '右' => '右',
            'L', 'LEFT', '左投げ', '左' => '左',
            'B', 'BOTH', '両', '両手' => '両',
            default => $label,
        };
    }

    private function formatSnapshotLicenseForPdf(?string $license): string
    {
        $license = trim((string) $license);
        if ($license === '') {
            return '-';
        }

        if ($license === 'アマ') {
            return 'アマ';
        }

        $seedService = app(\App\Services\ProBowlerSeedService::class);
        $display = $seedService->formatLicenseForPdf($license, false);

        return $display === '' ? $license : $display;
    }

    private function makeOfficialStandardTournamentPdf(array $data, string $downloadName)
    {
        $snapshots = collect($data['scoreSnapshots'] ?? []);
        $snapshotRowCount = function (string $resultCode) use ($snapshots): int {
            $set = $snapshots->first(
                fn ($item): bool => trim((string) (($item['snapshot']->result_code ?? ''))) === $resultCode
            );

            return count($set['rows'] ?? []);
        };

        $sections = [
            ['name' => 'overview', 'orientation' => 'landscape', 'enabled' => true],
            ['name' => 'awards', 'orientation' => 'landscape', 'enabled' => true],
            [
                'name' => 'step_ladder',
                'orientation' => 'portrait',
                'enabled' => ! empty($data['stepLadderPdf']['seeds'] ?? [])
                    || ! empty($data['matchScoreSheetImages'] ?? []),
            ],
            [
                'name' => 'round_robin_ranking',
                'orientation' => 'landscape',
                'enabled' => ! empty($data['roundRobinPdf']['players'] ?? []),
            ],
            [
                'name' => 'round_robin_matches',
                'orientation' => 'landscape',
                'enabled' => ! empty($data['roundRobinPdf']['players'] ?? []),
            ],
            [
                'name' => 'semifinal',
                'orientation' => 'landscape',
                'enabled' => $snapshotRowCount('semifinal_total') > 0,
            ],
            [
                'name' => 'prelim_1',
                'orientation' => 'portrait',
                'enabled' => $snapshotRowCount('prelim_total') > 0,
            ],
            [
                'name' => 'prelim_2',
                'orientation' => 'portrait',
                'enabled' => $snapshotRowCount('prelim_total') > 57,
            ],
        ];

        $parts = [];
        foreach ($sections as $section) {
            if (! $section['enabled']) {
                continue;
            }

            $sectionData = array_merge($data, ['standardPdfSection' => $section['name']]);
            $parts[] = $this->buildPdfWithJapaneseFont(
                'tournament_results.pdf',
                $sectionData,
                $section['orientation']
            )->output();
        }

        /** @var MixedOrientationPdfService $merger */
        $merger = app(MixedOrientationPdfService::class);
        $binary = $merger->merge($parts);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($binary),
            'Content-Disposition' => 'attachment; filename="tournament_results.pdf"; filename*=UTF-8\'\''.rawurlencode($downloadName),
        ]);
    }

    private function makePdfWithJapaneseFont(string $view, array $data, string $downloadName, string $orientation = 'portrait')
    {
        return $this->buildPdfWithJapaneseFont($view, $data, $orientation)->download($downloadName);
    }

    private function buildPdfWithJapaneseFont(string $view, array $data, string $orientation = 'portrait')
    {
        /*
         * THE OPENのように、入賞者リスト・ステップラダー画像・スコアシート画像を
         * 1つのPDFにまとめると、dompdfの描画中に128MBを超えて落ちることがある。
         * PDF生成リクエストだけメモリ上限と実行時間を引き上げ、dompdfの一時ディレクトリも
         * Laravel配下へ固定する。
         */
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', '300');
        }

        $this->forgetTournamentResultPdfSharedViewData();

        $dompdfTempDir = storage_path('framework/cache/dompdf');
        if (! is_dir($dompdfTempDir)) {
            @mkdir($dompdfTempDir, 0775, true);
        }

        $pdf = Pdf::loadView($view, $data)->setPaper('a4', $orientation === 'landscape' ? 'landscape' : 'portrait');

        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();

        $options->set('tempDir', $dompdfTempDir);
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('defaultFont', 'ipaexg');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', base_path());

        $fontPath = storage_path('fonts/ipaexg.ttf');

        if (is_file($fontPath)) {
            $dompdf->getFontMetrics()->registerFont(
                [
                    'family' => 'ipaexg',
                    'style' => 'normal',
                    'weight' => 'normal',
                ],
                $fontPath
            );
        }

        return $pdf;
    }

    private function forgetTournamentResultPdfSharedViewData(): void
    {
        $keys = [
            'shootoutPdf',
            'shootoutBracketImage',
            'matchScoreSheets',
            'matchScoreSheetImages',
            'stepLadderPdf',
            'stepLadderBracketImage',
            'singleEliminationPdf',
            'singleEliminationBracketImage',
            'scoreImages',
            'firstScoreImage',
            'remainingScoreImages',
            'scoreHeading',
            'resultRows',
            'jpbaLogoSrc',
            'venueText',
            'dateText',
            'pdfMode',
            'pdfCategory',
            'finalFormat',
            'isSeasonTrialPdf',
            'isShootoutFlow',
            'isSingleEliminationFlow',
            'isStepLadderFlow',
            'stageNumber',
            'officialMainTitle',
            'officialSeriesTitle',
            'officialSeasonTitle',
            'officialVenueTitle',
            'finalQualifierCount',
            'finalFormatLabel',
            'seriesTitle',
            'resolveName',
            'resolveLicense',
            'resolveRank',
            'resolvePeriod',
            'resolveBelong',
            'belongTextClass',
            'resolveNumber',
            'formatNumber',
            'formatPrize',
            'pdfScoreSnapshots',
            'prelimPlayerCount',
            'prelimGameCount',
            'prelimQualifierCount',
            'semifinalGameCount',
            'semifinalTotalGameCount',
            'roundRobinGameCount',
            'semifinalQualifierCount',
            'snapshotValue',
            'snapshotLicense',
            'snapshotName',
            'snapshotPeriod',
            'snapshotArm',
            'snapshotBelong',
            'snapshotBelongClass',
            'snapshotScoreFor',
            'snapshotTitle',
            'scoreTextClass',
            'snapshotLicenseKey',
            'snapshotPrelimRank',
            'stepPointLabelForSemifinalRank',
        ];

        try {
            $factory = view();
            $reflection = new \ReflectionObject($factory);
            if (! $reflection->hasProperty('shared')) {
                return;
            }

            $property = $reflection->getProperty('shared');
            $property->setAccessible(true);
            $shared = $property->getValue($factory);
            if (! is_array($shared)) {
                return;
            }

            foreach ($keys as $key) {
                unset($shared[$key]);
            }

            $property->setValue($factory, $shared);
        } catch (\Throwable) {
            // Best-effort cleanup for long-running CLI PDF regression/export processes.
        }
    }
}
