<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class HofManageController extends Controller
{
    public function create()
    {
        return view('hof.create');
    }

    public function store(Request $req)
    {
        $req->validate([
            'slug'     => ['required','string','max:64'],
            'year'     => ['required','integer','between:1900,2100'],
            'citation' => ['nullable','string'],
        ]);

        $T   = env('JPBA_PROFILES_TABLE');
        $CID = env('JPBA_PROFILES_ID_COL', 'id');
        $CSL = env('JPBA_PROFILES_SLUG_COL', 'slug');
        if (!$T) abort(500, 'JPBA_PROFILES_TABLE が未設定です');

        // 入力 slug を正規化（M/L 必須。数字だけは不可）
        $target = (function(string $raw): ?string {
            $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
            if (!preg_match('/^(M|L)(\d+)$/', $s, $m)) return null;
            $digits = ltrim($m[2], '0'); if ($digits==='') $digits='0';
            return $m[1].$digits;
        })($req->input('slug'));

        if ($target === null) {
            return back()
                ->withErrors(['slug'=>'「M または L + 番号」で指定してください（数字だけは不可）。例：M1297 / L0123 / m-000123'])
                ->withInput();
        }

        // DB 側も正規化して一致させる（PostgreSQL 式）
        $normCol = "REGEXP_REPLACE(REGEXP_REPLACE(UPPER({$CSL}), '[^A-Z0-9]', '', 'g'), '^(M|L)0+', '\\1')";
        $pro = DB::table($T)->whereRaw("$normCol = ?", [$target])->first([$CID]);

        if (!$pro) {
            return back()->withErrors(['slug'=>'該当するプロが見つかりません'])->withInput();
        }

        $exists = DB::table('hof_inductions')->where('pro_id',$pro->$CID)->first(['id']);
        if ($exists) {
            return redirect()->route('hof.edit',['id'=>$exists->id])->with('info','既に登録済みです');
        }

        $id = DB::table('hof_inductions')->insertGetId([
            'pro_id' => $pro->$CID, 'year' => (int)$req->year,
            'citation'=>$req->citation, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return redirect()->route('hof.edit',['id'=>$id])->with('ok','殿堂レコードを作成しました。写真を追加できます。');
    }


    public function edit(int $id)
    {
        $row = DB::table('hof_inductions')->where('id', $id)->first();
        if (!$row) abort(404);

        $T     = env('JPBA_PROFILES_TABLE');
        $CID   = env('JPBA_PROFILES_ID_COL', 'id');
        $CNAME = env('JPBA_PROFILES_NAME_COL', 'name_kanji');
        $CPORT = env('JPBA_PROFILES_PORTRAIT_URL_COL', 'public_image_path');
        $CSLG  = env('JPBA_PROFILES_SLUG_COL', 'slug');

        $pro = DB::table($T)->where($CID, $row->pro_id)
            ->first([$CID.' as id', $CSLG.' as slug', $CNAME.' as name', $CPORT.' as portrait_url']);

        $photos = DB::table('hof_photos')->where('hof_id', $id)
            ->orderBy('sort_order')->orderBy('id')->get();

        return view('hof.edit', ['hof' => $row, 'pro' => $pro, 'photos' => $photos]);
    }

    public function update(int $id, Request $req)
    {
        $req->validate([
            'year'     => ['required','integer','between:1900,2100'],
            'citation' => ['nullable','string'],
        ]);

        $n = DB::table('hof_inductions')->where('id', $id)->update([
            'year'       => (int)$req->year,
            'citation'   => $req->citation,
            'updated_at' => now(),
        ]);

        return back()->with('ok', $n ? '更新しました' : '変更なし');
    }

    /** 写真アップロード（昇順/降順の位置指定対応） */
    public function uploadPhoto(int $id, Request $req)
    {
        $req->validate([
            'file'     => ['required','file','image','max:4096'],
            'credit'   => ['nullable','string','max:255'],
            'position' => ['nullable','in:asc,desc'], // asc=先頭, desc=末尾（デフォルト）
        ]);

        // 並び順の自動計算
        $pos    = $req->input('position', 'desc');
        $bounds = DB::table('hof_photos')->where('hof_id', $id)
            ->selectRaw('MIN(sort_order) AS min, MAX(sort_order) AS max')->first();
        $sort = ($pos === 'asc')
            ? (is_null($bounds->min) ? 0 : (int)$bounds->min - 10)
            : (is_null($bounds->max) ? 0 : (int)$bounds->max + 10);

        // 保存（public ディスク）
        $path = $req->file('file')->store('hof', 'public'); // storage/app/public/hof/...
        $url  = Storage::disk('public')->url($path);        // => /storage/hof/...

        // 公開リンクが無い場合の案内
        if (!is_dir(public_path('storage'))) {
            DB::table('hof_photos')->insert([
                'hof_id'     => $id,
                'url'        => $url,
                'credit'     => $req->credit,
                'sort_order' => $sort,
                'created_at' => now(),
            ]);
            return back()->with('info', '公開ストレージ（/public/storage）のリンクが未作成です。ブラウザで /_dev/storage/link を一度開いてください。');
        }

        DB::table('hof_photos')->insert([
            'hof_id'     => $id,
            'url'        => $url,
            'credit'     => $req->credit,
            'sort_order' => $sort,
            'created_at' => now(),
        ]);

        return back()->with('ok', '写真を追加しました');
    }

    /** 写真の削除（管理者専用ルートでガード） */
    public function destroyPhoto(int $photoId, Request $req)
    {
        $row = DB::table('hof_photos')->where('id', $photoId)->first();
        if (!$row) abort(404);

        // /storage/hof/... にあるローカル実体だけ物理削除
        $url  = (string)$row->url;
        $path = parse_url($url, PHP_URL_PATH);
        if ($path && str_starts_with($path, '/storage/')) {
            $relative = ltrim(substr($path, strlen('/storage/')), '/'); // 'hof/xxx.jpg'
            if ($relative !== '') {
                try { Storage::disk('public')->delete($relative); } catch (\Throwable $e) {}
            }
        }

        DB::table('hof_photos')->where('id', $photoId)->delete();
        return back()->with('ok', '写真を削除しました');
    }

    /** 殿堂レコードの削除（管理者専用ルートでガード） */
    public function destroy(int $id, Request $req)
    {
        $hof = DB::table('hof_inductions')->where('id', $id)->first();
        if (!$hof) abort(404);

        // ひも付く写真の実体ファイルを先に削除
        $photos = DB::table('hof_photos')->where('hof_id', $id)->get(['url','id']);
        foreach ($photos as $p) {
            $path = parse_url((string)$p->url, PHP_URL_PATH);
            if ($path && str_starts_with($path, '/storage/')) {
                $relative = ltrim(substr($path, strlen('/storage/')), '/'); // 'hof/xxx.jpg'
                if ($relative !== '') {
                    try { Storage::disk('public')->delete($relative); } catch (\Throwable $e) {}
                }
            }
        }

        // 写真行 → 殿堂レコードの順に削除
        DB::table('hof_photos')->where('hof_id', $id)->delete();
        DB::table('hof_inductions')->where('id', $id)->delete();

        return redirect()->route('hof.index')->with('ok', '殿堂レコードを削除しました');
    }

    /** URL 追加（互換用。通常は uploadPhoto を使用） */
    public function addPhotoUrl(int $id, Request $req)
    {
        $req->validate([
            'url'    => ['required','string','max:2000'],
            'credit' => ['nullable','string','max:255'],
        ]);

        $bounds = DB::table('hof_photos')->where('hof_id', $id)
            ->selectRaw('COALESCE(MAX(sort_order),0) AS max')->first();

        DB::table('hof_photos')->insert([
            'hof_id'     => $id,
            'url'        => $req->url,
            'credit'     => $req->credit,
            'sort_order' => ((int)$bounds->max) + 10,
            'created_at' => now(),
        ]);

        return back()->with('ok', '写真（URL）を追加しました');
    }
}
