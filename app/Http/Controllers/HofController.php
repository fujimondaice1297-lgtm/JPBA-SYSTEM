<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Services\HofService;
use Carbon\Carbon;

final class HofController extends Controller
{
    public function index(HofService $svc)
    {
        $vm = $svc->listByYear();
        return view('hof.index', ['years'=>$vm['years'], 'byYear'=>$vm['data']]);
    }

    /** /hof/{slug} */
    public function show(string $slug)
    {
        $norm = $this->normalizeLicenseKey($slug);
        if ($norm === null) abort(404);

        // === プロ基本情報 ===
        $T     = env('JPBA_PROFILES_TABLE', 'pro_bowlers');
        $CID   = env('JPBA_PROFILES_ID_COL', 'id');
        $CNO   = env('JPBA_PROFILES_SLUG_COL', 'license_no');
        $CNAME = env('JPBA_PROFILES_NAME_COL', 'name_kanji');

        $CPORT = env('JPBA_PROFILES_PORTRAIT_URL_COL', 'public_image_path');
        $CBIO  = env('JPBA_PROFILES_BIO_COL', 'free_comment');
        $CKANA = env('JPBA_PROFILES_KANA_COL', 'name_kana');
        $CBYEAR= env('JPBA_PROFILES_BIRTH_YEAR_COL', 'birthdate');
        $CHAND = env('JPBA_PROFILES_HAND_COL', 'dominant_arm');
        $CORG  = env('JPBA_PROFILES_ORG_COL', 'organization_name');
        $CENTRY= env('JPBA_PRO_ENTRY_YEAR_COL', 'pro_entry_year');

        $has = fn(string $col) => $this->hasColumn($T, $col);

        $sel = [
            DB::raw("$CID as id"),
            DB::raw("$CNO as slug"),
            DB::raw("$CNAME as name"),
            $has($CPORT) ? DB::raw("$CPORT as portrait_url") : DB::raw("NULL as portrait_url"),
            $has($CBIO)  ? DB::raw("$CBIO  as biography")    : DB::raw("NULL as biography"),
            $has($CKANA) ? DB::raw("$CKANA as kana")         : DB::raw("NULL as kana"),
            $has($CBYEAR)? DB::raw("$CBYEAR as birth_year")  : DB::raw("NULL as birth_year"),
            $has($CHAND) ? DB::raw("$CHAND as hand")         : DB::raw("NULL as hand"),
            $has($CORG)  ? DB::raw("$CORG  as organizations"): DB::raw("NULL as organizations"),
            $has($CENTRY)? DB::raw("$CENTRY as entry_year")  : DB::raw("NULL as entry_year"),
        ];

        // ライセンス番号を M/L + 数字に正規化して比較
        $normCol = "REGEXP_REPLACE(REGEXP_REPLACE(UPPER({$CNO}), '[^A-Z0-9]', '', 'g'), '^(M|L)0+', '\\1')";
        $pro = DB::table($T)->whereRaw("$normCol = ?", [$norm])->first($sel);
        if (!$pro) abort(404);

        // 殿堂レコード & 写真
        $ind = DB::table('hof_inductions')->where('pro_id', $pro->id)
            ->orderByDesc('year')->first(['id','year','citation']);

        $photos = [];
        if ($ind) {
            $photos = DB::table('hof_photos')->where('hof_id', $ind->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['url','credit'])
                ->map(fn($p)=>['url'=>$p->url,'credit'=>$p->credit])
                ->toArray();
        }

        // タイトル一覧（優勝）
        $titles = [];
        if ($TT = env('JPBA_TITLES_TABLE')) {
            $TPRO = env('JPBA_TITLES_PRO_ID_COL', 'pro_bowler_id');
            $TY   = env('JPBA_TITLES_YEAR_COL', 'year');
            $TN   = env('JPBA_TITLES_NAME_COL', 'title_name');
            $TNOTE= env('JPBA_TITLES_NOTE_COL', 'tournament_name');

            $titles = DB::table($TT)->where($TPRO, $pro->id)
                ->orderByDesc($TY)->orderByDesc(env('JPBA_TITLES_ID_COL','id'))
                ->get([DB::raw("$TY as year"), DB::raw("$TN as name"), DB::raw("$TNOTE as note")])
                ->map(fn($t)=>['year'=>$t->year,'name'=>$t->name,'note'=>$t->note])
                ->toArray();
        }
        $winCount = count($titles);

        // ★ 修正：褒章レコードの集計（COUNT(*) に別名を付けて pluck）
        $awards = ['perfect'=>0,'eight_hundred'=>0,'seven_ten'=>0];
        if ($RS = $this->detectRecordsSource()) {
            $rt = $RS['table']; $rc = $RS['cols'];
            $grp = DB::table($rt)
                ->where($rc['pro_id'], $pro->id)
                ->selectRaw("{$rc['type']} AS rec_type, COUNT(*) AS cnt")
                ->groupBy($rc['type'])
                ->pluck('cnt', 'rec_type')   // ← ここで 'cnt' をキーつきで取得
                ->all();

            $normKey = function(string $v): ?string {
                $s = strtolower($v);
                if ($s==='perfect' || str_contains($s,'perfect')) return 'perfect';
                if ($s==='seven_ten' || (str_contains($s,'7') && str_contains($s,'10'))) return 'seven_ten';
                if ($s==='eight_hundred' || $s==='800' || str_contains($s,'800')) return 'eight_hundred';
                return null;
            };
            foreach ($grp as $type=>$cnt) {
                if ($k = $normKey((string)$type)) { $awards[$k] += (int)$cnt; }
            }
        }

        // プロフィール抜粋（facts）
        $facts = [];
        $add = function(string $label, $value) use (&$facts) {
            $v = is_string($value) ? trim($value) : $value;
            if ($v !== null && $v !== '') $facts[] = ['label'=>$label, 'value'=>$v];
        };

        if (!empty($pro->birth_year)) {
            $by = (string)$pro->birth_year;
            try {
                $disp = preg_match('/^\d{4}\-\d{2}\-\d{2}/',$by) ? Carbon::parse($by)->format('Y/m/d')
                      : (preg_match('/^\d{4}$/',$by) ? ($by.'年') : $by);
            } catch (\Throwable $e) { $disp = $by; }
            $add('生年月日', $disp);
        }
        $add('プロ入り',  !empty($pro->entry_year) ? ((string)$pro->entry_year.'年') : null);
        $add('利き腕',     $pro->hand ?? null);
        $add('所属',       $pro->organizations ?? null);
        $add('ふりがな',   $pro->kana ?? null);

        if ($winCount > 0)              $add('優勝回数',        $winCount.'回');
        if ($awards['perfect']>0)       $add('公認パーフェクト', $awards['perfect'].'回');
        if ($awards['eight_hundred']>0) $add('800シリーズ',      $awards['eight_hundred'].'回');
        if ($awards['seven_ten']>0)     $add('7-10メイド',      $awards['seven_ten'].'回');

        $vm = [
            'induction' => $ind ? [
                'id'=>(int)$ind->id, 'year'=>(int)$ind->year, 'citation'=>$ind->citation, 'photos'=>$photos,
            ] : ['id'=>null,'year'=>null,'citation'=>null,'photos'=>[]],
            'pro' => [
                'slug'=>$pro->slug,'name'=>$pro->name,'portrait_url'=>$pro->portrait_url,
                'biography'=>$pro->biography,'kana'=>$pro->kana,'birth_year'=>$pro->birth_year,
                'death_year'=>null,'hand'=>$pro->hand,'organizations'=>$pro->organizations,
            ],
            'titles' => $titles,
            'facts'  => $facts,
        ];

        return view('hof.show', ['vm'=>$vm]);
    }

