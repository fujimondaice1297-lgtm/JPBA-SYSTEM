<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use App\Models\GameScore;
use App\Services\ScoreService;

final class ScoreController extends Controller
{
    /* ================= 入力画面（既存） ================= */
    public function input(Request $r)
    {
        $tournaments = Tournament::orderBy('start_date','desc')
            ->orderBy('id','desc')->get(['id','name']);

        $new = (array)session('stage_settings', []);
        $old = (array)session('score_settings', []);

        $stageSettingsMap = [];

        foreach ($new as $tid => $st) {
            $tid = (string)$tid;
            foreach ((array)$st as $label => $g) {
                $g = (int)$g;
                if ($g > 0) $stageSettingsMap[$tid][$label] = $g;
            }
        }

        foreach ($old as $tid => $st) {
            $tid = (string)$tid;
            $pre  = (int)($st['prelim'] ?? 0);
            $semi = (int)($st['semi']   ?? 0);
            $final= (int)($st['final']  ?? 0);
            if ($pre  > 0) $stageSettingsMap[$tid]['予選']   = $stageSettingsMap[$tid]['予選']   ?? $pre;
            if ($semi > 0) $stageSettingsMap[$tid]['準決勝'] = $stageSettingsMap[$tid]['準決勝'] ?? $semi;
            if ($final> 0) $stageSettingsMap[$tid]['決勝']   = $stageSettingsMap[$tid]['決勝']   ?? $final;
        }

        foreach ($stageSettingsMap as $tid => $st) {
            $clean = [];
            foreach (['予選','準々決勝','準決勝','決勝'] as $label) {
                $g = (int)($st[$label] ?? 0);
                if ($g > 0) $clean[$label] = $g;
            }
            $stageSettingsMap[$tid] = $clean;
        }

        return view('scores.input', compact('tournaments','stageSettingsMap'));
    }

    /* ================= ステージ設定保存（既存） ================= */
    public function saveSettingBulk(Request $r)
    {
        $r->validate(['tournament_id' => 'required|integer']);
        $tid = (int)$r->tournament_id;

        $mapJA = [];
        $stages = (array)$r->input('stages', []);
        foreach ($stages as $label => $row) {
            $g = (int)($row['total_games'] ?? 0);
            $enabled = (isset($row['enabled']) && (string)$row['enabled'] === '1') || $g > 0;
            if ($enabled && $g > 0) $mapJA[(string)$label] = $g;
        }
        $p = (int)$r->input('prelim_games', 0);
        $s = (int)$r->input('semi_games',   0);
        $f = (int)$r->input('final_games',  0);
        if ($p>0 || $s>0 || $f>0) {
            if ($p>0) $mapJA['予選']   = $mapJA['予選']   ?? $p;
            if ($s>0) $mapJA['準決勝'] = $mapJA['準決勝'] ?? $s;
            if ($f>0) $mapJA['決勝']   = $mapJA['決勝']   ?? $f;
        }

        $allNew = (array)session('stage_settings', []);
        $allNew[$tid] = $mapJA; session()->put('stage_settings', $allNew);

        $allOld = (array)session('score_settings', []);
        $allOld[$tid] = [
            'prelim' => (int)($mapJA['予選']   ?? 0),
            'semi'   => (int)($mapJA['準決勝'] ?? 0),
            'final'  => (int)($mapJA['決勝']   ?? 0),
        ];
        session()->put('score_settings', $allOld);

        return back()->with('ok','ステージ設定を保存しました');
    }

