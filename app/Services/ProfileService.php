<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProfileService（プロフィール解決サービス）
 * - ライセンス番号（4桁）から、プロフィール元テーブルを自動検出して「氏名・写真URL」を取得。
 * - env があれば JPBA_PROFILES_* を優先（既存の殿堂/HOFと同じ方針）。
 * - DBは PostgreSQL 前提の正規化一致（"M/F+番号" に統一）で衝突を回避。
 */
final class ProfileService
{
    /** キャッシュ（同一リクエスト内） */
    private static ?array $profileSource = null;

    /** 単体解決 */
    public function resolve(?string $digits, ?string $gender = null): ?array
    {
        $digits = trim((string)$digits);
        if ($digits === '') return null;

        $src = $this->detectProfileSource();
        if (!$src) return null;

        $t   = $src['table'];
        $c   = $src['cols'];
        if (!isset($c['slug'])) return null;

        $slugCol = $c['slug'];
        $nameCol = $c['name']         ?? null;
        $portCol = $c['portrait_url'] ?? null;

        $digitsNoZero = ltrim($digits, '0');
        if ($digitsNoZero === '') $digitsNoZero = '0';

        $targets = ($gender === 'M' || $gender === 'F')
            ? [$gender.$digitsNoZero]
            : ['M'.$digitsNoZero, 'F'.$digitsNoZero];

        $normCol = $this->normalizedLicenseSql("p.{$slugCol}");

        $sel = [
            $nameCol ? DB::raw("p.{$nameCol} as _name") : DB::raw("NULL as _name"),
            $portCol ? DB::raw("p.{$portCol} as _photo") : DB::raw("NULL as _photo"),
        ];

        $row = DB::table($t.' as p')
            ->where(function($q) use ($normCol, $targets){
                foreach ($targets as $s) $q->orWhereRaw("$normCol = ?", [$s]);
            })
            ->limit(1)
            ->first($sel);

        if (!$row) return null;

        $name  = $row->_name ?: null;
        $photo = $row->_photo ?: null;

        if (is_string($photo) && $photo !== '') {
            if (!preg_match('#^https?://#', $photo) && !str_starts_with($photo, '/')) {
                $photo = '/storage/'.$photo;
            }
        } else {
            $photo = null;
        }

        return ['name'=>$name, 'portrait_url'=>$photo];
    }

    /** バッチ解決 */
    public function resolveBatch(array $digitsList, ?string $gender=null): array
    {
        $digitsList = array_values(array_unique(
            array_filter(array_map(fn($x)=>trim((string)$x), $digitsList), fn($v)=>$v!=='')
        ));
        if (!$digitsList) return [];

        $src = $this->detectProfileSource();
        if (!$src) return [];

        $t   = $src['table'];
        $c   = $src['cols'];
        if (!isset($c['slug'])) return [];

        $slugCol = $c['slug'];
        $nameCol = $c['name']         ?? null;
        $portCol = $c['portrait_url'] ?? null;

        // ターゲットを M/F で展開
        $targets = [];
        foreach ($digitsList as $d) {
            $d = ltrim($d, '0'); if ($d==='') $d='0';
            if ($gender === 'M' || $gender === 'F') {
                $targets[] = $gender.$d;
            } else {
                $targets[] = 'M'.$d;
                $targets[] = 'F'.$d;
            }
        }

        $normCol = $this->normalizedLicenseSql("p.{$slugCol}");
        $sel = [
            DB::raw("$normCol as _key"),
            $nameCol ? DB::raw("p.{$nameCol} as _name") : DB::raw("NULL as _name"),
            $portCol ? DB::raw("p.{$portCol} as _photo") : DB::raw("NULL as _photo"),
        ];

        $rows = DB::table($t.' as p')
            ->where(function($q) use ($normCol, $targets){
                foreach ($targets as $s) $q->orWhereRaw("$normCol = ?", [$s]);
            })
            ->get($sel);

        $map = [];
        foreach ($rows as $r) {
            $key = (string)$r->_key; // 'M1297' / 'F1297'
            if (preg_match('/^(M|F)(\d+)$/', $key, $m)) {
                $digits = $m[2];
                $photo  = $r->_photo ?: null;
                if ($photo && !preg_match('#^https?://#', $photo) && !str_starts_with($photo, '/')) {
                    $photo = '/storage/'.$photo;
                }
                $map[$digits] = $map[$digits] ?? ['name'=>($r->_name ?: null), 'portrait_url'=>$photo];
            }
        }
        return $map;
    }

    /* ===================== 検出と正規化 ===================== */

    /** プロフィール元テーブル検出（env優先、なければ自動） */
    private function detectProfileSource(): ?array
    {
        if (self::$profileSource !== null) return self::$profileSource;

        $forced = env('JPBA_PROFILES_TABLE');
        if ($forced && Schema::hasTable($forced)) {
            $cols = $this->columnsFromEnvProfiles($forced) ?: $this->mapColumnsForProfiles($forced);
            if ($this->hasRequired($cols)) return self::$profileSource = ['table'=>$forced,'cols'=>$cols];
        }

        $cands = ['pros','pro_profiles','pro_bowlers','bowlers','players','members','profiles','jpba_pros'];
        foreach ($cands as $t) {
            if (!Schema::hasTable($t)) continue;
            $cols = $this->mapColumnsForProfiles($t);
            if ($this->hasRequired($cols)) return self::$profileSource = ['table'=>$t,'cols'=>$cols];
        }

        $rows = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()");
        foreach ($rows as $r) {
            $t = (string)$r->table_name;
            $cols = $this->mapColumnsForProfiles($t);
            if ($this->hasRequired($cols)) return self::$profileSource = ['table'=>$t,'cols'=>$cols];
        }
        return self::$profileSource = null;
    }

    private function hasRequired(array $cols): bool
    { return isset($cols['name'], $cols['slug']); }

    private function columnsFromEnvProfiles(string $table): array
    {
        $env = [
            'name'         => env('JPBA_PROFILES_NAME_COL'),
            'slug'         => env('JPBA_PROFILES_SLUG_COL'),
            'portrait_url' => env('JPBA_PROFILES_PORTRAIT_URL_COL'),
        ];
        $env = array_filter($env, fn($v)=>$v!==null && $v!=='');
        if (!$env) return [];
        $all = $this->columns($table);
        foreach ($env as $k=>$col) {
            if (!in_array($col, $all, true)) return [];
        }
        return $env;
    }

    private function mapColumnsForProfiles(string $table): array
    {
        $all = $this->columns($table);
        $has = fn($n)=>in_array($n, $all, true);
        $pick = function(array $opts) use($has){ foreach($opts as $o) if($has($o)) return $o; return null; };

        return array_filter([
            'name'         => $pick(['name','full_name','display_name','name_kanji']),
            'slug'         => $pick(['slug','code','identifier','permalink','license_no','license','member_code']),
            'portrait_url' => $pick(['portrait_url','public_image_path','image_path','photo_url','image','avatar','thumb_url']),
        ], fn($v)=>$v!==null);
    }

    /** PostgreSQL の正規化式：UPPER→非英数除去→(M|F)直後の0を削除 */
    private function normalizedLicenseSql(string $columnSql): string
    {
        return "REGEXP_REPLACE(REGEXP_REPLACE(UPPER($columnSql), '[^A-Z0-9]', '', 'g'), '^(M|F)0+', '\\1')";
    }

    private function columns(string $table): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', $table)
            ->pluck('column_name')->map(fn($x)=>(string)$x)->toArray();
    }
}