    /** 'm001297' → 'M1297' / 'l000123' → 'L123' */
    private function normalizeLicenseKey(string $raw): ?string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
        if (!preg_match('/^(M|L)(\d+)$/', $s, $m)) return null;
        $d = ltrim($m[2], '0'); if ($d === '') $d = '0';
        return $m[1].$d;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }

    /** 褒章テーブル自動検出 */
    private function detectRecordsSource(): ?array
    {
        $t = env('JPBA_RECORDS_TABLE');
        if ($t && DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name',$t)->exists()) {
            $pro  = env('JPBA_RECORDS_PRO_ID_COL','pro_bowler_id');
            $type = env('JPBA_RECORDS_TYPE_COL','record_type');
            if ($this->hasColumn($t,$pro) && $this->hasColumn($t,$type)) {
                return ['table'=>$t,'cols'=>['pro_id'=>$pro,'type'=>$type]];
            }
        }

        $cands = ['pro_bowler_records','records','bowling_records','award_records','achievements'];
        $proKeys  = ['pro_bowler_id','player_id','bowler_id','pro_id'];
        $typeKeys = ['record_type','type','code','kind'];

        foreach ($cands as $cand) {
            $pickedPro = null; $pickedType = null;
            foreach ($proKeys as $k)  if ($this->hasColumn($cand,$k))  { $pickedPro  = $k; break; }
            foreach ($typeKeys as $k) if ($this->hasColumn($cand,$k)) { $pickedType = $k; break; }
            if ($pickedPro && $pickedType) {
                return ['table'=>$cand,'cols'=>['pro_id'=>$pickedPro,'type'=>$pickedType]];
            }
        }

        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()");
        foreach ($tables as $r) {
            $cand = (string)$r->table_name;
            $pickedPro = null; $pickedType = null;
            foreach ($proKeys as $k)  if ($this->hasColumn($cand,$k))  { $pickedPro  = $k; break; }
            foreach ($typeKeys as $k) if ($this->hasColumn($cand,$k)) { $pickedType = $k; break; }
            if ($pickedPro && $pickedType) {
                return ['table'=>$cand,'cols'=>['pro_id'=>$pickedPro,'type'=>$pickedType]];
            }
        }
        return null;
    }
}