    /* ================= スコア登録（既存+性別対応） ================= */
    public function store(Request $r)
    {
        $r->validate([
            'tournament_id'  =>'required|integer',
            'stage'          =>'required|string',
            'game_number'    =>'required|integer|min:1|max:30',
            'identifier_type'=>'required|string|in:license_number,entry_number,name',
            'gender'         =>'nullable|in:M,F',
            'rows'           =>'required|array',
        ]);

        $tid   = (int)$r->tournament_id;
        $stage = (string)$r->stage;
        $game  = (int)$r->game_number;
        $type  = (string)$r->identifier_type;
        $gender= $r->gender ?: null;

        if ($game > 1 && $type === 'license_number') {
            $base = GameScore::query()->where('tournament_id',$tid)
                ->where('stage',$stage)->where('game_number',1);
            if ($gender) {
                $base->where(function($q) use ($gender){
                    $q->where('gender',$gender)
                      ->orWhere('license_number','like',$gender.'%');
                });
            }
            $known = array_flip($base->pluck('license_number')->filter()->all());
            foreach ($r->rows as $i=>$row) {
                $id    = trim((string)($row['id'] ?? ''));
                $score = (int)($row['score'] ?? 0);
                if ($id === '' || $score <= 0) continue;
                if (!isset($known[$id])) {
                    return back()->withErrors(['rows.'.$i.'.id' => '1Gに存在しないライセンス番号です（性別も確認）'])->withInput();
                }
            }
        }

        if ($type === 'license_number') {
            $seen = [];
            foreach ($r->rows as $i=>$row) {
                $id=trim((string)($row['id']??'')); if($id==='')continue;
                $key = ($gender ?? '') . '#' . $id;
                if(isset($seen[$key])) return back()->withErrors(['rows.'.$i.'.id'=>'同一ゲーム内で重複しています（性別区別）'])->withInput();
                $seen[$key]=true;
            }
        }

        $count=0;
        foreach ($r->rows as $row) {
            $id = trim((string)($row['id'] ?? ''));
            $sc = (int)($row['score'] ?? 0);
            if ($id === '' || $sc <= 0) continue;

            $existing = GameScore::query()
                ->where('tournament_id',$tid)
                ->where('stage',$stage)
                ->where('game_number',$game)
                ->where('license_number',$id)
                ->where(function($q){ $q->whereNull('gender')->orWhere('gender',''); })
                ->first();

            if ($existing && $gender) {
                $existing->gender = $gender;
                $existing->score  = $sc;
                $existing->save();
                $count++;
                continue;
            }

            $data = [
                'tournament_id'=>$tid,
                'stage'        =>$stage,
                'game_number'  =>$game,
                'score'        =>$sc,
                'gender'       =>$gender,
            ];
            $data[$type] = $id;

            GameScore::create($data);
            $count++;
        }

        return back()->with('success', "{$count} 件のスコアを登録しました")
                     ->withInput($r->only(['tournament_id','stage','game_number','identifier_type','gender','shift']));
    }

    /* ================= 速報 ================= */
    public function result(Request $r, ScoreService $service)
    {
        $opt = [
            'tournament_id'  => (int)$r->get('tournament_id'),
            'stage'          => (string)$r->get('stage', '予選'),
            'upto_game'      => (int)$r->get('upto_game', 1),
            'shifts'         => (string)$r->get('shifts',''),
            'gender_filter'  => (string)$r->get('gender_filter',''),
            'per_point'      => (int)$r->get('per_point', 200),
            'border_type'    => (string)$r->get('border_type','rank'),
            'border_value'   => $r->get('border_value'),
            'carry_prelim'   => (int)$r->get('carry_prelim', 0),
            'carry_semifinal'=> (int)$r->get('carry_semifinal', 0), // ★追加
        ];
        $data = $service->getRankings($opt);

        $t = Tournament::find($opt['tournament_id']);
        $tournament_name = $t?->name ?? '大会名';

        return view('scores.result', [
            'rankings'        => $data['rows'],
            'meta'            => $data['meta'],
            'tournament_name' => $tournament_name,
            'stage'           => $opt['stage'],
            'upto_game'       => (int)$opt['upto_game'],
            'border_type'     => $opt['border_type'],
            'border_value'    => $opt['border_value'],
            'per_point'       => (int)$opt['per_point'],
            'carry_prelim'    => (int)$opt['carry_prelim'],
            'shifts'          => (string)$opt['shifts'],
            'gender_filter'   => (string)$opt['gender_filter'],
        ]);
    }

    /* ================= 入力中 即時検知 API ================= */
    public function apiExistingIds(Request $r)
    {
        $tid    = (int)$r->query('tournament_id');
        $stage  = (string)$r->query('stage', '予選');
        $game   = max(1, (int)$r->query('game_number', 1));
        $shift  = trim((string)$r->query('shift', ''));
        $gender = trim((string)$r->query('gender', ''));

        $base = GameScore::query()->where('tournament_id', $tid)->where('stage', $stage);
        if ($shift !== '') $base->where('shift', $shift);

        $prevQ  = (clone $base)->where('game_number', 1);
        $existQ = (clone $base)->where('game_number', $game);

        if ($gender !== '') {
            $wrap = function ($q) use ($gender) {
                $q->where('gender', $gender)
                  ->orWhere('license_number', 'like', $gender.'%');
            };
            $prevQ->where($wrap);
            $existQ->where($wrap);
        }

        $prev   = $prevQ->pluck('license_number')->map(fn($v)=>$this->digitsOnly($v))->filter()->unique()->values()->all();
        $exists = $existQ->pluck('license_number')->map(fn($v)=>$this->digitsOnly($v))->filter()->unique()->values()->all();

        return response()->json([
            'prevDigits'        => $prev,
            'existsThisGame'    => $exists,
            'enforceFirstGame'  => ($game > 1),
        ]);
    }

