<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\ProBowler;
use App\Models\TournamentPoint;
use App\Models\TournamentAward;
use App\Models\TournamentResult;
use App\Models\PointDistribution;
use App\Models\PrizeDistribution;
use App\Models\ProBowlerTitle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
            $q->where('name', 'like', '%' . $request->name . '%');
        }
        $tournaments = $q->orderByDesc('year')->orderBy('start_date')->get();

        // 既存のビュー（tournament_results/index.blade.php）が $tournaments を期待
        return view('tournament_results.index', compact('tournaments'));
    }

    /** 大会ごとの成績一覧（←“成績一覧” 入口） */
    public function index(Tournament $tournament)
    {
        $rankCol = collect(['ranking','rank','position','placing','result_rank','order_no'])
            ->first(fn($c) => Schema::hasColumn('tournament_results', $c));

        $q = TournamentResult::with(['bowler','player'])
            ->where('tournament_id', $tournament->id);

        $rankCol ? $q->orderBy($rankCol) : $q->orderBy('id');
        $results = $q->get();

        return view('tournament_results.show', compact('tournament', 'results'));
    }

    /* ---- 以降はあなたの現状ロジックを維持 ---- */

    public function create()
    {
        $tournaments = Tournament::all();
        $players     = ProBowler::all();
        return view('tournament_results.create', compact('tournaments','players'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'tournament_id'  => ['required','integer','exists:tournaments,id'],
            'player_mode'    => ['required','in:pro,ama'],
            'pro_key'        => ['required_if:player_mode,pro','nullable','string','max:255'],
            'amateur_name'   => ['required_if:player_mode,ama','nullable','string','max:255'],
            'ranking'        => ['required','integer','min:1','max:10000'],
            'total_pin'      => ['required','integer','min:0','max:200000'],
            'games'          => ['required','integer','min:1','max:200'],
            'ranking_year'   => ['required','integer','min:1900','max:2100'],
        ], [], [
            'player_mode'  => '選手区分',
            'pro_key'      => 'プロ選手（ライセンス/氏名）',
            'amateur_name' => 'アマチュア選手名',
        ]);

        $pro     = null;
        $license = null;

        if ($v['player_mode'] === 'pro') {
            $pro = $this->resolvePro($v['pro_key'] ?? '');
            if (!$pro) {
                return back()
                    ->withErrors(['pro_key' => '該当するプロが見つかりません。ライセンスNo（例: M0123 / F0456 / m000123 等）または氏名を正確に入力してください。'])
                    ->withInput();
            }
            $license = $pro->license_no ?? null;
        }

        $games   = max(1, (int)$v['games']);
        $average = round(((int)$v['total_pin']) / $games, 2);
        $points  = $pro ? (TournamentPoint::where('rank', (int)$v['ranking'])->value('point') ?? 0) : 0;
        $prize   = $pro ? (TournamentAward::where('tournament_id', (int)$v['tournament_id'])
                                    ->where('rank', (int)$v['ranking'])->value('prize_money') ?? 0) : 0;

        $data = [
            'tournament_id'  => (int)$v['tournament_id'],
            'ranking_year'   => (int)$v['ranking_year'],
            'ranking'        => (int)$v['ranking'],
            'total_pin'      => (int)$v['total_pin'],
            'games'          => (int)$v['games'],
            'average'        => $average,
            'points'         => $points,
            'prize_money'    => $prize,
            'amateur_name'   => $pro ? null : ($v['amateur_name'] ?? null),
        ];

        if ($pro) {
            $data['pro_bowler_license_no'] = $license;
            if (Schema::hasColumn('tournament_results', 'pro_bowler_id')) {
                $data['pro_bowler_id'] = $pro->id;
            }
        }

        TournamentResult::create($data);

        return redirect()
            ->route('tournaments.results.show', (int)$v['tournament_id'])
            ->with('success', '大会成績を登録しました。');
    }

    /**
     * ライセンス or 氏名からプロを一意特定（ゼロ詰めゆらぎ対応）
     */
    private function resolvePro(?string $key): ?ProBowler
    {
        if (!$key) return null;
        $k = trim($key);

        // 1) まずは完全一致（大文字で）
        $exactByLicense = ProBowler::whereRaw('upper(license_no) = ?', [strtoupper($k)])->first();
        if ($exactByLicense) return $exactByLicense;

        // 2) "M/F + 0* + 数字" を標準化して探す（M1278 と M01278 の相互ヒット）
        if (preg_match('/^([MF])\s*0*?(\d+)$/i', $k, $m)) {
            $letter = strtoupper($m[1]);
            $digits = ltrim($m[2], '0');   // 標準化（頭の0除去）… "1278"
            if ($digits === '') $digits = '0';

            // a) 正規化版（M1278）
            $hit = ProBowler::whereRaw('upper(license_no) = ?', [$letter.$digits])->first();
            if ($hit) return $hit;

            // b) よくあるゼロ詰め（3～6桁まで面倒見ます）
            foreach ([3,4,5,6] as $len) {
                $candidate = $letter . str_pad($digits, $len, '0', STR_PAD_LEFT); // M001278 など
                $hit = ProBowler::whereRaw('upper(license_no) = ?', [$candidate])->first();
                if ($hit) return $hit;
            }

            // c) 最後の手段：DBから同じ先頭文字の候補を取り出して PHP 側でゼロを無視して照合
            $candidates = ProBowler::whereRaw('upper(left(license_no,1)) = ?', [$letter])->get();
            foreach ($candidates as $p) {
                $pDigits = preg_replace('/^[MF]0*/i', '', $p->license_no); // M0001278 -> 1278
                if ((string)$pDigits === (string)$digits) return $p;
            }
        }

        // 3) 氏名（漢字・フリガナ）：完全一致 → 前方一致で1件だけならOK
        $byExact = ProBowler::where('name_kanji', $k)->orWhere('name_kana', $k)->get();
        if ($byExact->count() === 1) return $byExact->first();
        if ($byExact->count() > 1)  return null;

        $byLike = ProBowler::where('name_kanji', 'like', $k.'%')
                    ->orWhere('name_kana', 'like', $k.'%')
                    ->limit(2)->get();
        return $byLike->count() === 1 ? $byLike->first() : null;
    }

    public function edit($id)
    {
        $result      = TournamentResult::findOrFail($id);
        $players     = ProBowler::all();
        $tournaments = Tournament::all();
        return view('tournament_results.edit', compact('result','players','tournaments'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'pro_bowler_license_no' => 'required',
            'tournament_id'         => 'required',
            'ranking'               => 'required|integer',
            'total_pin'             => 'required|integer',
            'games'                 => 'required|integer|min:1',
            'ranking_year'          => 'required|integer',
        ]);

        $result  = TournamentResult::findOrFail($id);

        $average = round($request->total_pin / max(1,$request->games), 2);
        $point   = TournamentPoint::where('rank', $request->ranking)->value('point') ?? 0;
        $prize   = TournamentAward::where('tournament_id', $request->tournament_id)
                    ->where('rank', $request->ranking)->value('prize_money') ?? 0;

        $result->update([
            'pro_bowler_license_no' => $request->pro_bowler_license_no,
            'tournament_id'         => $request->tournament_id,
            'ranking'               => $request->ranking,
            'points'                => $point,
            'total_pin'             => $request->total_pin,
            'games'                 => $request->games,
            'average'               => $average,
            'prize_money'           => $prize,
            'ranking_year'          => $request->ranking_year,
        ]);

        // ★ 成績一覧へ戻す（フラッシュ付き）
        return redirect()
            ->route('tournaments.results.show', (int)$result->tournament_id)
            ->with('success', '成績を更新しました。');
    }

    public function createForTournament(Tournament $tournament)
    {
        $players = ProBowler::all();
        return view('tournament_results.create_for_tournament', compact('tournament','players'));
    }

    public function storeForTournament(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'results'                           => 'required|array',
            'results.*.pro_bowler_license_no'   => 'required',
            'results.*.ranking'                 => 'required|integer',
            'results.*.total_pin'               => 'required|integer',
            'results.*.games'                   => 'required|integer|min:1',
            'results.*.ranking_year'            => 'required|integer',
        ]);

        foreach ($validated['results'] as $data) {
            $average = round($data['total_pin'] / $data['games'], 2);
            $point   = TournamentPoint::where('rank', $data['ranking'])->value('point') ?? 0;
            $prize   = TournamentAward::where('tournament_id', $tournament->id)
                        ->where('rank', $data['ranking'])->value('prize_money') ?? 0;

            TournamentResult::create([
                'pro_bowler_license_no' => $data['pro_bowler_license_no'],
                'tournament_id'         => $tournament->id,
                'ranking'               => $data['ranking'],
                'points'                => $point,
                'total_pin'             => $data['total_pin'],
                'games'                 => $data['games'],
                'average'               => $average,
                'prize_money'           => $prize,
                'ranking_year'          => $data['ranking_year'],
            ]);
        }

        return redirect()->route('tournaments.results.index', $tournament)->with('success','成績を登録しました。');
    }

    public function batchCreate(Request $request)
    {
        $tournamentId = $request->query('tournament_id');
        $tournaments  = Tournament::all();
        $players      = ProBowler::all();
        return view('tournament_results.batch_create', compact('tournaments','players','tournamentId'));
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'tournament_id' => ['required','integer','exists:tournaments,id'],
            'ranking_year'  => ['required','integer','min:1900','max:2100'],
        ]);

        $tid  = (int) $request->input('tournament_id');
        $year = (int) $request->input('ranking_year');

        // 旧: results[ { pro_bowler_license_no, ranking, total_pin, games } ... ]
        $legacyRows = $request->input('results', []);

        // 新: rows[ { player_mode, pro_key, amateur_name, ranking, total_pin, games } ... ]
        $newRows    = $request->input('rows', []);

        // どちらか一方（両方来てもOK）
        $rows = [];

        // 旧 → 正規化
        foreach ($legacyRows as $r) {
            if (empty($r['pro_bowler_license_no']) && empty($r['ranking']) && empty($r['total_pin']) && empty($r['games'])) {
                continue;
            }
            $rows[] = [
                'player_mode'  => 'pro',
                'pro_key'      => $r['pro_bowler_license_no'] ?? null,
                'amateur_name' => null,
                'ranking'      => (int)($r['ranking'] ?? 0),
                'total_pin'    => (int)($r['total_pin'] ?? 0),
                'games'        => (int)($r['games'] ?? 0),
            ];
        }

        // 新 → そのまま
        foreach ($newRows as $r) {
            if (empty($r['pro_key']) && empty($r['amateur_name']) &&
                empty($r['ranking']) && empty($r['total_pin']) && empty($r['games'])) {
                continue;
            }
            $rows[] = [
                'player_mode'  => $r['player_mode'] ?? 'pro',
                'pro_key'      => $r['pro_key'] ?? null,
                'amateur_name' => $r['amateur_name'] ?? null,
                'ranking'      => (int)($r['ranking'] ?? 0),
                'total_pin'    => (int)($r['total_pin'] ?? 0),
                'games'        => (int)($r['games'] ?? 0),
            ];
        }

        foreach ($rows as $entry) {
            $isPro  = ($entry['player_mode'] ?? 'pro') === 'pro';
            $pro    = null;
            $license= null;

            if ($isPro) {
                $pro = $this->resolvePro($entry['pro_key'] ?? '');
                if (!$pro) {
                    return back()->withErrors(['rows' => '不明なプロ選手があります（'.$entry['pro_key'].'）。'])->withInput();
                }
                $license = $pro->license_no ?? null;
            }

            $games   = max(1, (int)$entry['games']);
            $average = round(((int)$entry['total_pin']) / $games, 2);
            $points  = $isPro ? ( TournamentPoint::where('rank', (int)$entry['ranking'])->value('point') ?? 0 ) : 0;
            $prize   = $isPro ? ( TournamentAward::where('tournament_id', $tid)
                                            ->where('rank', (int)$entry['ranking'])->value('prize_money') ?? 0 ) : 0;

            $data = [
                'tournament_id'           => $tid,
                'ranking_year'            => $year,
                'ranking'                 => (int)$entry['ranking'],
                'total_pin'               => (int)$entry['total_pin'],
                'games'                   => (int)$entry['games'],
                'average'                 => $average,
                'points'                  => $points,
                'prize_money'             => $prize,
                'amateur_name'            => $isPro ? null : ($entry['amateur_name'] ?? null),
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

        return redirect()->route('tournaments.results.show', $tid)
                        ->with('success','一括登録を完了しました。');
    }

    public function applyAwardsAndPoints(Tournament $tournament)
    {
        $id      = $tournament->id;
        $results = TournamentResult::where('tournament_id', $id)->get();

        foreach ($results as $result) {
            $point = PointDistribution::where('tournament_id', $id)
                        ->where('rank', $result->ranking)->value('points') ?? 0;

            $prize = PrizeDistribution::where('tournament_id', $id)
                        ->where('rank', $result->ranking)->value('amount') ?? 0;

            $result->update([
                'points'      => $point,
                'prize_money' => $prize,
            ]);
        }

        return back()->with('success','賞金とポイントを反映しました。');
    }

    public function syncTitles(Request $request, Tournament $tournament)
    {
        $year = $request->input('year');

        // 順位列は存在するものを自動検出して 1 位だけ拾う
        $winners = TournamentResult::query()
            ->where('tournament_id', $tournament->id)
            ->where(function ($q) {
                foreach (['ranking','rank','position','placing','result_rank','order_no'] as $col) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('tournament_results', $col)) {
                        $q->orWhere($col, 1);
                    }
                }
            })
            ->when($year, function ($q) use ($year) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('tournament_results','ranking_year')) {
                    $q->where('ranking_year', $year);
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('tournament_results','year')) {
                    $q->where('year', $year);
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('tournament_results','date')) {
                    $q->whereYear('date', $year);
                }
            })
            ->get();

        $created = 0; $skipped = 0; $missing = 0;

        foreach ($winners as $r) {
            // 成績→選手IDの特定（license_no 経由も許容）
            $bowlerId = $r->pro_bowler_id
                ?? \App\Models\ProBowler::where('license_no', $r->license_no ?? $r->pro_bowler_license_no ?? null)->value('id');

            if (!$bowlerId) { $missing++; continue; }

            $payload = [
                'pro_bowler_id' => $bowlerId,
                'tournament_id' => $tournament->id,           // ← FKで紐付け
                'title_name'    => $tournament->name,         // ← 表示用スナップショットは title_name に保存
                'year'          => $r->ranking_year
                                    ?? ($r->year ?? (optional($r->date)->year) ?? $tournament->year),
                'won_date'      => $r->date ?? null,
                'source'        => 'sync_from_results',
            ];

            // 同一大会（＝同一 tournament_id）での重複作成を防止
            $unique = [
                'pro_bowler_id' => $payload['pro_bowler_id'],
                'tournament_id' => $payload['tournament_id'],
            ];

            $title = \App\Models\ProBowlerTitle::firstOrCreate($unique, $payload);
            $title->wasRecentlyCreated ? $created++ : $skipped++;
        }

        return back()->with('success', "タイトル反映：新規 {$created}／既存 {$skipped}／選手未特定 {$missing}");
    }

    public function rankings(Request $request)
    {
        $year  = $request->input('year', date('Y'));
        $years = TournamentResult::select('ranking_year')->distinct()->orderByDesc('ranking_year')->pluck('ranking_year');

        $moneyRanks = TournamentResult::where('ranking_year', $year)
            ->select('pro_bowler_license_no', DB::raw('SUM(prize_money) as total_prize_money'))
            ->groupBy('pro_bowler_license_no')->orderByDesc('total_prize_money')->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no',$item->pro_bowler_license_no)->first())->name_kanji;
                return $item;
            });

        $pointRanks = TournamentResult::where('ranking_year', $year)
            ->select('pro_bowler_license_no', DB::raw('SUM(points) as total_points'))
            ->groupBy('pro_bowler_license_no')->orderByDesc('total_points')->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no',$item->pro_bowler_license_no)->first())->name_kanji;
                return $item;
            });

        $averageRanks = TournamentResult::where('ranking_year', $year)
            ->select('pro_bowler_license_no', DB::raw('AVG(average) as avg_average'))
            ->groupBy('pro_bowler_license_no')->orderByDesc('avg_average')->get()
            ->map(function ($item) {
                $item->name_kanji = optional(ProBowler::where('license_no',$item->pro_bowler_license_no)->first())->name_kanji;
                return $item;
            });

        return view('tournament_results.rankings', compact('year','years','moneyRanks','pointRanks','averageRanks'));
    }

    public function exportPdf()
    {
        $results = TournamentResult::with(['tournament','player'])->orderBy('ranking_year','desc')->get();
        $pdf = Pdf::loadView('tournament_results.pdf', compact('results'));
        return $pdf->download('tournament_results.pdf');
    }

    public function destroy(TournamentResult $result)
    {
        $tid = $result->tournament_id;
        $result->delete();

        return redirect()
            ->route('tournaments.results.show', $tid)
            ->with('success', '成績を削除しました。');
    }

}
