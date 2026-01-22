<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema; // （用語）Schema：DBスキーマ情報にアクセスできるLaravelのヘルパ

class VenuePageController extends Controller
{
    // 一覧
    public function index(Request $request)
    {
        $q = Venue::query();

        if ($request->filled('keyword')) {
            $kw = trim($request->keyword);
            // ILIKE（用語：PostgreSQLの大文字小文字を無視するLIKE）
            $q->where('name', 'ILIKE', "%{$kw}%");
        }

        $venues = $q->orderBy('name')->get();

        return view('venues.index', compact('venues'));
    }

    // 新規
    public function create()
    {
        $venue = new Venue();
        return view('venues.create', compact('venue'));
    }

    // 保存
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'nullable|string|max:255',
            'postal_code' => ['nullable','regex:/^\d{3}-?\d{4}$/'],
            'tel'         => 'nullable|string|max:50',
            'fax'         => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'note'        => 'nullable|string|max:2000',
        ]);

        // 郵便番号の表記統一（1234567 -> 123-4567）
        if (!empty($validated['postal_code'])) {
            $pc = preg_replace('/\D/','',$validated['postal_code']);
            if (strlen($pc) === 7) {
                $validated['postal_code'] = substr($pc,0,3).'-'.substr($pc,3);
            }
        }

        // ★変更理由：値が捨てられないようにするため。
        // 以前は「存在するカラムだけ」を保存していたが、これが原因で tel/fax/URL が欠落。
        // 以降は保存前に“必須カラムがDBに存在するか”を検査し、無いならエラーで止める。
        $this->assertColumnsExist('venues', [
            'name','address','postal_code','tel','fax','website_url','note'
        ]);

        Venue::create($validated);

        return redirect()->route('venues.index')->with('success', '会場を登録しました。');
    }

    // 編集
    public function edit($id)
    {
        $venue = Venue::findOrFail($id);
        return view('venues.edit', compact('venue'));
    }

    // 更新
    public function update(Request $request, $id)
    {
        $venue = Venue::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'nullable|string|max:255',
            'postal_code' => ['nullable','regex:/^\d{3}-?\d{4}$/'],
            'tel'         => 'nullable|string|max:50',
            'fax'         => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'note'        => 'nullable|string|max:2000',
        ]);

        if (!empty($validated['postal_code'])) {
            $pc = preg_replace('/\D/','',$validated['postal_code']);
            if (strlen($pc) === 7) {
                $validated['postal_code'] = substr($pc,0,3).'-'.substr($pc,3);
            }
        }

        // ★同上：保存前に列存在チェック。見つからなければエラー。
        $this->assertColumnsExist('venues', [
            'name','address','postal_code','tel','fax','website_url','note'
        ]);

        $venue->update($validated);

        return redirect()->route('venues.index')->with('success', '会場を更新しました。');
    }

    // 削除
    public function destroy($id)
    {
        $venue = Venue::findOrFail($id);
        $venue->delete();

        return redirect()->route('venues.index')->with('success', '会場を削除しました。');
    }

    /**
     * DBの指定テーブルに必要なカラムが全て存在するか検査し、
     * 欠落があれば人間が気づけるようにバリデーションエラーを投げる。
     * （用語）search_path：PostgreSQLの検索スキーマ。これのズレで「列が無い」ように見えることがある。
     */
    private function assertColumnsExist(string $table, array $required): void
    {
        if (!Schema::hasTable($table)) {
            abort(500, "テーブル {$table} が見つかりません。接続先や search_path を確認してください。");
        }
        $cols = Schema::getColumnListing($table);
        $missing = array_values(array_diff($required, $cols));
        if (!empty($missing)) {
            // 人が読めるように日本語名も添える
            $labels = [
                'name'=>'会場名','address'=>'住所','postal_code'=>'郵便番号',
                'tel'=>'TEL','fax'=>'FAX','website_url'=>'公式サイト','note'=>'会場データ'
            ];
            $human = array_map(fn($k)=>$labels[$k]??$k, $missing);
            $msg = 'DBに次の列がありません：'.implode(' / ', $human).'（テーブル: '.$table.'）';
            // バリデーションエラー扱いで戻す
            back()->withErrors(['db_columns' => $msg])->throwResponse();
        }
    }
}