    /* ====== 個別更新/削除・クリア ====== */

    public function updateOne(Request $r)
    {
        $r->validate([
            'tournament_id'=>'required|integer',
            'stage'        =>'required|string',
            'game_number'  =>'required|integer|min:1|max:30',
            'identifier_type'=>'required|string|in:license_number,entry_number,name',
            'identifier'   =>'required|string',
            'score'        =>'required|integer|min:0|max:300',
            'shift'        =>'nullable|string',
            'gender'       =>'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id',(int)$r->tournament_id)
            ->where('stage',(string)$r->stage)
            ->where('game_number',(int)$r->game_number);

        if ($r->filled('shift'))  $q->where('shift',$r->shift);
        if ($r->filled('gender')) $q->where('gender',$r->gender);

        $col = $r->identifier_type;
        $val = trim((string)$r->identifier);
        if ($col === 'license_number') {
            $digits = $this->digitsOnly($val);
            $q->where('license_number','like','%'.$digits);
        } else {
            $q->where($col,$val);
        }

        $row = $q->first();
        if ($row) {
            $row->score = (int)$r->score;
            if ($r->filled('gender') && !$row->gender) $row->gender = $r->gender;
            $row->save();
            return back()->with('success','1件更新しました');
        }
        return back()->with('success','対象データが見つかりません（何も更新していません）');
    }

    public function deleteOne(Request $r)
    {
        $r->validate([
            'tournament_id'=>'required|integer',
            'stage'        =>'required|string',
            'game_number'  =>'required|integer|min:1|max:30',
            'identifier_type'=>'required|string|in:license_number,entry_number,name',
            'identifier'   =>'required|string',
            'shift'        =>'nullable|string',
            'gender'       =>'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id',(int)$r->tournament_id)
            ->where('stage',(string)$r->stage)
            ->where('game_number',(int)$r->game_number);

        if ($r->filled('shift'))  $q->where('shift',$r->shift);
        if ($r->filled('gender')) $q->where('gender',$r->gender);

        $col = $r->identifier_type;
        $val = trim((string)$r->identifier);
        if ($col === 'license_number') {
            $digits = $this->digitsOnly($val);
            $q->where('license_number','like','%'.$digits);
        } else {
            $q->where($col,$val);
        }

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました");
    }

    public function clearGame(Request $r)
    {
        $r->validate([
            'tournament_id'=>'required|integer',
            'stage'        =>'required|string',
            'game_number'  =>'required|integer|min:1|max:30',
            'shift'        =>'nullable|string',
            'gender'       =>'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id',(int)$r->tournament_id)
            ->where('stage',(string)$r->stage)
            ->where('game_number',(int)$r->game_number);

        if ($r->filled('shift'))  $q->where('shift',$r->shift);
        if ($r->filled('gender')) $q->where('gender',$r->gender);

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました（このゲーム）");
    }

    public function clearAll(Request $r)
    {
        $r->validate([
            'tournament_id'=>'required|integer',
            'stage'        =>'required|string',
            'shift'        =>'nullable|string',
            'gender'       =>'nullable|in:M,F',
        ]);

        $q = GameScore::query()
            ->where('tournament_id',(int)$r->tournament_id)
            ->where('stage',(string)$r->stage);

        if ($r->filled('shift'))  $q->where('shift',$r->shift);
        if ($r->filled('gender')) $q->where('gender',$r->gender);

        $deleted = $q->delete();
        return back()->with('success', "{$deleted} 件を削除しました（ステージ全体）");
    }

    private function digitsOnly(?string $s): string
    {
        if (!$s) return '';
        if (preg_match('/\d+/', $s, $m)) return (string)$m[0];
        return '';
    }
}
