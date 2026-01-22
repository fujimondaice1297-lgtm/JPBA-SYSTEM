<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HofService
{
    private static ?array $profileSource = null;
    private static ?array $titlesSource  = null;

    /** 年別一覧（既存のまま） */
    public function listByYear(?int $year = null): array
    {
        $src = $this->detectProfileSource();
        $t = $src['table']; $c = $src['cols'];

        $q = DB::table('hof_inductions as h')
            ->join("$t as p", "p.{$c['id']}", '=', 'h.pro_id')
            ->when($year !== null, fn($x) => $x->where('h.year', $year))
            ->orderByDesc('h.year')->orderBy("p.{$c['name']}");

        $selects = [
            DB::raw('h.year as year'),
            DB::raw("p.{$c['slug']} as slug"),
            DB::raw("p.{$c['name']} as name"),
            isset($c['portrait_url'])
                ? DB::raw("p.{$c['portrait_url']} as portrait_url")
                : DB::raw("NULL as portrait_url"),
        ];

        $rows = $q->get($selects);

        $years=[]; $grouped=[];
        foreach ($rows as $r) {
            $y=(int)$r->year;
            $years[$y]=$y;
            $grouped[$y][]=[
                'slug'=>(string)$r->slug,
                'name'=>(string)$r->name,
                'portrait_url'=>$r->portrait_url,
                'year'=>$y,
            ];
        }
        $years=array_values(array_reverse($years,true));
        return ['years'=>$years,'data'=>$grouped];
    }

    /**
     * 詳細：URLの slug は必ず M/L から始まる前提で「正規化キー一致」で取得。
     * 例) 'm-001297' / 'M1297' / 'm0001297' → 'M1297' に正規化。
     * 数字だけ（'1297' 等）は男女衝突のため **不採用**。見つからなければ null。
     */
    public function detailBySlug(string $slug): ?array
    {
        $src = $this->detectProfileSource();
        $t = $src['table']; $c = $src['cols'];

        // 1) URL 文字列を正規化（M/L 必須。無ければ null）
        $target = $this->normalizeLicenseKey($slug);
        if ($target === null) {
            return null; // 性別が判別できない入力はマッチさせない
        }

        // DB 側のカラムを同じ規則で正規化する SQL（PostgreSQL）
        $normCol = $this->normalizedLicenseSql("p.{$c['slug']}");

        // 2) 殿堂 + プロの突き合わせ（正規化キー一致）
        $selects = [
            DB::raw('h.id as hof_id'),
            DB::raw('h.year as induction_year'),
            DB::raw('h.citation as citation'),
            DB::raw("p.{$c['id']} as pro_id"),
            DB::raw("p.{$c['slug']} as slug"),
            DB::raw("p.{$c['name']} as name"),
        ];
        foreach (['portrait_url','biography','kana','birth_year','death_year','hand','organizations'] as $opt) {
            $selects[] = isset($c[$opt]) ? DB::raw("p.{$c[$opt]} as {$opt}") : DB::raw("NULL as {$opt}");
        }

        $row = DB::table('hof_inductions as h')
            ->join("$t as p", "p.{$c['id']}", '=', 'h.pro_id')
            ->whereRaw("$normCol = ?", [$target])
            ->orderByDesc('h.year') // 重複が万一あれば最新を優先
            ->first($selects);

        if (!$row) return null;

        // 3) 写真
        $photos = DB::table('hof_photos')
            ->where('hof_id', $row->hof_id)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['url','credit'])
            ->map(fn($p)=>['url'=>$p->url,'credit'=>$p->credit])
            ->toArray();

        // 4) タイトル（あれば）
        $titles = [];
        $ts = $this->detectTitlesSource();
        if ($ts) {
            $tt = $ts['table']; $tc = $ts['cols'];
            $sel = [
                DB::raw(($tc['id']   ?? 'NULL')." as id"),
                DB::raw("{$tc['year']} as year"),
                DB::raw("{$tc['name']} as name"),
            ];
            $sel[] = isset($tc['note']) ? DB::raw("{$tc['note']} as note") : DB::raw("NULL as note");

            $titles = DB::table($tt)
                ->where($tc['pro_id'], $row->pro_id)
                ->orderByDesc($tc['year'])
                ->when(isset($tc['id']), fn($q)=>$q->orderByDesc($tc['id']))
                ->get($sel)
                ->map(fn($t)=>[
                    'id'   => isset($t->id) ? (int)$t->id : null,
                    'year' => $t->year,
                    'name' => $t->name,
                    'note' => $t->note,
                ])->toArray();
        }

        return [
            'induction'=>[
                'id'     => (int)$row->hof_id,
                'year'   => (int)$row->induction_year,
                'citation'=>$row->citation,
                'photos' => $photos
            ],
            'pro'=>[
                'slug'=>$row->slug,'name'=>$row->name,'portrait_url'=>$row->portrait_url,
                'biography'=>$row->biography,'kana'=>$row->kana,'birth_year'=>$row->birth_year,
                'death_year'=>$row->death_year,'hand'=>$row->hand,'organizations'=>$row->organizations,
            ],
            'titles'=>$titles,
        ];
    }

    /* ===================== プロフィール元テーブル検出（既存） ===================== */
    private function detectProfileSource(): array
    {
        if (self::$profileSource) return self::$profileSource;

        $forcedTable = env('JPBA_PROFILES_TABLE');
        if ($forcedTable && Schema::hasTable($forcedTable)) {
            $cols = $this->columnsFromEnvProfiles($forcedTable) ?: $this->mapColumnsForProfiles($forcedTable);
            $this->assertRequiredProfiles($forcedTable, $cols);
            return self::$profileSource = ['table'=>$forcedTable,'cols'=>$cols];
        }

        $candidates = ['pros','pro_profiles','pro_bowlers','bowlers','players','members','profiles','jpba_pros'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                $cols = $this->mapColumnsForProfiles($t);
                if ($this->hasRequiredProfiles($cols)) {
                    return self::$profileSource = ['table'=>$t,'cols'=>$cols];
                }
            }
        }

        $rows = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()");
        $best=null; $score=-1;
        foreach ($rows as $r) {
            $t=(string)$r->table_name;
            $cols=$this->mapColumnsForProfiles($t);
            if (!$this->hasRequiredProfiles($cols)) continue;
            $s=count($cols); if ($s>$score) { $score=$s; $best=['table'=>$t,'cols'=>$cols]; }
        }
        if ($best) return self::$profileSource = $best;

        throw new \RuntimeException("プロフィール元テーブルを特定できませんでした。 .env に JPBA_PROFILES_TABLE=テーブル名 を設定してください。");
    }

    private function columnsFromEnvProfiles(string $table): array
    {
        $envMap = [
            'id'            => env('JPBA_PROFILES_ID_COL'),
            'name'          => env('JPBA_PROFILES_NAME_COL'),
            'slug'          => env('JPBA_PROFILES_SLUG_COL'),
            'portrait_url'  => env('JPBA_PROFILES_PORTRAIT_URL_COL'),
            'biography'     => env('JPBA_PROFILES_BIO_COL'),
            'kana'          => env('JPBA_PROFILES_KANA_COL'),
            'birth_year'    => env('JPBA_PROFILES_BIRTH_YEAR_COL'),
            'death_year'    => env('JPBA_PROFILES_DEATH_YEAR_COL'),
            'hand'          => env('JPBA_PROFILES_HAND_COL'),
            'organizations' => env('JPBA_PROFILES_ORG_COL'),
        ];
        $envMap = array_filter($envMap, fn($v)=>$v!==null && $v!=='');
        if (!$envMap) return [];

        $all = $this->tableColumns($table);
        foreach ($envMap as $k=>$col) {
            if (!in_array($col, $all, true)) {
                throw new \RuntimeException("env指定の列 '{$col}' がテーブル {$table} に見つかりません（キー {$k}）。");
            }
        }
        return $envMap;
    }

    private function mapColumnsForProfiles(string $table): array
    {
        $cols = $this->tableColumns($table);
        $has = fn($n)=>in_array($n,$cols,true);
        $pick = function(array $opts) use ($has){ foreach($opts as $o) if($has($o)) return $o; return null; };

        $map = [
            'id'            => $pick(['id','pro_id','player_id','member_id']),
            'name'          => $pick(['name','full_name','display_name','name_kanji']),
            'slug'          => $pick(['slug','code','identifier','url_key','permalink','license_no']),
            'portrait_url'  => $pick(['portrait_url','public_image_path','image_path','photo_url','image','avatar','thumb_url']),
            'biography'     => $pick(['biography','bio','profile','introduction','intro','about','free_comment']),
            'kana'          => $pick(['kana','name_kana','furigana','yomigana','ruby']),
            'birth_year'    => $pick(['birth_year','birthdate','birthyear','byear','born_year']),
            'death_year'    => $pick(['death_year','deathdate','deathyear','dyear','passed_year']),
            'hand'          => $pick(['hand','handedness','dominant_hand']),
            'organizations' => $pick(['organizations','orgs','organization_name','affiliations','team','teams','association']),
        ];
        return array_filter($map, fn($v)=>$v!==null);
    }

    private function hasRequiredProfiles(array $cols): bool
    { return isset($cols['id'],$cols['name'],$cols['slug']); }

    private function assertRequiredProfiles(string $table, array $cols): void
    {
        if (!$this->hasRequiredProfiles($cols)) {
            throw new \RuntimeException("テーブル {$table} に id/name/slug に相当する列が見つかりません。envで *_COL を指定してください。");
        }
    }

    /* ===================== タイトル表の検出（既存） ===================== */
    private function detectTitlesSource(): ?array
    {
        if (self::$titlesSource !== null) return self::$titlesSource;

        $t = env('JPBA_TITLES_TABLE');
        if ($t && Schema::hasTable($t)) {
            $cols = $this->columnsFromEnvTitles($t) ?: $this->mapColumnsForTitles($t);
            if (isset($cols['pro_id'],$cols['year'],$cols['name'])) {
                return self::$titlesSource = ['table'=>$t,'cols'=>$cols];
            }
        }

        $candidates = ['titles','pro_titles','achievements','awards','wins','results_titles'];
        foreach ($candidates as $cand) {
            if (!Schema::hasTable($cand)) continue;
            $cols = $this->mapColumnsForTitles($cand);
            if (isset($cols['pro_id'],$cols['year'],$cols['name'])) {
                return self::$titlesSource = ['table'=>$cand,'cols'=>$cols];
            }
        }

        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()");
        foreach ($tables as $r) {
            $cand = (string)$r->table_name;
            $cols = $this->mapColumnsForTitles($cand);
            if (isset($cols['pro_id'],$cols['year'],$cols['name'])) {
                return self::$titlesSource = ['table'=>$cand,'cols'=>$cols];
            }
        }

        return self::$titlesSource = null;
    }

    private function columnsFromEnvTitles(string $table): array
    {
        $envMap = [
            'id'     => env('JPBA_TITLES_ID_COL'),
            'pro_id' => env('JPBA_TITLES_PRO_ID_COL'),
            'year'   => env('JPBA_TITLES_YEAR_COL'),
            'name'   => env('JPBA_TITLES_NAME_COL'),
            'note'   => env('JPBA_TITLES_NOTE_COL'),
        ];
        $envMap = array_filter($envMap, fn($v)=>$v!==null && $v!=='');
        if (!$envMap) return [];

        $all = $this->tableColumns($table);
        foreach ($envMap as $k=>$col) {
            if (!in_array($col, $all, true)) {
                throw new \RuntimeException("env指定の列 '{$col}' がテーブル {$table} に見つかりません（タイトル用 {$k}）。");
            }
        }
        if (!isset($envMap['pro_id'],$envMap['year'],$envMap['name'])) return [];
        return $envMap;
    }

    private function mapColumnsForTitles(string $table): array
    {
        $cols = $this->tableColumns($table);
        $has = fn($n)=>in_array($n,$cols,true);
        $pick = function(array $opts) use ($has){ foreach($opts as $o) if($has($o)) return $o; return null; };

        $map = [
            'id'     => $pick(['id','title_id','record_id']),
            'pro_id' => $pick(['pro_id','bowler_id','player_id','member_id','probowler_id']),
            'year'   => $pick(['year','win_year','season','y']),
            'name'   => $pick(['name','title','tournament','event','competition']),
            'note'   => $pick(['note','memo','remarks','comment','detail']),
        ];
        return array_filter($map, fn($v)=>$v!==null);
    }

    /* ===================== 正規化ユーティリティ ===================== */

    /**
     * URL 等の入力を 'M1234' / 'L567' に正規化。該当しない場合は null。
     */
    private function normalizeLicenseKey(string $raw): ?string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw)); // 英数以外除去して大文字化
        if (!preg_match('/^(M|L)(\d+)$/', $s, $m)) return null;
        $digits = ltrim($m[2], '0');
        if ($digits === '') $digits = '0';
        return $m[1] . $digits; // 例: 'M1297'
    }

    /**
     * DB カラムを URL 側と同じ正規化ルールで式化（PostgreSQL）。
     * 1) 大文字化→2) 非英数除去→3) 先頭 'M|L' の直後の 0 を削る
     * 例: 'm-001297' / 'M01297' / 'm0001297' → 'M1297'
     */
    private function normalizedLicenseSql(string $columnSql): string
    {
        // UPPER → 非英数除去 → (M|L)直後の 0* を削る
        return "REGEXP_REPLACE(REGEXP_REPLACE(UPPER($columnSql), '[^A-Z0-9]', '', 'g'), '^(M|L)0+', '\\1')";
    }

    /* ===================== 小物 ===================== */
    private function tableColumns(string $table): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', $table)
            ->pluck('column_name')
            ->map(fn($x)=>(string)$x)->toArray();
    }
}
